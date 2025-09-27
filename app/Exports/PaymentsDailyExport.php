<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PaymentsDailyExport implements FromArray, WithHeadings, WithEvents, WithTitle
{
    protected int $year;
    protected int $month;
    protected string $mode; // 'Pago' | 'Caja'

    // datos
    protected int $daysInMonth = 30;
    protected array $rows = [];
    protected array $totalsPerDay = [];
    protected float $grandTotal = 0.0;
    protected int $sumDaysPaid = 0;
    protected int $sumDebtDays = 0;
    protected float $sumDebtAmount = 0.0;
    protected float $sumRealDebtAmount = 0.0;

    // tablas/columnas
    protected string $amountCol = 'amount';
    protected string $costTable = 'cost_per_plate_days';

    public function __construct(int $year, int $month, string $mode = 'Pago')
    {
        $this->year  = $year;
        $this->month = $month;
        $this->mode  = $mode;

        $this->build();
    }

    public function title(): string
    {
        return "Diario {$this->month}-{$this->year}";
    }

    public function headings(): array
    {
        $heads = ['Item', 'Placa', 'Cond.'];
        for ($d = 1; $d <= $this->daysInMonth; $d++) $heads[] = (string)$d;
        $heads[] = 'Total (S/)';
        $heads[] = 'Días Pag.';
        if ($this->mode === 'Pago') {
            $heads[] = 'Deuda (días)';
            $heads[] = 'Deuda (S/)';
            $heads[] = 'Deuda Real (S/)';
        }
        return $heads;
    }

    public function array(): array
    {
        $data = [];
        $i = 0;
        foreach ($this->rows as $row) {
            $i++;
            $line = [];
            $line[] = $i;
            $line[] = $row['plate'];
            $line[] = $row['cond'] ?: '-';

            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $line[] = (float)($row['days'][$d] ?? 0.0);
            }

            $line[] = (float)$row['total'];        // Total (S/)
            $line[] = (int)$row['days_paid'];      // Días Pag.

            if ($this->mode === 'Pago') {
                $line[] = (int)$row['debt_days'];          // Deuda (días)
                $line[] = (float)$row['debt_amount'];      // Deuda (S/)
                $line[] = (float)$row['real_debt_amount']; // Deuda Real (S/)
            }

            $data[] = $line;
        }

        // Footer (totales)
        $footer = ['Totales por día (S/)', '', ''];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $footer[] = (float)($this->totalsPerDay[$d] ?? 0.0);
        }
        $footer[] = (float)$this->grandTotal;
        $footer[] = (int)$this->sumDaysPaid;

        if ($this->mode === 'Pago') {
            $footer[] = (int)$this->sumDebtDays;
            $footer[] = (float)$this->sumDebtAmount;
            $footer[] = (float)$this->sumRealDebtAmount;
        }

        $data[] = $footer;

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // ====== cálculo de rango ======
                $lastColIndex = 3 + $this->daysInMonth + 1 + ($this->mode === 'Pago' ? 4 : 1);
                $lastCol = $this->col($lastColIndex);

                // ====== título (fila 1) ======
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', $this->titleText());
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => 'center'],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '23242F']],
                ]);

                // ====== header (fila 2) ======
                $headerRange = "A2:{$lastCol}2";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => 'center'],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '23242F']],
                ]);

                // Domingos en rojo en el header de días
                $baseDayCol = 4; // días empiezan en col 4 (A=1,B=2,C=3)
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    $date = CarbonImmutable::create($this->year, $this->month, $d);
                    if ($date->isSunday()) {
                        $col = $this->col($baseDayCol + ($d - 1));
                        $sheet->getStyle("{$col}2")->applyFromArray([
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DC3545']],
                            'font' => ['color' => ['rgb' => 'FFFFFF']],
                        ]);
                    }
                }

                // congelar encabezado + 3 primeras columnas
                $sheet->freezePane('D3');

                // bordes finos
                $highestRow = (int)$sheet->getHighestRow();
                $tableRange = "A2:{$lastCol}{$highestRow}";
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ]);

                // ====== filas/rangos útiles ======
                $firstDataRow = 3;               // 1 título + 1 header
                $lastDataRow  = $highestRow;     // incluye footer
                $dataLastRow  = $lastDataRow - 1;// excluye footer

                // ====== formatos ======
                // DÍAS: mostrar “–” cuando sea cero
                $dayStart = 4;
                $dayEnd   = 3 + $this->daysInMonth;
                for ($i = $dayStart; $i <= $dayEnd; $i++) {
                    $col = $this->col($i);
                    $sheet->getStyle("{$col}{$firstDataRow}:{$col}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00;#,##0.00;"-"');
                }

                // Total (S/)
                $totalCol = $this->col($dayEnd + 1);
                $sheet->getStyle("{$totalCol}{$firstDataRow}:{$totalCol}{$lastDataRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // Días Pag.
                $daysPaidCol = $this->col($dayEnd + 2);
                $sheet->getStyle("{$daysPaidCol}{$firstDataRow}:{$daysPaidCol}{$lastDataRow}")
                    ->getNumberFormat()->setFormatCode('0');

                if ($this->mode === 'Pago') {
                    // Deuda (días)
                    $debtDaysCol = $this->col($dayEnd + 3);
                    $sheet->getStyle("{$debtDaysCol}{$firstDataRow}:{$debtDaysCol}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('0');
                    // Deuda (S/)
                    $debtAmtCol = $this->col($dayEnd + 4);
                    $sheet->getStyle("{$debtAmtCol}{$firstDataRow}:{$debtAmtCol}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    // Deuda Real (S/)
                    $realDebtCol = $this->col($dayEnd + 5);
                    $sheet->getStyle("{$realDebtCol}{$firstDataRow}:{$realDebtCol}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // alineaciones
                $sheet->getStyle("A{$firstDataRow}:C{$lastDataRow}")->getAlignment()->setHorizontal('left');
                $sheet->getStyle("A2:C2")->getAlignment()->setHorizontal('center');

                // ====== anchos compactos ======
                $setW = function(int $idx, float $w) use ($sheet) {
                    $sheet->getColumnDimension($this->col($idx))->setWidth($w);
                };
                $setW(1, 5);    // Item
                $setW(2, 12);   // Placa
                $setW(3, 7);    // Cond.
                for ($i = $dayStart; $i <= $dayEnd; $i++) $setW($i, 6.5); // días
                $setW($dayEnd + 1, 11); // Total (S/)
                $setW($dayEnd + 2, 9);  // Días Pag.
                if ($this->mode === 'Pago') {
                    $setW($dayEnd + 3, 9);  // Deuda (días)
                    $setW($dayEnd + 4, 11); // Deuda (S/)
                    $setW($dayEnd + 5, 12); // Deuda Real (S/)
                }

                // ====== COLOREADO CONDICIONAL (SÓLO DÍAS) ======
                // = 0  => rojo + blanco
                $condZero = new Conditional();
                $condZero->setConditionType(Conditional::CONDITION_CELLIS)
                    ->setOperatorType(Conditional::OPERATOR_EQUAL)
                    ->addCondition('0');
                $condZero->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EF4444');
                $condZero->getStyle()->getFont()->getColor()->setRGB('FFFFFF');

                // > 0 => verde + blanco
                $condGt = new Conditional();
                $condGt->setConditionType(Conditional::CONDITION_CELLIS)
                    ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
                    ->addCondition('0');
                $condGt->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('22C55E');
                $condGt->getStyle()->getFont()->getColor()->setRGB('FFFFFF');

                // aplicar a días (filas de datos, sin footer)
                $daysRange = $this->col($dayStart) . $firstDataRow . ':' . $this->col($dayEnd) . $dataLastRow;
                $sheet->getStyle($daysRange)->setConditionalStyles([$condZero, $condGt]);

                // ====== GRIS SUAVE para columnas resumen (filas de datos) ======
                $grey = [
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F1F5F9']],
                    'font' => ['bold' => true],
                ];
                // Total (S/)
                $sheet->getStyle("{$totalCol}{$firstDataRow}:{$totalCol}{$dataLastRow}")
                    ->applyFromArray($grey);
                // Días Pag.
                $sheet->getStyle("{$daysPaidCol}{$firstDataRow}:{$daysPaidCol}{$dataLastRow}")
                    ->applyFromArray($grey);

                if ($this->mode === 'Pago') {
                    // Deuda (días)
                    $debtDaysCol = $this->col($dayEnd + 3);
                    $sheet->getStyle("{$debtDaysCol}{$firstDataRow}:{$debtDaysCol}{$dataLastRow}")
                        ->applyFromArray($grey);
                    // Deuda (S/)  **(añadido ahora en gris)**
                    $debtAmtCol = $this->col($dayEnd + 4);
                    $sheet->getStyle("{$debtAmtCol}{$firstDataRow}:{$debtAmtCol}{$dataLastRow}")
                        ->applyFromArray($grey);
                    // Deuda Real (S/)
                    $realDebtCol = $this->col($dayEnd + 5);
                    $sheet->getStyle("{$realDebtCol}{$firstDataRow}:{$realDebtCol}{$dataLastRow}")
                        ->applyFromArray($grey);
                }

                // ====== FOOTER igual que THEAD (oscuro) ======
                $footerRow = $lastDataRow; // última fila (totales)
                $sheet->getStyle("A{$footerRow}:{$lastCol}{$footerRow}")
                    ->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '23242F']],
                    ]);
            },
        ];
    }

    /* ======================= DATA BUILDER ======================= */
    protected function build(): void
    {
        $this->rows = [];
        $this->totalsPerDay = [];
        $this->grandTotal = 0;
        $this->sumDaysPaid = 0;
        $this->sumDebtDays = 0;
        $this->sumDebtAmount = 0.0;
        $this->sumRealDebtAmount = 0.0;

        if (!Schema::hasTable('vehicles') || !Schema::hasTable('payments')) return;

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();
        $this->daysInMonth = (int) $start->daysInMonth;

        // Vehículos activos
        $orderCol = Schema::hasColumn('vehicles','sort_order')
            ? 'sort_order'
            : (Schema::hasColumn('vehicles','plate') ? 'plate' : 'id');

        $vehCols = ['id','plate','status'];
        if (Schema::hasColumn('vehicles','sort_order')) $vehCols[] = 'sort_order';
        if (Schema::hasColumn('vehicles','condition'))  $vehCols[] = 'condition';

        $vehicles = DB::table('vehicles')
            ->where('status', 'active')
            ->select($vehCols)
            ->orderBy($orderCol)
            ->get();

        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'order'     => (string)($v->sort_order ?? ''),
                'plate'     => (string)$v->plate,
                'cond'      => (string)($v->condition ?? ''),
                'days'      => array_fill(1, $this->daysInMonth, 0.0),
                'total'     => 0.0,
                'days_paid' => 0,
                'debt_days' => 0,
                'debt_amount' => 0.0,
                'real_debt_amount' => 0.0,
            ];
        }

        $amountCol = $this->amountCol;

        // Importes por día
        if ($this->mode === 'Pago') {
            $dateCol = 'date_payment';
            $aggs = DB::table('payments as p')
                ->leftJoin('vehicles as v2', function ($join) {
                    $join->on('v2.plate', '=', 'p.legacy_plate')
                        ->where('v2.status', 'active');
                })
                ->selectRaw("
                    COALESCE(p.vehicle_id, v2.id) as vid,
                    DAY($dateCol) as d,
                    SUM(p.$amountCol) as s
                ")
                ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
                ->whereNotNull($dateCol)
                ->whereBetween($dateCol, [$start->toDateString(), $end->toDateString()])
                ->groupBy('vid', 'd')
                ->get();
        } else {
            $dateCol = 'date_register';
            $aggs = DB::table('payments as p')
                ->leftJoin('vehicles as v2', function ($join) {
                    $join->on('v2.plate', '=', 'p.legacy_plate')
                        ->where('v2.status', 'active');
                })
                ->selectRaw("
                    COALESCE(p.vehicle_id, v2.id) as vid,
                    DAY($dateCol) as d,
                    SUM(p.$amountCol) as s
                ")
                ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO','DEUDA'])
                ->whereNotNull($dateCol)
                ->whereBetween($dateCol, [$start->toDateString(), $end->toDateString()])
                ->groupBy('vid', 'd')
                ->get();
        }

        foreach ($aggs as $r) {
            $vid = (int) $r->vid;
            $d   = (int) $r->d;
            $s   = (float) $r->s;
            if (!isset($this->rows[$vid])) continue;
            if ($d < 1 || $d > $this->daysInMonth) continue;
            $this->rows[$vid]['days'][$d] = $s;
        }

        // Días pagados (PAGO/RETRASO) sin domingos
        $paidDateCol = $this->mode === 'Pago' ? 'date_payment' : 'date_register';
        $paidDaysAgg = DB::table('payments as p')
            ->leftJoin('vehicles as v2', function ($join) {
                $join->on('v2.plate', '=', 'p.legacy_plate')
                    ->where('v2.status', 'active');
            })
            ->selectRaw("COALESCE(p.vehicle_id, v2.id) as vid, DAY($paidDateCol) as d")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull($paidDateCol)
            ->whereBetween($paidDateCol, [$start->toDateString(), $end->toDateString()])
            ->whereRaw("DAYOFWEEK($paidDateCol) <> 1")
            ->groupBy('vid','d')
            ->get();

        $paidDaysByVehicle = [];
        foreach ($paidDaysAgg as $p) {
            $paidDaysByVehicle[(int)$p->vid][(int)$p->d] = true;
        }

        // Suma de pagos del mes (PAGO/RETRASO), sin excluir domingos
        $paidSumAgg = DB::table('payments as p')
            ->leftJoin('vehicles as v2', function ($join) {
                $join->on('v2.plate', '=', 'p.legacy_plate')
                    ->where('v2.status', 'active');
            })
            ->selectRaw("COALESCE(p.vehicle_id, v2.id) as vid, SUM(p.$amountCol) as s")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull($paidDateCol)
            ->whereBetween($paidDateCol, [$start->toDateString(), $end->toDateString()])
            ->groupBy('vid')
            ->get();

        $paidSumByVehicle = [];
        foreach ($paidSumAgg as $pa) {
            $paidSumByVehicle[(int)$pa->vid] = (float)$pa->s;
        }

        // Costos por día (sin domingos)
        $costsByVehicle = [];
        if (Schema::hasTable($this->costTable)) {
            $costs = DB::table($this->costTable)
                ->selectRaw("vehicle_id, DAY(`date`) as d, SUM(amount) as a")
                ->where('year', $this->year)
                ->where('month', $this->month)
                ->whereRaw("DAYOFWEEK(`date`) <> 1")
                ->groupBy('vehicle_id','d')
                ->get();

            foreach ($costs as $c) {
                $costsByVehicle[(int)$c->vehicle_id][(int)$c->d] = (float)$c->a;
            }
        }

        // Totales por día
        for ($d=1; $d <= $this->daysInMonth; $d++) $this->totalsPerDay[$d] = 0.0;

        foreach ($this->rows as $vid => &$row) {
            $row['total'] = array_sum($row['days']);

            $cond = strtoupper(trim($row['cond'] ?? ''));
            $isEx = str_starts_with($cond, 'EX');
            $isDt = ($cond === 'DT');
            $isGn = ($cond === 'GN');

            $row['days_paid'] = isset($paidDaysByVehicle[$vid]) ? count($paidDaysByVehicle[$vid]) : 0;

            // Total Deuda (no pagados, sin domingos) - EX=0
            $debtDays   = 0;
            $debtAmount = 0.0;
            if (isset($costsByVehicle[$vid])) {
                foreach ($costsByVehicle[$vid] as $d => $amt) {
                    $isPaid = isset($paidDaysByVehicle[$vid][$d]);
                    if (!$isPaid) {
                        $debtDays++;
                        $debtAmount += (float)$amt;
                    }
                }
            }
            if ($isEx) {
                $row['debt_days']   = 0;
                $row['debt_amount'] = 0.0;
            } else {
                $row['debt_days']   = $debtDays;
                $row['debt_amount'] = round($debtAmount, 2);
            }

            // suma de pagos del mes (PAGO/RETRASO)
            $paidSum = $paidSumByVehicle[$vid] ?? 0.0;

            // Deuda REAL
            if ($isEx) {
                $row['real_debt_amount'] = 0.0;
            } elseif ($isGn) {
                $row['real_debt_amount'] = round(max(0.0, ($row['total'] + $row['debt_amount']) - $paidSum), 2);
            } elseif ($isDt) {
                $row['real_debt_amount'] = round(max(0.0, $row['total'] - $paidSum), 2);
            } else {
                $row['real_debt_amount'] = round(max(0.0, $row['debt_amount'] - $paidSum), 2);
            }

            $this->sumDaysPaid       += (int)$row['days_paid'];
            $this->sumDebtDays       += (int)$row['debt_days'];
            $this->sumDebtAmount     += (float)$row['debt_amount'];
            $this->sumRealDebtAmount += (float)$row['real_debt_amount'];

            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $this->totalsPerDay[$d] += (float)$row['days'][$d];
            }
            $this->grandTotal += (float)$row['total'];
        }
        unset($row);
    }

    /* ======================= helpers ======================= */

    protected function titleText(): string
    {
        $mes = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ][$this->month] ?? (string)$this->month;

        $modo = ($this->mode === 'Pago')
            ? 'REPORTE DIARIO DE PAGO'
            : 'REPORTE DIARIO DE CAJA';

        return "{$modo} – {$mes} {$this->year}";
    }

    // 1->A, 2->B, ...
    protected function col(int $index): string
    {
        $index--; $str = '';
        while ($index >= 0) {
            $str = chr($index % 26 + 65) . $str;
            $index = intdiv($index, 26) - 1;
        }
        return $str;
    }
}
