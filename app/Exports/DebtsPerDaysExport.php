<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DebtsPerDaysExport implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        protected string $monthDate = '', // YYYY-mm-dd
        protected bool   $onlyActive = true,
        protected string $condition  = '' // '', 'DT', 'GN', 'EX', 'EX5', ...
    ) {}

    // para styling
    private array $days = [];
    private array $cellRows = []; // filas con celdas para colorear
    private int $rowCount = 0;  // filas de datos
    private int $dayCols  = 0;  // cantidad de días (columnas dinámicas)

    public function title(): string
    {
        return 'Deuda por Día';
    }

    public function view(): View
    {
        $seed = $this->monthDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->monthDate)
            ? Carbon::parse($this->monthDate)
            : now();

        [$from, $toMonthEnd] = $this->monthBoundaries($seed->toDateString());

        // Si es mes actual, cortar en hoy; si no, fin de mes
        $today  = now()->toDateString();
        $isCurr = $seed->isSameMonth($today);
        $cutoff = $isCurr ? min($today, $toMonthEnd) : $toMonthEnd;

        // Días del mes
        $days = $this->makeDays($from, $toMonthEnd);
        $this->days   = $days;
        $this->dayCols = count($days);

        // Vehículos
        $vehiclesQ = DB::table('vehicles as v')
            ->select('v.id','v.plate','v.sort_order','v.condition','v.status');

        // No filtrar por status — mostrar todos igual que el blade
        if ($this->condition !== '') $vehiclesQ->where('v.condition', $this->condition);

        $vehicles = $vehiclesQ
            ->orderByRaw('COALESCE(v.sort_order, 999999)')
            ->orderBy('v.plate')
            ->get();

        if ($vehicles->isEmpty()) {
            $rows = [];
            $this->rowCount = 0;
            return view('exports.debts_per_days_only_grid', [
                'rows'       => $rows,
                'days'       => $days,
                'monthLabel' => $seed->locale('es')->translatedFormat('F Y'),
                'onlyActive' => $this->onlyActive,
                'condition'  => $this->condition,
            ]);
        }

        $vehicleIds = $vehicles->pluck('id')->all();

        // Costos por placa/día
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

        // Pagos (excluye DEUDA)
        $payDay = DB::table('payments as p')
            ->select('p.vehicle_id', DB::raw('DATE(p.date_payment) as date'))
            ->whereIn('p.vehicle_id', $vehicleIds)
            ->where('p.type','<>','DEUDA')
            ->whereBetween(DB::raw('DATE(p.date_payment)'), [$from, $toMonthEnd])
            ->groupBy('p.vehicle_id', DB::raw('DATE(p.date_payment)'))
            ->get();

        $payExists = [];
        foreach ($payDay as $p) {
            $payExists[$p->vehicle_id][$p->date] = true;
        }

        // Salidas (sum(times)), excluyendo Huachipa / Lima
        $deps = DB::table('departures as d')
            ->leftJoin('headquarters as h','h.id','=','d.headquarter_id')
            ->select('d.vehicle_id','d.date', DB::raw('SUM(d.times) as k1'))
            ->whereIn('d.vehicle_id', $vehicleIds)
            ->whereBetween('d.date', [$from, $toMonthEnd])
            ->where(function($q){
                $q->whereNull('h.name')->orWhereNotIn('h.name', ['Huachipa','Lima']);
            })
            ->groupBy('d.vehicle_id','d.date')
            ->get();

        $depMap = [];
        foreach ($deps as $d) {
            $depMap[$d->vehicle_id][$d->date] = (int) $d->k1;
        }

        // Filas
        $rows = [];
        $item = 0;

        foreach ($vehicles as $v) {
            $item++;
            $isExempt = Str::startsWith((string)$v->condition, 'EX'); // EX, EX5, ...

            $row = [
                'item'        => $item,
                'cod'         => $v->sort_order,
                'plate'       => $v->plate,
                'condition'   => $v->condition,
                'cells'       => [],
                'paid_days'   => 0,
                'paid_amount' => 0.0,
                'debt_days'   => 0,
                'debt_amount' => 0.0,
            ];

            foreach ($days as $d) {
                $date = $d['d'];
                if ($d['isSunday']) {
                    $row['cells'][] = ['txt'=>'', 'type'=>'sun'];
                    continue;
                }

                if ($isExempt) {
                    $row['cells'][] = ['txt'=>'NT', 'type'=>'nopay'];
                    continue;
                }

                $cost = (float)($costMap[$v->id][$date] ?? 0.0);

                // Pago presente -> "P"
                if (!empty($payExists[$v->id][$date])) {
                    $row['cells'][] = ['txt'=>'P', 'type'=>'paid'];
                    if ($date <= $cutoff) {
                        $row['paid_days']++;
                        $row['paid_amount'] += $cost;
                    }
                    continue;
                }

                // Sin pago: ver salidas
                $k = (int)($depMap[$v->id][$date] ?? 0);
                if ($k > 0) {
                    $row['cells'][] = ['txt'=>(string)$k, 'type'=>'freq'];
                    if ($date <= $cutoff) {
                        $row['debt_days']++;
                        $row['debt_amount'] += $cost;
                    }
                } else {
                    $row['cells'][] = ['txt'=>'NT', 'type'=>'nopay'];
                }
            }

            if (!$isExempt) {
                $row['paid_amount'] = round($row['paid_amount'], 2);
                $row['debt_amount'] = round($row['debt_amount'], 2);
            }

            $rows[] = $row;
        }

        $this->rowCount = count($rows);
        $this->cellRows = $rows;

        return view('exports.debts_per_days_only_grid', [
            'rows'       => $rows,
            'days'       => $days,
            'monthLabel' => $seed->locale('es')->translatedFormat('F Y'),
            'onlyActive' => $this->onlyActive,
            'condition'  => $this->condition,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Paleta
                $blue     = 'FF2874A6'; // header
                $footerBg = 'FFCEE7FF'; // footer
                $white    = 'FFFFFFFF';
                $borderC  = '000000';
                $sunRed   = 'F80000';

                // Fuente base 10 pt, sin gridlines
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->setShowGridlines(false);

                // ===== Insertar SOLO 1 fila (título) =====
                $ws->insertNewRowBefore(1, 1);

                $headerRow    = 2;           // thead real
                $dataStartRow = 3;           // primer dato
                $dayStartColIndex = 5;       // E (A:Item, B:Cod, C:Placa, D:Condición)
                $dayEndColIndex   = $dayStartColIndex + max(0, $this->dayCols - 1);
                // +4 columnas finales: P días, P S/, D días, D S/
                $lastColIndex     = $dayEndColIndex + 4;
                $lastColLetter    = $this->colLetter($lastColIndex);
                $lastColIdx       = $lastColIndex;

                // Letras de las 4 columnas finales
                $pDaysCol = $this->colLetter($dayEndColIndex + 1);
                $pAmtCol  = $this->colLetter($dayEndColIndex + 2);
                $dDaysCol = $this->colLetter($dayEndColIndex + 3);
                $dAmtCol  = $this->colLetter($dayEndColIndex + 4);

                // ===== TÍTULO (fila 1) =====
                $monthLabel = !empty($this->days)
                    ? Carbon::parse($this->days[0]['d'])->locale('es')->translatedFormat('F Y')
                    : '';
                $ws->setCellValue('A1', 'DEUDA POR DÍA' . ($monthLabel ? " – {$monthLabel}" : ''));
                $ws->mergeCells("A1:{$lastColLetter}1");
                $ws->getRowDimension(1)->setRowHeight(18);
                $ws->getStyle('A1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$sunRed]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
                    'borders' => ['allBorders' => ['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FF000000']]],
                ]);

                // ===== THEAD (fila 2) azul =====
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(16);

                // Combinar headers: "Total Pagos" y "Total Deuda"
                $ws->mergeCells("{$pDaysCol}{$headerRow}:{$pAmtCol}{$headerRow}");
                $ws->setCellValue("{$pDaysCol}{$headerRow}", 'Total Pagos');
                $ws->mergeCells("{$dDaysCol}{$headerRow}:{$dAmtCol}{$headerRow}");
                $ws->setCellValue("{$dDaysCol}{$headerRow}", 'Total Deuda');

                // Domingos en rojo (solo header de días)
                foreach ($this->days as $i => $d) {
                    if (!empty($d['isSunday'])) {
                        $colLetter = $this->colLetter($dayStartColIndex + $i);
                        $ws->getStyle("{$colLetter}{$headerRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($sunRed);
                    }
                }

                // Freeze después de D y debajo del thead
                //$ws->freezePane("E{$dataStartRow}");

                // Rango de datos
                $lastDataRow = $dataStartRow + max(0, $this->rowCount) - 1;

                // Bordes datos: dashed horizontal, thin vertical
                if ($lastDataRow >= $dataStartRow) {
                    $dataRange = "A{$dataStartRow}:{$lastColLetter}{$lastDataRow}";
                    $borders = $ws->getStyle($dataRange)->getBorders();
                    $borders->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($borderC);
                    $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($borderC);
                    $borders->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($borderC);
                    $borders->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($borderC);
                    $borders->getHorizontal()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setRGB($borderC);
                    $borders->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($borderC);
                }

                // Bordes header
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB($borderC);

                // Montos (S/)
                $paidAmtColLetter = $this->colLetter($dayEndColIndex + 2);
                $debtAmtColLetter = $this->colLetter($dayEndColIndex + 4);
                if ($lastDataRow >= $dataStartRow) {
                    $ws->getStyle("{$paidAmtColLetter}{$dataStartRow}:{$paidAmtColLetter}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("{$debtAmtColLetter}{$dataStartRow}:{$debtAmtColLetter}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $ws->getStyle("{$paidAmtColLetter}{$dataStartRow}:{$paidAmtColLetter}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    $ws->getStyle("{$debtAmtColLetter}{$dataStartRow}:{$debtAmtColLetter}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // ===== Colores por celda (formato legacy) =====
                if ($lastDataRow >= $dataStartRow && !empty($this->days)) {
                    $rowIdx = $dataStartRow;
                    foreach ($this->cellRows as $r) {
                        foreach ($r['cells'] as $ci => $cell) {
                            $colLetter = $this->colLetter($dayStartColIndex + $ci);
                            $ref = "{$colLetter}{$rowIdx}";
                            if ($cell['type'] === 'paid') {
                                // P = negro
                                $ws->getStyle($ref)->getFont()->getColor()->setRGB('000000');
                            } elseif ($cell['type'] === 'nopay') {
                                // NT = naranja
                                $ws->getStyle($ref)->getFont()->getColor()->setRGB('FF8C00');
                            } elseif ($cell['type'] === 'freq') {
                                // Vueltas = azul bold
                                $ws->getStyle($ref)->getFont()->getColor()->setRGB('0000FF');
                                $ws->getStyle($ref)->getFont()->setBold(true);
                            } elseif ($cell['type'] === 'sun') {
                                // Domingo = fondo blanco
                                $ws->getStyle($ref)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
                            }
                        }
                        $rowIdx++;
                    }
                }

                // ===== Anchos compactos =====
                // Desactiva autosize por columna con índice (evita range('A','AA'))
                for ($i = 1; $i <= $lastColIdx; $i++) {
                    $ws->getColumnDimension($this->colLetter($i))->setAutoSize(false);
                }

                $ws->getColumnDimension('A')->setWidth(4.0);   // Item
                $ws->getColumnDimension('B')->setWidth(3.0);   // Cod
                $ws->getColumnDimension('C')->setWidth(10.0);  // Placa
                $ws->getColumnDimension('D')->setWidth(6.5);   // Condición
                for ($c = $dayStartColIndex; $c <= $dayEndColIndex; $c++) {
                    $ws->getColumnDimension($this->colLetter($c))->setWidth(4.0); // días compactos
                }
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 1))->setWidth(5.0);   // P días
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 2))->setWidth(8.0);   // P S/
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 3))->setWidth(5.0);   // D días
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 4))->setWidth(8.0);   // D S/

                // Alineaciones básicas
                if ($lastDataRow >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("B{$dataStartRow}:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("C{$dataStartRow}:C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("D{$dataStartRow}:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    if ($this->dayCols > 0) {
                        $firstDayLetter = $this->colLetter($dayStartColIndex);
                        $lastDayLetter  = $this->colLetter($dayEndColIndex);
                        $ws->getStyle("{$firstDayLetter}{$dataStartRow}:{$lastDayLetter}{$lastDataRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                // Pie de TOTALES con banda #CEE7FF
                if ($lastDataRow >= $dataStartRow) {
                    $totalRow = $lastDataRow + 1;

                    // Etiqueta TOTAL
                    $ws->mergeCells("A{$totalRow}:D{$totalRow}");
                    $ws->setCellValue("A{$totalRow}", 'TOTAL');

                    // Sumas por columnas finales
                    $paidDaysColLetter = $this->colLetter($dayEndColIndex + 1);
                    $ws->setCellValue("{$paidDaysColLetter}{$totalRow}", "=SUM({$paidDaysColLetter}{$dataStartRow}:{$paidDaysColLetter}{$lastDataRow})");
                    $ws->setCellValue("{$paidAmtColLetter}{$totalRow}", "=SUM({$paidAmtColLetter}{$dataStartRow}:{$paidAmtColLetter}{$lastDataRow})");

                    $debtDaysColLetter = $this->colLetter($dayEndColIndex + 3);
                    $ws->setCellValue("{$debtDaysColLetter}{$totalRow}", "=SUM({$debtDaysColLetter}{$dataStartRow}:{$debtDaysColLetter}{$lastDataRow})");
                    $ws->setCellValue("{$debtAmtColLetter}{$totalRow}", "=SUM({$debtAmtColLetter}{$dataStartRow}:{$debtAmtColLetter}{$lastDataRow})");

                    // Estilo pie: celeste, texto negro, bordes thin
                    $ws->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
                        'font' => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'000000']],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                        'borders' => ['allBorders' => ['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$borderC]]],
                    ]);
                    $ws->getStyle("{$paidAmtColLetter}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $ws->getStyle("{$debtAmtColLetter}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $ws->getStyle("{$paidDaysColLetter}{$totalRow}:{$debtDaysColLetter}{$totalRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }

    // ===== helpers =====
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
                'd' => $c->toDateString(),
                'n' => (int)$c->format('j'),
                'isSunday' => $c->dayOfWeekIso === 7,
            ];
            $c->addDay();
        }
        return $days;
    }

    private function colLetter(int $i): string
    {
        $s = '';
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    }
}
