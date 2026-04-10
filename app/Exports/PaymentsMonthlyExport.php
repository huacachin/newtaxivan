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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PaymentsMonthlyExport implements FromArray, WithHeadings, WithEvents, WithTitle
{
    protected int $year;
    protected int $month;
    protected ?string $cond;

    protected array $rows = [];
    protected array $footer1 = [];
    protected array $footer2 = [];
    protected array $legacyRowIndexes = [];

    protected string $paymentsTable = 'payments';
    protected string $vehiclesTable = 'vehicles';
    protected string $costTable     = 'cost_per_plate_days';
    protected string $debtDaysTable = 'debt_days';

    public function __construct(int $year, int $month, ?string $cond = null)
    {
        $this->year  = $year;
        $this->month = $month;
        $this->cond  = $cond ?: null;
        $this->build();
    }

    public function title(): string
    {
        return sprintf('Pago %02d-%d', $this->month, $this->year);
    }

    public function headings(): array
    {
        return [
            'Nº','Cod','Placa',
            'Deuda','Exo.','P.Deuda',
            sprintf('%02d/%d', $this->month, $this->year),
            'Lab.','DT','DNT','Condición','T.Deuda',
        ];
    }

    public function array(): array
    {
        return array_merge($this->rows, [$this->footer1, $this->footer2]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                // Paleta
                $blue      = 'FF2874A6';
                $footerBg  = 'FFCEE7FF';
                $white     = 'FFFFFFFF';
                $red       = 'F80000';
                $borderC   = '000000';

                // Tipografía global 10pt
                $s->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // ===== Layout (insertamos 2 filas: Título y Cabecera de grupos) =====
                $s->insertNewRowBefore(1, 2);   // headings() se mueve a la fila 3
                $lastCol  = 'L';                // A..L
                $titleRow = 1;
                $groupRow = 2;
                $headRow  = 3;
                $dataRow1 = 4;

                // Título
                $s->mergeCells("A{$titleRow}:{$lastCol}{$titleRow}");
                $s->setCellValue("A{$titleRow}", 'REPORTE MENSUAL DE PAGO '.mb_strtoupper($this->mesTexto($this->month)).' '.$this->year);
                $s->getStyle("A{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$red]],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => ['allBorders' => ['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FF000000']]],
                ]);
                $s->getRowDimension($titleRow)->setRowHeight(18);

                // Cabecera de grupos (fila 2)
                $s->setCellValue("A{$groupRow}", 'Nº');
                $s->setCellValue("B{$groupRow}", 'Cod');
                $s->setCellValue("C{$groupRow}", 'Placa');

                // Grupos D:F y H:K
                $s->mergeCells("D{$groupRow}:F{$groupRow}");
                $s->mergeCells("H{$groupRow}:K{$groupRow}");
                $s->setCellValue("D{$groupRow}", 'DEUDA DEL MES ANTERIOR');
                $s->setCellValue("H{$groupRow}", 'PAGOS');

                // Merge vertical para A,B,C: fila 2 y 3
                $s->mergeCells("A{$groupRow}:A{$headRow}");
                $s->mergeCells("B{$groupRow}:B{$headRow}");
                $s->mergeCells("C{$groupRow}:C{$headRow}");

                $s->getStyle("A{$groupRow}:{$lastCol}{$groupRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $s->getRowDimension($groupRow)->setRowHeight(16);

                // Encabezados originales (fila 3) — ya vienen de headings()
                $s->getStyle("A{$headRow}:{$lastCol}{$headRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Bordes: headers con thin, datos con dashed horizontal / thin vertical
                $lastRow = (int)$s->getHighestRow();
                $dataEnd = $lastRow - 2;

                // Headers (rows 2-3): thin borders
                $s->getStyle("A{$groupRow}:{$lastCol}{$headRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$groupRow}:{$lastCol}{$headRow}")
                    ->getBorders()->getAllBorders()->getColor()->setARGB($borderC);

                // Data rows: dashed horizontal, thin vertical/outline
                if ($dataEnd >= $dataRow1) {
                    $dataRange = "A{$dataRow1}:{$lastCol}{$dataEnd}";
                    $borders = $s->getStyle($dataRange)->getBorders();
                    $borders->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                    $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                    $borders->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                    $borders->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                    $borders->getHorizontal()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB($borderC);
                    $borders->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                }

                // ===== Anchos (compactos) =====
                foreach (range('A','L') as $col) {
                    $s->getColumnDimension($col)->setAutoSize(false);
                }
                $s->getColumnDimension('A')->setWidth(4.5);   // Item
                $s->getColumnDimension('B')->setWidth(4.5);   // Cod
                $s->getColumnDimension('C')->setWidth(12);    // Placa
                foreach (['D','E','F'] as $c) {
                    $s->getColumnDimension($c)->setWidth(10);  // Deuda ant./Exonerado/P.Deuda
                }
                $s->getColumnDimension('G')->setWidth(12);    // Mes actual
                foreach (['H','I','J'] as $c) {
                    $s->getColumnDimension($c)->setWidth(5);  // Lab./DT/DNT
                }
                $s->getColumnDimension('K')->setWidth(9);     // Condición
                $s->getColumnDimension('L')->setWidth(11);    // T.Deuda

                // ===== Alineaciones: TODO centrado (datos + pies) =====
                if ($dataEnd >= $dataRow1) {
                    // Centrar todo el bloque de datos y pies
                    $s->getStyle("A{$dataRow1}:{$lastCol}{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // ===== Formatos numéricos =====
                // Moneda: D,E,F,G,L
                foreach (['D','E','F','G','L'] as $c) {
                    $s->getStyle("{$c}{$dataRow1}:{$c}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }
                // Enteros: H,I,J y A (Item)
                foreach (['A','H','I','J'] as $c) {
                    $s->getStyle("{$c}{$dataRow1}:{$c}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('0');
                }

                // ===== Rellenar vacíos numéricos con 0 SOLO en filas de datos (NO pies) =====
                $numCols = ['A','D','E','F','G','H','I','J','L'];
                if ($dataEnd >= $dataRow1) {
                    for ($r = $dataRow1; $r <= $dataEnd; $r++) {
                        foreach ($numCols as $c) {
                            $cell = $s->getCell("{$c}{$r}");
                            $val  = $cell->getValue();
                            if ($val === null || $val === '') {
                                $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                            }
                        }
                    }
                }

                // ===== Cesados: placa en rojo negrita =====
                foreach ($this->legacyRowIndexes as $itemNum) {
                    $excelRow = $dataRow1 + $itemNum - 1;
                    $s->getStyle("C{$excelRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFF0000']],
                    ]);
                }

                // ===== Pies (últimas 2 filas) =====
                $footer1 = $lastRow - 1;
                $footer2 = $lastRow;
                foreach ([$footer1,$footer2] as $fr) {
                    $s->getStyle("A{$fr}:{$lastCol}{$fr}")->applyFromArray([
                        'fill' => [
                            'fillType'=>Fill::FILL_SOLID,
                            'startColor'=>['argb'=>$footerBg],
                        ],
                        'font' => [
                            'bold'=>true,
                            'color'=>['argb'=>'FF000000'],
                        ],
                        'borders' => [
                            'top' => [
                                'borderStyle'=>Border::BORDER_MEDIUM,
                                'color'=>['argb'=>'FF000000'],
                            ],
                            'bottom' => [
                                'borderStyle'=>Border::BORDER_MEDIUM,
                                'color'=>['argb'=>'FF000000'],
                            ],
                            'left' => [
                                'borderStyle'=>Border::BORDER_THIN,
                                'color'=>['argb'=>'FF000000'],
                            ],
                            'right' => [
                                'borderStyle'=>Border::BORDER_THIN,
                                'color'=>['argb'=>'FF000000'],
                            ],
                            'vertical' => [
                                'borderStyle'=>Border::BORDER_THIN,
                                'color'=>['argb'=>'FF000000'],
                            ],
                        ],
                        'alignment' => [
                            'horizontal'=>Alignment::HORIZONTAL_CENTER,
                            'vertical'  =>Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }
            },
        ];
    }

    /* ==================== DATA ==================== */

    protected function build(): void
    {
        if (!Schema::hasTable($this->vehiclesTable) || !Schema::hasTable($this->paymentsTable)) return;

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();
        $startStr = $start->toDateString();
        $endStr   = $end->toDateString();
        $laborables = $this->laborablesSinDomingo($start);

        // Vehículos activos (misma lógica que la vista)
        $vehCols = ['id','plate','status'];
        if (Schema::hasColumn($this->vehiclesTable,'sort_order')) $vehCols[] = 'sort_order';
        if (Schema::hasColumn($this->vehiclesTable,'condition'))  $vehCols[] = 'condition';

        $orderCol = Schema::hasColumn($this->vehiclesTable,'sort_order')
            ? 'sort_order'
            : (Schema::hasColumn($this->vehiclesTable,'plate') ? 'plate' : 'id');

        $vq = DB::table($this->vehiclesTable)->select($vehCols)->where('status','active');
        if ($this->cond) {
            $cf = strtoupper(trim($this->cond));
            $vq->where(function($q) use ($cf) {
                $q->where('condition', $cf)->orWhere('condition', 'like', $cf.'%');
            });
        }
        $vehicles = $vq->orderBy($orderCol)->get();
        if ($vehicles->isEmpty()) return;

        $map = [];
        foreach ($vehicles as $v) {
            $map[(int)$v->id] = [
                'order'           => (string)($v->sort_order ?? ''),
                'plate'           => (string)$v->plate,
                'condition'       => (string)($v->condition ?? ''),
                'prev_debt'       => 0.0,
                'prev_exonerated' => 0.0,
                'prev_paid_debt'  => 0.0,
                'month_amount'    => 0.0,
                'dt_days'         => 0,
                'dnt_days'        => 0,
                'tdebt'           => 0.0,
            ];
        }

        // Deuda mes anterior (debt_days) — misma lógica que la vista
        if (Schema::hasTable($this->debtDaysTable)) {
            $prev = $start->subMonth()->startOfMonth();
            $prevAgg = DB::table($this->debtDaysTable)
                ->selectRaw("
                    vehicle_id,
                    COALESCE(SUM(total),0)      as total_sum,
                    COALESCE(SUM(exonerated),0) as exo_sum,
                    COALESCE(SUM(amortized),0)  as amo_sum
                ")
                ->whereYear('date', $prev->year)
                ->whereMonth('date', $prev->month)
                ->groupBy('vehicle_id')
                ->get();

            foreach ($prevAgg as $r) {
                $vid = (int)$r->vehicle_id;
                if (!isset($map[$vid])) continue;
                $total = (float)$r->total_sum;
                $exo   = (float)$r->exo_sum;
                $amo   = (float)$r->amo_sum;

                $map[$vid]['prev_debt']       = max(0.0, round($total - $exo - $amo, 2));
                $map[$vid]['prev_exonerated'] = round($exo, 2);
                $map[$vid]['prev_paid_debt']  = round($amo, 2);
            }
        }

        // Pagos del mes (PAGO/RETRASO) — misma query que la vista (sin JOIN legacy)
        $paidAgg = DB::table($this->paymentsTable.' as p')
            ->selectRaw("
                p.vehicle_id as vid,
                SUM(p.amount) as s,
                COUNT(*) as kt
            ")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull('p.date_payment')
            ->whereBetween('p.date_payment', [$startStr, $endStr])
            ->where(DB::raw('UPPER(p.type)'), '<>', 'DEUDA')
            ->groupBy('vid')
            ->get();

        foreach ($paidAgg as $r) {
            $vid = (int) $r->vid;
            if (!isset($map[$vid])) continue;
            $map[$vid]['month_amount'] = round((float)$r->s, 2);
            $map[$vid]['dt_days']      = (int) $r->kt;
        }

        // DNT
        foreach ($map as $vid => &$row) {
            $row['dnt_days'] = max(0, $laborables - (int)$row['dt_days']);
        }
        unset($row);

        // T.Deuda — misma lógica que la vista

        // 1) Fechas con pago por vehículo
        $paidDatesByVid = [];
        $paidDatesRaw = DB::table($this->paymentsTable)
            ->selectRaw("vehicle_id as vid, DATE(date_payment) as pd")
            ->whereIn(DB::raw('UPPER(type)'), ['PAGO','RETRASO'])
            ->whereNotNull('date_payment')
            ->whereBetween('date_payment', [$startStr, $endStr])
            ->groupBy('vid', 'pd')
            ->get();
        foreach ($paidDatesRaw as $r) {
            $paidDatesByVid[(int)$r->vid][] = $r->pd;
        }

        // 2) Fechas con salidas por vehículo (para DT)
        $depDatesByVid = [];
        $depDatesRaw = DB::table('departures')
            ->selectRaw("vehicle_id as vid, DATE(date) as dd")
            ->whereBetween('date', [$startStr, $endStr])
            ->groupBy('vid', 'dd')
            ->get();
        foreach ($depDatesRaw as $r) {
            $depDatesByVid[(int)$r->vid][] = $r->dd;
        }

        // 3) Costos totales del mes por vehículo (sin domingos)
        $costTotalByVid = [];
        $costTotals = DB::table($this->costTable)
            ->selectRaw("vehicle_id, SUM(amount) as total_cost")
            ->whereYear('date', $start->year)
            ->whereMonth('date', $start->month)
            ->whereRaw("DAYOFWEEK(`date`) <> 1")
            ->groupBy('vehicle_id')
            ->get();
        foreach ($costTotals as $c) {
            $costTotalByVid[(int)$c->vehicle_id] = (float)$c->total_cost;
        }

        // 4) Aplicar reglas (misma lógica que la vista)
        foreach ($map as $vid => &$row) {
            $cond      = strtoupper(trim($row['condition'] ?? ''));
            $paidTotal = (float)$row['month_amount'];
            $kt        = (int)$row['dt_days'];

            if ($cond === 'EX') {
                $row['tdebt'] = 0.0;
            } elseif ($cond === 'DT') {
                $depDates = $depDatesByVid[$vid] ?? [];
                if (empty($depDates)) {
                    $row['tdebt'] = 0.0;
                } else {
                    $costOnDepDays = (float) DB::table($this->costTable)
                        ->where('vehicle_id', $vid)
                        ->whereYear('date', $start->year)
                        ->whereMonth('date', $start->month)
                        ->whereIn('date', $depDates)
                        ->whereRaw("DAYOFWEEK(`date`) <> 1")
                        ->sum('amount');
                    $row['tdebt'] = round(max(0.0, $costOnDepDays - $paidTotal), 2);
                }
            } elseif ($cond === 'GN') {
                $paidDates = $paidDatesByVid[$vid] ?? [];
                $q = DB::table($this->costTable)
                    ->where('vehicle_id', $vid)
                    ->whereYear('date', $start->year)
                    ->whereMonth('date', $start->month)
                    ->whereRaw("DAYOFWEEK(`date`) <> 1");
                if (!empty($paidDates)) {
                    $q->whereNotIn('date', $paidDates);
                }
                $row['tdebt'] = round((float) $q->sum('amount'), 2);
            } elseif ($cond === 'EX5') {
                $dnt = $laborables - $kt;
                if (($dnt - 5) <= 0) {
                    $row['tdebt'] = 0.0;
                } else {
                    $costTotal = $costTotalByVid[$vid] ?? 0.0;
                    $costDias = 0;
                    if (isset($costTotalByVid[$vid])) {
                        $costDias = (int) DB::table($this->costTable)
                            ->where('vehicle_id', $vid)
                            ->whereYear('date', $start->year)
                            ->whereMonth('date', $start->month)
                            ->whereRaw("DAYOFWEEK(`date`) <> 1")
                            ->count();
                    }
                    $costUnit = $costDias > 0 ? round($costTotal / $costDias, 2) : 0;
                    $row['tdebt'] = round(($dnt - 5) * $costUnit, 2);
                }
            } else {
                // Otros: misma lógica que GN
                $paidDates = $paidDatesByVid[$vid] ?? [];
                $q = DB::table($this->costTable)
                    ->where('vehicle_id', $vid)
                    ->whereYear('date', $start->year)
                    ->whereMonth('date', $start->month)
                    ->whereRaw("DAYOFWEEK(`date`) <> 1");
                if (!empty($paidDates)) {
                    $q->whereNotIn('date', $paidDates);
                }
                $row['tdebt'] = round((float) $q->sum('amount'), 2);
            }
        }
        unset($row);

        // --- Cesados: pagos con vehicle_id=NULL y legacy_plate ---
        $legacyAggs = DB::table($this->paymentsTable)
            ->selectRaw("legacy_plate as plate, SUM(amount) as s, COUNT(*) as kt")
            ->whereIn(DB::raw('UPPER(type)'), ['PAGO','RETRASO'])
            ->whereNotNull('date_payment')
            ->whereBetween('date_payment', [$startStr, $endStr])
            ->whereNull('vehicle_id')
            ->whereNotNull('legacy_plate')
            ->where('legacy_plate', '!=', '')
            ->groupBy('plate')
            ->get();

        $negKey = -1;
        foreach ($legacyAggs as $r) {
            $plate = strtoupper(trim($r->plate));
            $map[$negKey] = [
                'order'           => '',
                'plate'           => $plate,
                'condition'       => '',
                'prev_debt'       => 0.0,
                'prev_exonerated' => 0.0,
                'prev_paid_debt'  => 0.0,
                'month_amount'    => round((float)$r->s, 2),
                'dt_days'         => (int)$r->kt,
                'dnt_days'        => max(0, $laborables - (int)$r->kt),
                'tdebt'           => 0.0,
            ];
            $negKey--;
        }

        // Construcción de filas + totales
        $sumPrevDebt = $sumPrevExo = $sumPrevPaid = 0.0;
        $sumMonth    = 0.0;
        $sumDt       = 0;
        $sumDnt      = 0;
        $sumTdebt    = 0.0;

        $item = 0;
        foreach ($map as $vid => $r) {
            $item++;

            if ($vid < 0) {
                $this->legacyRowIndexes[] = $item;
            }

            $this->rows[] = [
                $item,
                (string)$r['order'],
                (string)$r['plate'],
                round((float)$r['prev_debt'], 2),
                round((float)$r['prev_exonerated'], 2),
                round((float)$r['prev_paid_debt'], 2),
                round((float)$r['month_amount'], 2),
                $laborables,
                (int)$r['dt_days'],
                (int)$r['dnt_days'],
                strtoupper(trim($r['condition'] ?? '')) ?: '-',
                round((float)$r['tdebt'], 2),
            ];

            $sumPrevDebt += (float)$r['prev_debt'];
            $sumPrevExo  += (float)$r['prev_exonerated'];
            $sumPrevPaid += (float)$r['prev_paid_debt'];
            $sumMonth    += (float)$r['month_amount'];
            $sumDt       += (int)$r['dt_days'];
            $sumDnt      += (int)$r['dnt_days'];
            $sumTdebt    += (float)$r['tdebt'];
        }

        $this->footer1 = [
            '', 'TOTAL', '',
            round($sumPrevDebt, 2),
            round($sumPrevExo, 2),
            round($sumPrevPaid, 2),
            round($sumMonth, 2),
            $laborables,
            $sumDt,
            $sumDnt,
            '',
            round($sumTdebt, 2),
        ];

        $this->footer2 = [
            '', 'TOTAL', '',
            '', '', '',
            round($sumMonth + $sumPrevPaid, 2),
            '', '', '', '', '',
        ];
    }

    protected function laborablesSinDomingo(CarbonImmutable $monthStart): int
    {
        $days  = (int)$monthStart->daysInMonth;
        $count = 0;
        for ($d=1; $d <= $days; $d++) {
            $w = $monthStart->day($d)->dayOfWeekIso; // 7=domingo
            if ($w !== 7) $count++;
        }
        return $count;
    }

    protected function mesTexto(int $m): string
    {
        return [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ][$m] ?? (string)$m;
    }
}
