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
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DebtsPerDaysExport implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        protected string $monthDate = '', // YYYY-mm-dd (cualquier día del mes)
        protected bool   $onlyActive = true,
        protected string $condition  = '' // '', 'DT', 'GN', 'EX', 'EX5', ...
    ) {}

    // para styling
    private array $days = [];
    private int $rowCount = 0;  // filas de datos
    private int $dayCols  = 0;  // cantidad de días (columnas dinámicas)

    public function title(): string
    {
        return 'Deuda por Día';
    }

    public function view(): View
    {
        // Normaliza mes
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

        if ($this->onlyActive)       $vehiclesQ->where('v.status','active');
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

                // ===== Inserta 2 filas para TÍTULO y SUBTÍTULO =====
                $ws->insertNewRowBefore(1, 2);

                $headerRow    = 3;   // thead real
                $dataStartRow = 4;   // primer dato
                $dayStartColIndex = 5; // E (A:Item, B:Cod, C:Placa, D:Condición)
                $dayEndColIndex   = $dayStartColIndex + max(0, $this->dayCols - 1);
                // +4 columnas finales: P días, P S/, D días, D S/
                $lastColIndex     = $dayEndColIndex + 4;
                $lastColLetter    = $this->colLetter($lastColIndex);

                // ===== TÍTULO (fila 1) =====
                $monthLabel = !empty($this->days)
                    ? Carbon::parse($this->days[0]['d'])->locale('es')->translatedFormat('F Y')
                    : '';
                $ws->setCellValue('A1', 'DEUDA POR DÍA' . ($monthLabel ? " – {$monthLabel}" : ''));
                $ws->mergeCells("A1:{$lastColLetter}1");
                $ws->getRowDimension(1)->setRowHeight(24);
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937'); // barra oscura

                // ===== SUBTÍTULO (fila 2) =====
                $filters = [];
                $filters[] = 'Solo activos: ' . ($this->onlyActive ? 'Sí' : 'No');
                if ($this->condition !== '') $filters[] = 'Condición: ' . $this->condition;
                $ws->setCellValue('A2', implode(' | ', $filters));
                $ws->mergeCells("A2:{$lastColLetter}2");
                $ws->getRowDimension(2)->setRowHeight(18);
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');

                // ===== THEAD (fila 3) oscuro =====
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getRowDimension($headerRow)->setRowHeight(20);
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F'); // #009BDC

                // Domingo: cabeceras en rojo (sin tapar condicionales del cuerpo)
                foreach ($this->days as $i => $d) {
                    if (!empty($d['isSunday'])) {
                        $colLetter = $this->colLetter($dayStartColIndex + $i);
                        $ws->getStyle("{$colLetter}{$headerRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEF4444');
                        $ws->getStyle("{$colLetter}{$headerRow}")
                            ->getFont()->getColor()->setARGB('FFFFFFFF');
                    }
                }

                // ===== Congelar después de D y debajo del thead =====
                $ws->freezePane("E{$dataStartRow}");

                // ===== Rango de datos / autofiltro =====
                $lastDataRow = $dataStartRow + max(0, $this->rowCount) - 1;
                if ($lastDataRow >= $dataStartRow) {
                    $ws->setAutoFilter("A{$headerRow}:{$lastColLetter}{$lastDataRow}");
                } else {
                    $ws->setAutoFilter("A{$headerRow}:{$lastColLetter}{$headerRow}");
                }

                // ===== Zebra en cuerpo =====
                if ($lastDataRow >= $dataStartRow) {
                    $rangeData = "A{$dataStartRow}:{$lastColLetter}{$lastDataRow}";
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF9FAFB');
                    $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($rangeData)->setConditionalStyles($styles);
                }

                // ===== Bordes finos =====
                $ws->getStyle("A{$headerRow}:{$lastColLetter}" . max($headerRow, $lastDataRow))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCFD8DC');

                // ===== Formato condicional para celdas de DÍAS =====
                if ($lastDataRow >= $dataStartRow && $this->dayCols > 0) {
                    $firstDayLetter = $this->colLetter($dayStartColIndex);
                    $lastDayLetter  = $this->colLetter($dayEndColIndex);
                    $dayRange = "{$firstDayLetter}{$dataStartRow}:{$lastDayLetter}{$lastDataRow}";

                    // "P" ⇒ verde
                    $cP = new Conditional();
                    $cP->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
                    $cP->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
                    $cP->setText('P');
                    $cP->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFDCFCE7');
                    $cP->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // "NT" ⇒ rojo suave
                    $cNT = new Conditional();
                    $cNT->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
                    $cNT->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
                    $cNT->setText('NT');
                    $cNT->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFEE2E2');
                    $cNT->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // número ⇒ ámbar (ISNUMBER)
                    $cNum = new Conditional();
                    $cNum->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cNum->setConditions(["ISNUMBER({$firstDayLetter}{$dataStartRow})"]);
                    $cNum->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFEF3C7');
                    $cNum->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $styles = $ws->getStyle($dayRange)->getConditionalStyles();
                    $styles[] = $cP; $styles[] = $cNT; $styles[] = $cNum;
                    $ws->getStyle($dayRange)->setConditionalStyles($styles);
                }

                // ===== Montos: alineación y formato "S/ " =====
                $paidAmtColLetter = $this->colLetter($dayEndColIndex + 2);
                $debtAmtColLetter = $this->colLetter($dayEndColIndex + 4);
                if ($lastDataRow >= $dataStartRow) {
                    $ws->getStyle("{$paidAmtColLetter}{$dataStartRow}:{$paidAmtColLetter}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $ws->getStyle("{$debtAmtColLetter}{$dataStartRow}:{$debtAmtColLetter}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $ws->getStyle("{$paidAmtColLetter}{$dataStartRow}:{$paidAmtColLetter}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("{$debtAmtColLetter}{$dataStartRow}:{$debtAmtColLetter}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                }

                // ===== Anchos sugeridos =====
                $ws->getColumnDimension('A')->setWidth(6);   // Item
                $ws->getColumnDimension('B')->setWidth(8);   // Cod
                $ws->getColumnDimension('C')->setWidth(12);  // Placa
                $ws->getColumnDimension('D')->setWidth(11);  // Condición
                for ($c = $dayStartColIndex; $c <= $dayEndColIndex; $c++) {
                    $ws->getColumnDimension($this->colLetter($c))->setWidth(5); // días compactos
                }
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 1))->setWidth(10); // P días
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 2))->setWidth(14); // P S/
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 3))->setWidth(10); // D días
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 4))->setWidth(14); // D S/

                // ===== Pie de TOTALES con banda oscura (igual diseño) =====
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

                    // Estilo pie
                    $ws->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")
                        ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                    $ws->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F');
                    $ws->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")
                        ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                    $ws->getStyle("A{$totalRow}:D{$totalRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Formato moneda en sumas
                    $ws->getStyle("{$paidAmtColLetter}{$totalRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("{$debtAmtColLetter}{$totalRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
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
