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
        protected string $monthDate = '', // YYYY-mm-dd
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

                // ===== Paleta / base =====
                $blue     = 'FF2874A6'; // header/título
                $footerBg = 'FFCEE7FF'; // footer
                $white    = 'FFFFFFFF';
                $black    = 'FF000000';
                $borderC  = 'FFCFD8DC';
                $sunRed   = 'FFEF4444';

                // Tipografía compacta
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // ===== Índices / columnas =====
                $headerRow       = 2;              // thead (tras insertar título)
                $dataStartRow    = 3;              // primera fila de datos
                $dayStartColIndex= 5;              // E: días empiezan en la col 5 (A..D fijas)
                $dayEndColIndex  = $dayStartColIndex + max(0, $this->dayCols - 1);
                $lastColIndex    = $dayEndColIndex + 4; // + [P días][P S/][D días][D S/]
                $lastColLetter   = $this->colLetter($lastColIndex);

                // ===== Insertar título (fila 1) =====
                $ws->insertNewRowBefore(1, 1);
                $monthLabel = !empty($this->days)
                    ? Carbon::parse($this->days[0]['d'])->locale('es')->translatedFormat('F Y')
                    : '';
                $ws->mergeCells("A1:{$lastColLetter}1");
                $ws->setCellValue('A1', 'DEUDA POR DÍA' . ($monthLabel ? " – {$monthLabel}" : ''));
                $ws->getRowDimension(1)->setRowHeight(18);
                $ws->getStyle("A1:{$lastColLetter}1")->applyFromArray([
                    'font'      => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);

                // ===== THEAD (fila 2) =====
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
                    'font'      => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(16);

                // Domingos en rojo (solo header de días)
                foreach ($this->days as $i => $d) {
                    if (!empty($d['isSunday'])) {
                        $colL = $this->colLetter($dayStartColIndex + $i);
                        $ws->getStyle("{$colL}{$headerRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($sunRed);
                    }
                }

                // Freeze: debajo del thead y a la derecha de D
                $ws->freezePane("E{$dataStartRow}");

                // Rango de datos existente
                $lastDataRow = $dataStartRow + max(0, $this->rowCount) - 1;

                // Zebra (solo datos)
                if ($lastDataRow >= $dataStartRow) {
                    $rangeData = "A{$dataStartRow}:{$lastColLetter}{$lastDataRow}";
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF3F4F6');
                    $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($rangeData)->setConditionalStyles($styles);
                }

                // Bordes finos (thead + datos)
                $ws->getStyle("A{$headerRow}:{$lastColLetter}" . max($headerRow, $lastDataRow))
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB($borderC);

                // ===== Condicionales en celdas de días =====
                if ($lastDataRow >= $dataStartRow && $this->dayCols > 0) {
                    $firstDayL = $this->colLetter($dayStartColIndex);
                    $lastDayL  = $this->colLetter($dayEndColIndex);
                    $dayRange  = "{$firstDayL}{$dataStartRow}:{$lastDayL}{$lastDataRow}";

                    // "P" ⇒ verde claro
                    $cP = new Conditional();
                    $cP->setConditionType(Conditional::CONDITION_CONTAINSTEXT)
                        ->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT)
                        ->setText('P');
                    $cP->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFDCFCE7');
                    $cP->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // "NT" ⇒ rojo suave
                    $cNT = new Conditional();
                    $cNT->setConditionType(Conditional::CONDITION_CONTAINSTEXT)
                        ->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT)
                        ->setText('NT');
                    $cNT->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFEE2E2');
                    $cNT->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Números (frecuencia) ⇒ ámbar (usaremos una expresión que colorea celdas no vacías ni “P/NT”)
                    $cNum = new Conditional();
                    $cNum->setConditionType(Conditional::CONDITION_EXPRESSION);
                    // Nota: en Excel, usar fórmula basada en la primera celda del rango aplicado
                    $cNum->setConditions(["AND(LEN(TRIM({$firstDayL}{$dataStartRow}))>0, {$firstDayL}{$dataStartRow}<>\"P\", {$firstDayL}{$dataStartRow}<>\"NT\")"]);
                    $cNum->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFEF3C7');
                    $cNum->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $styles = $ws->getStyle($dayRange)->getConditionalStyles();
                    $styles[] = $cP; $styles[] = $cNT; $styles[] = $cNum;
                    $ws->getStyle($dayRange)->setConditionalStyles($styles);
                }

                // ===== Alineaciones =====
                if ($lastDataRow >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:A{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("B{$dataStartRow}:B{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("C{$dataStartRow}:C{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true);
                    $ws->getStyle("D{$dataStartRow}:D{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    if ($this->dayCols > 0) {
                        $firstDayL = $this->colLetter($dayStartColIndex);
                        $lastDayL  = $this->colLetter($dayEndColIndex);
                        $ws->getStyle("{$firstDayL}{$dataStartRow}:{$lastDayL}{$lastDataRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                // ===== Anchos compactos =====
                foreach (range('A', $lastColLetter) as $col) {
                    $ws->getColumnDimension($col)->setAutoSize(false);
                }
                $ws->getColumnDimension('A')->setWidth(5.0);   // Item
                $ws->getColumnDimension('B')->setWidth(7.0);   // Cod
                $ws->getColumnDimension('C')->setWidth(10.0);  // Placa
                $ws->getColumnDimension('D')->setWidth(8.5);   // Condición
                for ($c = $dayStartColIndex; $c <= $dayEndColIndex; $c++) {
                    $ws->getColumnDimension($this->colLetter($c))->setWidth(5.5); // días compactos
                }
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 1))->setWidth(9.0);   // P días
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 2))->setWidth(12.5);  // P S/
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 3))->setWidth(9.0);   // D días
                $ws->getColumnDimension($this->colLetter($dayEndColIndex + 4))->setWidth(12.5);  // D S/

                // ===== Formatos columnas de montos =====
                if ($lastDataRow >= $dataStartRow) {
                    $paidAmtColL = $this->colLetter($dayEndColIndex + 2);
                    $debtAmtColL = $this->colLetter($dayEndColIndex + 4);
                    $ws->getStyle("{$paidAmtColL}{$dataStartRow}:{$paidAmtColL}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $ws->getStyle("{$debtAmtColL}{$dataStartRow}:{$debtAmtColL}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $ws->getStyle("{$paidAmtColL}{$dataStartRow}:{$paidAmtColL}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("{$debtAmtColL}{$dataStartRow}:{$debtAmtColL}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                }

                // ===== Fila TOTAL con bandas y fórmulas =====
                if ($lastDataRow >= $dataStartRow) {
                    $totalRow = $lastDataRow + 1;

                    // Etiqueta TOTAL
                    $ws->mergeCells("A{$totalRow}:D{$totalRow}");
                    $ws->setCellValue("A{$totalRow}", 'TOTAL');

                    // Columnas finales
                    $paidDaysColL = $this->colLetter($dayEndColIndex + 1);
                    $paidAmtColL  = $this->colLetter($dayEndColIndex + 2);
                    $debtDaysColL = $this->colLetter($dayEndColIndex + 3);
                    $debtAmtColL  = $this->colLetter($dayEndColIndex + 4);

                    // SUM() para cada totales
                    $ws->setCellValue("{$paidDaysColL}{$totalRow}", "=SUM({$paidDaysColL}{$dataStartRow}:{$paidDaysColL}{$lastDataRow})");
                    $ws->setCellValue("{$paidAmtColL}{$totalRow}",  "=SUM({$paidAmtColL}{$dataStartRow}:{$paidAmtColL}{$lastDataRow})");
                    $ws->setCellValue("{$debtDaysColL}{$totalRow}", "=SUM({$debtDaysColL}{$dataStartRow}:{$debtDaysColL}{$lastDataRow})");
                    $ws->setCellValue("{$debtAmtColL}{$totalRow}",  "=SUM({$debtAmtColL}{$dataStartRow}:{$debtAmtColL}{$lastDataRow})");

                    // Estilos de pie
                    $ws->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
                        'font'      => ['bold'=>true,'size'=>10,'color'=>['argb'=>$black]],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT,'vertical'=>Alignment::VERTICAL_CENTER],
                        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                    ]);
                    $ws->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")
                        ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)
                        ->getColor()->setARGB($blue);

                    // Formatos numéricos del pie
                    $ws->getStyle("{$paidAmtColL}{$totalRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("{$debtAmtColL}{$totalRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("{$paidDaysColL}{$totalRow}:{$debtDaysColL}{$totalRow}")
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
