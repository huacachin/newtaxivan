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
    protected int $sumRealDebtDays = 0;
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
        $heads = ['Nº', 'Placa', 'Cond.'];
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
        // Pre-calculate sundays
        $sundays = [];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $sundays[$d] = CarbonImmutable::create($this->year, $this->month, $d)->isSunday();
        }

        $data = [];
        $i = 0;
        foreach ($this->rows as $row) {
            $i++;
            $line = [];
            $line[] = $i;
            $line[] = $row['plate'];
            $line[] = $row['cond'] ?: '-';

            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $val = (float)($row['days'][$d] ?? 0.0);
                // Domingo o valor 0 → celda vacía
                $line[] = ($sundays[$d] || $val == 0) ? '' : $val;
            }

            $line[] = (float)$row['total'];
            $line[] = (int)$row['days_paid'];

            if ($this->mode === 'Pago') {
                $line[] = (int)$row['debt_days'];
                $line[] = (float)$row['debt_amount'];
                $line[] = (float)$row['real_debt_amount'];
            }

            $data[] = $line;
        }

        // Footer (totales)
        $footer = ['', '', 'Total'];
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
                $lastColIndex = 3 + $this->daysInMonth + 2 + ($this->mode === 'Pago' ? 3 : 0);
                $lastCol      = $this->col($lastColIndex);

                // ====== PALETA ======
                $blueDark   = 'FF2874A6';
                $fontWhite  = 'FFFFFFFF';
                $borderSoft = '000000';
                $greenFill  = 'FF008000';
                $redFill    = 'FFFF0000';
                $whiteFill  = 'FFFFFFFF';
                $greyFill   = 'FFD0CECE';

                // Pre-calculate sundays
                $sundays = [];
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    $sundays[$d] = CarbonImmutable::create($this->year, $this->month, $d)->isSunday();
                }

                // Fuente compacta 10pt (global)
                $sheet->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // ====== título (fila 1) ======
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', $this->titleText());
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $redFill]],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(18);

                // ====== header (fila 2) ======
                $headerRange = "A2:{$lastCol}2";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $fontWhite]],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => $blueDark]],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(18);

                // Domingos en rojo en el header
                $baseDayCol = 4;
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    if ($sundays[$d]) {
                        $col = $this->col($baseDayCol + ($d - 1));
                        $sheet->getStyle("{$col}2")->applyFromArray([
                            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => $redFill]],
                            'font' => ['color' => ['argb' => $fontWhite], 'bold' => true],
                        ]);
                    }
                }

                // congelar encabezado + 3 primeras columnas
                // $sheet->freezePane(...); // removido

                // bordes finos en toda la tabla
                $highestRow = (int)$sheet->getHighestRow();
                $tableRange = "A2:{$lastCol}{$highestRow}";
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => $borderSoft],
                        ],
                    ],
                ]);

                // ====== filas/rangos útiles ======
                $firstDataRow = 3;
                $lastDataRow  = $highestRow;
                $dataLastRow  = $lastDataRow - 1; // excluye footer

                $dayStart = 4;
                $dayEnd   = 3 + $this->daysInMonth;

                // ====== alineación centrada en datos ======
                $sheet->getStyle("A{$firstDataRow}:{$lastCol}{$dataLastRow}")
                    ->getAlignment()->setHorizontal('center')->setVertical('center');

                // ====== COLOREADO DIRECTO POR CELDA (días) ======
                $styleGreen = [
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $greenFill]],
                    'font' => ['color' => ['argb' => $fontWhite]],
                ];
                $styleRed = [
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $redFill]],
                    'font' => ['color' => ['argb' => $fontWhite]],
                ];
                $styleSunday = [
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $whiteFill]],
                ];

                for ($r = $firstDataRow; $r <= $dataLastRow; $r++) {
                    for ($d = 1; $d <= $this->daysInMonth; $d++) {
                        $col  = $this->col($baseDayCol + ($d - 1));
                        $cell = "{$col}{$r}";
                        if ($sundays[$d]) {
                            $sheet->getStyle($cell)->applyFromArray($styleSunday);
                        } else {
                            $val = $sheet->getCell($cell)->getValue();
                            if ($val !== null && $val !== '' && (float)$val > 0) {
                                $sheet->getStyle($cell)->applyFromArray($styleGreen);
                            } else {
                                $sheet->getStyle($cell)->applyFromArray($styleRed);
                            }
                        }
                    }
                }

                // ====== formatos numéricos ======
                // Días con valor: mostrar con 2 decimales
                for ($i = $dayStart; $i <= $dayEnd; $i++) {
                    $col = $this->col($i);
                    $sheet->getStyle("{$col}{$firstDataRow}:{$col}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                $totalCol    = $this->col($dayEnd + 1);
                $daysPaidCol = $this->col($dayEnd + 2);

                $sheet->getStyle("{$totalCol}{$firstDataRow}:{$totalCol}{$lastDataRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$daysPaidCol}{$firstDataRow}:{$daysPaidCol}{$lastDataRow}")
                    ->getNumberFormat()->setFormatCode('0');

                if ($this->mode === 'Pago') {
                    $debtDaysCol = $this->col($dayEnd + 3);
                    $debtAmtCol  = $this->col($dayEnd + 4);
                    $realDebtCol = $this->col($dayEnd + 5);
                    $sheet->getStyle("{$debtDaysCol}{$firstDataRow}:{$debtDaysCol}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('0');
                    $sheet->getStyle("{$debtAmtCol}{$firstDataRow}:{$debtAmtCol}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("{$realDebtCol}{$firstDataRow}:{$realDebtCol}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // ====== GRIS #D0CECE para columnas resumen (filas de datos) ======
                $summaryStyle = [
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $greyFill]],
                ];
                $sheet->getStyle("{$totalCol}{$firstDataRow}:{$totalCol}{$dataLastRow}")
                    ->applyFromArray($summaryStyle);
                $sheet->getStyle("{$daysPaidCol}{$firstDataRow}:{$daysPaidCol}{$dataLastRow}")
                    ->applyFromArray($summaryStyle);

                if ($this->mode === 'Pago') {
                    $debtDaysCol = $this->col($dayEnd + 3);
                    $debtAmtCol  = $this->col($dayEnd + 4);
                    $realDebtCol = $this->col($dayEnd + 5);
                    $sheet->getStyle("{$debtDaysCol}{$firstDataRow}:{$debtDaysCol}{$dataLastRow}")
                        ->applyFromArray($summaryStyle);
                    $sheet->getStyle("{$debtAmtCol}{$firstDataRow}:{$debtAmtCol}{$dataLastRow}")
                        ->applyFromArray($summaryStyle);
                    $sheet->getStyle("{$realDebtCol}{$firstDataRow}:{$realDebtCol}{$dataLastRow}")
                        ->applyFromArray($summaryStyle);
                }

                // ====== anchos ======
                $setW = function(int $idx, float $w) use ($sheet) {
                    $sheet->getColumnDimension($this->col($idx))->setAutoSize(false);
                    $sheet->getColumnDimension($this->col($idx))->setWidth($w);
                };
                $setW(1, 5.5);   // Item
                $setW(2, 9);     // Placa
                $setW(3, 7);     // Cond.

                // Días y resumen: autoSize para evitar ###
                for ($i = $dayStart; $i <= $lastColIndex; $i++) {
                    $sheet->getColumnDimension($this->col($i))->setAutoSize(true);
                }

                // ====== FOOTER ======
                $footerRow = $lastDataRow + 1;
                $sheet->getStyle("A{$footerRow}:{$lastCol}{$footerRow}")
                    ->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                    ]);
                $sheet->getStyle($this->col($dayStart)."{$footerRow}:".$this->col($dayEnd)."{$footerRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$totalCol}{$footerRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$daysPaidCol}{$footerRow}")->getNumberFormat()->setFormatCode('0');
                if ($this->mode === 'Pago') {
                    $debtDaysCol = $this->col($dayEnd + 3);
                    $debtAmtCol  = $this->col($dayEnd + 4);
                    $realDebtCol = $this->col($dayEnd + 5);
                    $sheet->getStyle("{$debtDaysCol}{$footerRow}")->getNumberFormat()->setFormatCode('0');
                    $sheet->getStyle("{$debtAmtCol}{$footerRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("{$realDebtCol}{$footerRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
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
        $this->sumRealDebtDays = 0;
        $this->sumRealDebtAmount = 0.0;

        if (!Schema::hasTable('vehicles') || !Schema::hasTable('payments')) return;

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();
        $this->daysInMonth = (int) $start->daysInMonth;
        $startStr = $start->toDateString();
        $endStr   = $end->toDateString();

        // Vehículos activos (misma lógica que la vista)
        $vehicles = DB::table('vehicles')
            ->where('status', 'active')
            ->select(['id', 'plate', 'sort_order', 'condition'])
            ->orderBy('sort_order')
            ->get();

        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'order'            => (string)($v->sort_order ?? ''),
                'plate'            => (string)$v->plate,
                'cond'             => (string)($v->condition ?? ''),
                'days'             => array_fill(1, $this->daysInMonth, 0.0),
                'total'            => 0.0,
                'days_paid'        => 0,
                'debt_days'        => 0,
                'debt_amount'      => 0.0,
                'real_debt_days'   => 0,
                'real_debt_amount' => 0.0,
            ];
        }

        $dateCol    = $this->mode === 'Pago' ? 'date_payment' : 'date_register';
        $typeFilter = $this->mode === 'Pago' ? ['PAGO','RETRASO'] : ['PAGO','RETRASO','DEUDA'];

        // Importes por día (misma query que la vista)
        $aggs = DB::table('payments')
            ->selectRaw("vehicle_id as vid, DAY($dateCol) as d, SUM(amount) as s")
            ->whereIn(DB::raw('UPPER(type)'), $typeFilter)
            ->whereNotNull($dateCol)
            ->whereBetween($dateCol, [$startStr, $endStr])
            ->groupBy('vid', 'd')
            ->get();

        foreach ($aggs as $r) {
            $vid = (int) $r->vid;
            $day = (int) $r->d;
            if (!isset($this->rows[$vid]) || $day < 1 || $day > $this->daysInMonth) continue;
            $this->rows[$vid]['days'][$day] = (float) $r->s;
        }

        // Total Pagos: count registros + sum monto
        $paidTotals = DB::table('payments')
            ->selectRaw("vehicle_id as vid, COUNT(*) as kt, SUM(amount) as montox")
            ->whereIn(DB::raw('UPPER(type)'), ['PAGO','RETRASO'])
            ->whereNotNull($dateCol)
            ->whereBetween($dateCol, [$startStr, $endStr])
            ->groupBy('vid')
            ->get();

        $paidCountByVehicle = [];
        $paidSumByVehicle = [];
        foreach ($paidTotals as $pt) {
            $vid = (int) $pt->vid;
            $paidCountByVehicle[$vid] = (int) $pt->kt;
            $paidSumByVehicle[$vid] = (float) $pt->montox;
        }

        // Costos por vehículo (sin domingos)
        $costTotals = DB::table('cost_per_plate_days')
            ->selectRaw("vehicle_id, COUNT(*) as dias, SUM(amount) as total_costo")
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->whereRaw("DAYOFWEEK(`date`) <> 1")
            ->groupBy('vehicle_id')
            ->get();

        $costDaysByVehicle = [];
        $costSumByVehicle = [];
        foreach ($costTotals as $ct) {
            $vid = (int) $ct->vehicle_id;
            $costDaysByVehicle[$vid] = (int) $ct->dias;
            $costSumByVehicle[$vid] = (float) $ct->total_costo;
        }

        // Deuda Real DT: días con salidas (excluyendo Huachipa/lima, sin domingos)
        $dtDepartures = DB::table('departures as d')
            ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->selectRaw("d.vehicle_id as vid, COUNT(DISTINCT DATE(d.date)) as dias_trab,
                         (SELECT SUM(cpd.amount) FROM cost_per_plate_days cpd
                          WHERE cpd.vehicle_id = d.vehicle_id
                          AND cpd.year = ? AND cpd.month = ?
                          AND DAYOFWEEK(cpd.date) <> 1
                          AND cpd.date IN (SELECT DISTINCT DATE(d2.date) FROM departures d2
                              LEFT JOIN headquarters h2 ON h2.id = d2.headquarter_id
                              WHERE d2.vehicle_id = d.vehicle_id
                              AND DATE(d2.date) BETWEEN ? AND ?
                              AND (h2.name IS NULL OR h2.name NOT IN ('Huachipa','lima'))
                              AND DAYOFWEEK(d2.date) <> 1)
                         ) as monto_pen", [$this->year, $this->month, $startStr, $endStr])
            ->whereBetween(DB::raw('DATE(d.date)'), [$startStr, $endStr])
            ->where(function($q) {
                $q->whereNull('h.name')->orWhereNotIn('h.name', ['Huachipa','lima']);
            })
            ->whereRaw('DAYOFWEEK(d.date) <> 1')
            ->groupBy('d.vehicle_id')
            ->get();

        $dtDataByVehicle = [];
        foreach ($dtDepartures as $dt) {
            $dtDataByVehicle[(int)$dt->vid] = [
                'dias_trab' => (int) $dt->dias_trab,
                'monto_pen' => (float) ($dt->monto_pen ?? 0),
            ];
        }

        // Totales por fila (misma lógica que la vista)
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $this->totalsPerDay[$d] = 0;
        }

        foreach ($this->rows as $vid => &$row) {
            $row['total'] = array_sum($row['days']);

            $cond  = strtoupper(trim($row['cond'] ?? ''));
            $isEx  = str_starts_with($cond, 'EX');
            $isEx5 = ($cond === 'EX5');
            $isDt  = ($cond === 'DT');
            $isGn  = ($cond === 'GN');

            $kt     = $paidCountByVehicle[$vid] ?? 0;
            $montox = $paidSumByVehicle[$vid] ?? 0.0;

            $row['days_paid'] = $kt;

            // Total Deuda
            $costDias = $costDaysByVehicle[$vid] ?? 0;
            $costSum  = $costSumByVehicle[$vid] ?? 0.0;
            $costUnit = $costDias > 0 ? round($costSum / $costDias, 2) : 10.0;

            if ($isEx && !$isEx5) {
                $row['debt_days']   = 0;
                $row['debt_amount'] = 0.0;
            } else {
                $debtDays = $costDias > 0 ? round(($costSum - $montox) / $costUnit, 0) : 0;
                $debtAmount = round($debtDays * $costUnit, 2);
                if ($debtDays < 0) { $debtDays = 0; $debtAmount = 0.0; }
                $row['debt_days']   = (int) $debtDays;
                $row['debt_amount'] = $debtAmount;
            }

            // Deuda Real
            $realDays = 0;
            $realAmount = 0.0;

            if ($isEx && !$isEx5) {
                $realDays = 0;
                $realAmount = 0.0;
            } elseif ($isEx5) {
                $debtRaw = $costDias > 0 ? ($costSum - $montox) / $costUnit : 0;
                if ($debtRaw > 5) {
                    $realDays = (int) round($debtRaw - 5, 0);
                    $realAmount = round($realDays * $costUnit, 2);
                }
            } elseif ($isDt) {
                $dtData = $dtDataByVehicle[$vid] ?? ['dias_trab' => 0, 'monto_pen' => 0];
                $realDays = max(0, $dtData['dias_trab'] - $kt);
                $realAmount = max(0, round($dtData['monto_pen'] - $montox, 2));
            } elseif ($isGn) {
                $realDays = max(0, $costDias - $kt);
                $realAmount = max(0, round($costSum - $montox, 2));
            } else {
                $realDays = (int) $row['debt_days'];
                $realAmount = $row['debt_amount'];
            }

            $row['real_debt_days']   = (int) $realDays;
            $row['real_debt_amount'] = round($realAmount, 2);

            $this->sumDaysPaid       += (int)$row['days_paid'];
            $this->sumDebtDays       += (int)$row['debt_days'];
            $this->sumDebtAmount     += (float)$row['debt_amount'];
            $this->sumRealDebtDays   += (int)$row['real_debt_days'];
            $this->sumRealDebtAmount += (float)$row['real_debt_amount'];

            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $this->totalsPerDay[$d] += (float)$row['days'][$d];
            }
            $this->grandTotal += (float)$row['total'];
        }
        unset($row);

        // ===== Pagos sin vehicle_id (legacy_plate) =====
        $dateCol = $this->mode === 'Pago' ? 'date_payment' : 'date_register';
        $typeFilter = $this->mode === 'Pago' ? ['PAGO','RETRASO'] : ['PAGO','RETRASO','DEUDA'];

        $legacyAggs = DB::table('payments')
            ->selectRaw("legacy_plate as plate, DAY($dateCol) as d, SUM(amount) as s")
            ->whereIn(DB::raw('UPPER(type)'), $typeFilter)
            ->whereNotNull($dateCol)
            ->whereBetween($dateCol, [$startStr, $endStr])
            ->whereNull('vehicle_id')
            ->whereNotNull('legacy_plate')
            ->where('legacy_plate', '!=', '')
            ->groupBy('plate', 'd')
            ->get();

        $legacyPlates = [];
        foreach ($legacyAggs as $r) {
            $plate = strtoupper(trim($r->plate));
            $day   = (int) $r->d;
            if (!isset($legacyPlates[$plate])) {
                $legacyPlates[$plate] = [
                    'order'           => '',
                    'plate'           => $plate,
                    'cond'            => '',
                    'days'            => array_fill(1, $this->daysInMonth, 0.0),
                    'total'           => 0.0,
                    'days_paid'       => 0,
                    'debt_days'       => 0,
                    'debt_amount'     => 0.0,
                    'real_debt_days'  => 0,
                    'real_debt_amount'=> 0.0,
                    'is_legacy'       => true,
                ];
            }
            if ($day >= 1 && $day <= $this->daysInMonth) {
                $legacyPlates[$plate]['days'][$day] = (float) $r->s;
            }
        }

        $legacyPaidCounts = DB::table('payments')
            ->selectRaw("legacy_plate as plate, COUNT(*) as kt")
            ->whereIn(DB::raw('UPPER(type)'), ['PAGO','RETRASO'])
            ->whereNotNull($dateCol)
            ->whereBetween($dateCol, [$startStr, $endStr])
            ->whereNull('vehicle_id')
            ->whereNotNull('legacy_plate')
            ->where('legacy_plate', '!=', '')
            ->groupBy('plate')
            ->pluck('kt', 'plate');

        foreach ($legacyPlates as $plate => &$row) {
            $row['total']     = array_sum($row['days']);
            $row['days_paid'] = (int) ($legacyPaidCounts[strtoupper(trim($plate))] ?? 0);

            $this->sumDaysPaid += (int)$row['days_paid'];

            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $this->totalsPerDay[$d] += (float)$row['days'][$d];
            }
            $this->grandTotal += (float)$row['total'];
        }
        unset($row);

        $negKey = -1;
        foreach ($legacyPlates as $plate => $row) {
            $this->rows[$negKey] = $row;
            $negKey--;
        }
    }

    /* ======================= helpers ======================= */

    public function htmlData(): array
    {
        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $sundays = [];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $sundays[$d] = $start->day($d)->isSunday();
        }

        $months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

        return [
            'year'              => $this->year,
            'monthName'         => $months[$this->month] ?? '',
            'daysInMonth'       => $this->daysInMonth,
            'sundays'           => $sundays,
            'mode'              => $this->mode,
            'rows'              => $this->rows,
            'totalsPerDay'      => $this->totalsPerDay,
            'grandTotal'        => $this->grandTotal,
            'sumDaysPaid'       => $this->sumDaysPaid,
            'sumDebtDays'       => $this->sumDebtDays,
            'sumDebtAmount'     => $this->sumDebtAmount,
            'sumRealDebtDays'   => $this->sumRealDebtDays,
            'sumRealDebtAmount' => $this->sumRealDebtAmount,
        ];
    }

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
