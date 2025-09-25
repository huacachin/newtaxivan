<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class DelayDetailsExport implements FromArray, ShouldAutoSize, WithHeadings, WithEvents, WithStyles
{
    public function __construct(
        protected string  $monthDate,                   // cualquier día del mes: YYYY-mm-dd
        protected bool    $onlyActive   = true,         // solo vehículos activos
        protected string  $condition    = '',           // '', 'DT', 'GN', 'EX', 'EX5', etc.
        protected ?string $plateFilter  = null,         // filtro opcional por placa (like)
        protected array   $excludeHeads = ['Huachipa','Lima'] // sedes a excluir para vueltas
    ) {}

    private int $rowCount = 0;

    /** ---------------- datos ---------------- */
    public function array(): array
    {
        [$from, $toMonthEnd] = $this->monthBoundaries($this->monthDate);
        $today  = now()->toDateString();
        $seed   = Carbon::parse($this->monthDate);
        $isCurr = $seed->isSameMonth($today);
        $cutoff = $isCurr ? min($today, $toMonthEnd) : $toMonthEnd;

        // ====== VEHÍCULOS
        $vehiclesQ = DB::table('vehicles as v')
            ->select('v.id','v.plate','v.sort_order','v.condition','v.status');

        if ($this->onlyActive)       $vehiclesQ->where('v.status', 'active');
        if ($this->condition !== '') $vehiclesQ->where('v.condition', $this->condition);
        if ($this->plateFilter)      $vehiclesQ->where('v.plate', 'like', '%'.strtoupper($this->plateFilter).'%');

        $vehicles = $vehiclesQ
            ->orderByRaw('COALESCE(v.sort_order, 999999)')
            ->orderBy('v.plate')
            ->get();

        if ($vehicles->isEmpty()) {
            $this->rowCount = 0;
            return [];
        }

        $vehicleIds = $vehicles->pluck('id')->all();

        // ====== DÍAS DEL MES (para saltar domingos)
        $days = $this->makeDays($from, $toMonthEnd);

        // ====== COSTO POR PLACA/DÍA
        $costs = DB::table('cost_per_plate_days as c')
            ->select('c.vehicle_id','c.date', DB::raw('SUM(c.amount) as amount'))
            ->whereIn('c.vehicle_id', $vehicleIds)
            ->whereBetween('c.date', [$from, $toMonthEnd])
            ->groupBy('c.vehicle_id','c.date')
            ->get();
        $costMap = [];
        foreach ($costs as $c) {
            $costMap[$c->vehicle_id][$c->date] = (float) $c->amount;
        }

        // ====== PAGOS DEL DÍA (auto-detecta columnas de importe/fecha)
        [$amountCol, $dateCol] = $this->detectPaymentColumns();
        $payRows = DB::table('payments as p')
            ->select('p.vehicle_id', DB::raw("DATE(p.$dateCol) as d"), DB::raw("COUNT(*) as cnt"), DB::raw("COALESCE(SUM(p.$amountCol),0) as amt"))
            ->whereIn('p.vehicle_id', $vehicleIds)
            ->whereBetween(DB::raw("DATE(p.$dateCol)"), [$from, $toMonthEnd])
            ->where('p.type', '<>', 'DEUDA')
            ->groupBy('p.vehicle_id', DB::raw("DATE(p.$dateCol)"))
            ->get();
        $payMap = [];
        foreach ($payRows as $r) {
            $payMap[$r->vehicle_id][$r->d] = ['cnt' => (int)$r->cnt, 'amt' => (float)$r->amt];
        }

        // ====== SALIDAS por día (vueltas): SUM(times), excluye sedes
        $deps = DB::table('departures as d')
            ->leftJoin('headquarters as h','h.id','=','d.headquarter_id')
            ->select('d.vehicle_id','d.date', DB::raw('SUM(d.times) as k1'))
            ->whereIn('d.vehicle_id', $vehicleIds)
            ->whereBetween('d.date', [$from, $toMonthEnd])
            ->when(!empty($this->excludeHeads), function ($q) {
                $q->where(function($qq){
                    $qq->whereNull('h.name')->orWhereNotIn('h.name', $this->excludeHeads);
                });
            })
            ->groupBy('d.vehicle_id','d.date')
            ->get();
        $depMap = [];
        foreach ($deps as $d) {
            $depMap[$d->vehicle_id][$d->date] = (int) $d->k1;
        }

        // ====== CONSTRUIR FILAS (solo días con DEUDA y con salidas)
        $data = [];
        $item = 0;

        foreach ($vehicles as $v) {
            // Exonerados fuera (igual que legacy)
            if (Str::startsWith((string)$v->condition, 'EX')) {
                continue;
            }

            foreach ($days as $d) {
                $date = $d['d'];
                if ($d['isSunday']) continue; // saltar domingos

                // Costo del día: regla legacy
                $cost = $date <= '2023-04-30'
                    ? 10.00
                    : (float) ($costMap[$v->id][$date] ?? 0.00);

                // Pagos del día (excluye DEUDA)
                $cnt = (int)  ($payMap[$v->id][$date]['cnt'] ?? 0);
                $sum = (float)($payMap[$v->id][$date]['amt'] ?? 0.00);

                // Si hay pagos múltiples, multiplicar costo esperado
                $expected = $cnt > 1 ? ($cost * $cnt) : $cost;

                // Si el pagado ≠ esperado ⇒ evaluar salidas
                if (round($sum, 2) !== round($expected, 2)) {
                    $k1 = (int)($depMap[$v->id][$date] ?? 0);
                    if ($k1 > 0) {
                        $item++;
                        $vueltas = (int) ceil($k1 / 2);
                        $data[] = [
                            'item'   => $item,
                            'cod'    => $v->sort_order,
                            'fecha'  => $date,
                            'placa'  => $v->plate,
                            'vuelta' => $vueltas,
                            'monto'  => $expected, // costo del día (ajustado por cnt)
                        ];
                    }
                }
            }
        }

        $this->rowCount = count($data);
        return $data;
    }

    /** ---------------- encabezados ---------------- */
    public function headings(): array
    {
        return ['Item','Codigo','Fecha','Placa','Vueltas','S/'];
    }

    /** ---------------- estilos base (header bold) ---------------- */
    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** ---------------- after sheet: diseño pro + alternar por placa (1º negro, 2º rojo) ---------------- */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Insertar 2 filas para título/subtítulo
                $ws->insertNewRowBefore(1, 2);
                $headerRow    = 3;        // headings()
                $dataStartRow = 4;
                $lastRow      = $dataStartRow + max(0, $this->rowCount) - 1;
                $lastColLetter = 'F';     // A..F

                // Título
                $title = 'Reporte de Retrasos – Detalle';
                $ws->setCellValue('A1', $title);
                $ws->mergeCells("A1:{$lastColLetter}1");
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Subtítulo
                $seed   = Carbon::parse($this->monthDate);
                $monthL = $seed->locale('es')->translatedFormat('F Y');
                $filters = "Mes: {$monthL} | Solo activos: " . ($this->onlyActive ? 'Sí' : 'No');
                if ($this->condition !== '')   $filters .= " | Condición: {$this->condition}";
                if ($this->plateFilter)        $filters .= " | Placa: {$this->plateFilter}";
                $ws->setCellValue('A2', $filters);
                $ws->mergeCells("A2:{$lastColLetter}2");
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

                // Header con color
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF2874A6'); // azul
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getRowDimension($headerRow)->setRowHeight(22);

                // Congelar debajo del encabezado
                $ws->freezePane("A{$dataStartRow}");

                // Anchos (Item angosto)
                $ws->getColumnDimension('A')->setWidth(6);  // Item
                $ws->getColumnDimension('B')->setWidth(10); // Codigo
                $ws->getColumnDimension('C')->setWidth(12); // Fecha
                $ws->getColumnDimension('D')->setWidth(12); // Placa
                $ws->getColumnDimension('E')->setWidth(10); // Vueltas
                $ws->getColumnDimension('F')->setWidth(14); // S/

                // Autofiltro
                if ($lastRow >= $dataStartRow) {
                    $ws->setAutoFilter("A{$headerRow}:{$lastColLetter}{$lastRow}");
                } else {
                    $ws->setAutoFilter("A{$headerRow}:{$lastColLetter}{$headerRow}");
                }

                // Zebra stripes
                if ($lastRow >= $dataStartRow) {
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF8FAFC');
                    $rangeData = "A{$dataStartRow}:{$lastColLetter}{$lastRow}";
                    $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($rangeData)->setConditionalStyles($styles);
                }

                // Bordes finos
                $ws->getStyle("A{$headerRow}:{$lastColLetter}" . max($headerRow, $lastRow))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCBD5E1');

                // Alineaciones y moneda
                if ($lastRow >= $dataStartRow) {
                    $ws->getStyle("C{$dataStartRow}:C{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("D{$dataStartRow}:D{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("E{$dataStartRow}:E{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    // Moneda S/ en F
                    $ws->getStyle("F{$dataStartRow}:F{$lastRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("F{$dataStartRow}:F{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ----- Alternar color por bloque de placa (igual al legacy: 1º bloque NEGRO, 2º ROJO) -----
                if ($lastRow >= $dataStartRow) {
                    // comenzamos leyendo la primera placa: primer bloque en NEGRO
                    $prevPlate = (string) $ws->getCell("D{$dataStartRow}")->getValue();
                    $paintRed  = false; // false = negro, true = rojo

                    for ($r = $dataStartRow; $r <= $lastRow; $r++) {
                        $plate = (string) $ws->getCell("D{$r}")->getValue();

                        if ($r > $dataStartRow && $plate !== $prevPlate) {
                            $paintRed  = !$paintRed;   // alterna al cambiar de placa
                            $prevPlate = $plate;
                        }

                        // pinta TODA la fila (si quieres solo la placa, usa "D{$r}")
                        $rowRange = "A{$r}:F{$r}";
                        $ws->getStyle($rowRange)->getFont()->getColor()
                            ->setARGB($paintRed ? 'FFFF0000' : 'FF000000');
                        // opcional: negrita cuando es rojo
                        // $ws->getStyle($rowRange)->getFont()->setBold($paintRed);
                    }
                }

                // Fila TOTAL (suma S/)
                $totalRow = ($lastRow >= $dataStartRow) ? $lastRow + 1 : $headerRow + 1;
                $ws->mergeCells("A{$totalRow}:E{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL');
                if ($lastRow >= $dataStartRow) {
                    $ws->setCellValue("F{$totalRow}", "=SUM(F{$dataStartRow}:F{$lastRow})");
                } else {
                    $ws->setCellValue("F{$totalRow}", 0);
                }
                $ws->getStyle("A{$totalRow}:F{$totalRow}")->getFont()->setBold(true);
                $ws->getStyle("A{$totalRow}:F{$totalRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFCEE7FF');
                $ws->getStyle("F{$totalRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                $ws->getStyle("A{$totalRow}:E{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    /** ---------------- helpers ---------------- */
    private function monthBoundaries(string $anyDay): array
    {
        $d = Carbon::parse($anyDay)->startOfMonth();
        return [$d->toDateString(), $d->copy()->endOfMonth()->toDateString()];
    }

    private function makeDays(string $from, string $to): array
    {
        $days = [];
        $c = Carbon::parse($from);
        $end = Carbon::parse($to);
        while ($c->lte($end)) {
            $days[] = [
                'd'        => $c->toDateString(),
                'isSunday' => $c->dayOfWeekIso === 7,
            ];
            $c->addDay();
        }
        return $days;
    }

    private function detectPaymentColumns(): array
    {
        $table = 'payments';
        $cols  = Schema::getColumnListing($table);

        $amountCandidates = ['amount','total','total_amount','importe','import','price','value','amount_total'];
        $dateCandidates   = ['date_payment','date_register','fechac','fecha'];

        $amountCol = collect($amountCandidates)->first(fn($c) => in_array($c, $cols, true)) ?? 'amount';
        $dateCol   = collect($dateCandidates)->first(fn($c) => in_array($c, $cols, true)) ?? 'date_payment';

        return [$amountCol, $dateCol];
    }
}
