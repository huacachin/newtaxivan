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

class PaymentsMonthlyExport implements FromArray, WithHeadings, WithEvents, WithTitle
{
    protected int $year;
    protected int $month;
    protected ?string $cond;

    protected array $rows = [];
    protected array $footer1 = [];
    protected array $footer2 = [];

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
            'Item','Cod','Placa',
            'Deuda ant.','Exonerado','P.Deuda',
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
                $sheet = $event->sheet->getDelegate();

                // Calcular filas
                $rowCount  = count($this->rows);
                $titleRow  = 1;
                $headerRow = 2;            // después de insertar el título
                $firstData = 3;            // datos inician en fila 3
                $lastData  = $firstData + max(0, $rowCount - 1);
                $footer1   = $lastData + 1;
                $footer2   = $footer1 + 1;

                // Título (inserta y pinta)
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells('A1:L1');
                $sheet->setCellValue('A1', 'REPORTE MENSUAL DE PAGO – '.$this->mesTexto($this->month).' '.$this->year);
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => 'center'],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '23242F']],
                ]);

                // Encabezado oscuro
                $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => 'center'],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '23242F']],
                ]);

                // Bordes finos (incluye footers)
                $sheet->getStyle("A{$headerRow}:L{$footer2}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color'       => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ]);

                // Congelar debajo del header
                $sheet->freezePane('A3');

                // Autosize columnas
                foreach (range('A', 'L') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Formatos numéricos en montos (D,E,F,G,L)
                foreach (['D','E','F','G','L'] as $col) {
                    $sheet->getStyle("{$col}{$firstData}:{$col}{$footer2}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // Contenido (tbody) en gris suave
                if ($lastData >= $firstData) {
                    $sheet->getStyle("A{$firstData}:L{$lastData}")->applyFromArray([
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F1F5F9']],
                    ]);
                }

                // Footers: mismo color que thead + negrita
                $sheet->getStyle("A{$footer1}:L{$footer1}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '23242F']],
                ]);
                $sheet->getStyle("A{$footer2}:L{$footer2}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '23242F']],
                ]);
            },
        ];
    }

    /* ==================== DATA ==================== */

    protected function build(): void
    {
        if (!Schema::hasTable($this->vehiclesTable) || !Schema::hasTable($this->paymentsTable)) return;

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();
        $laborables = $this->laborablesSinDomingo($start);

        $vehCols = ['id','plate','status'];
        if (Schema::hasColumn($this->vehiclesTable,'sort_order')) $vehCols[] = 'sort_order';
        if (Schema::hasColumn($this->vehiclesTable,'condition'))  $vehCols[] = 'condition';

        $orderCol = Schema::hasColumn($this->vehiclesTable,'sort_order')
            ? 'sort_order' : (Schema::hasColumn($this->vehiclesTable,'plate') ? 'plate' : 'id');

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

        // Deuda mes anterior (debt_days)
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

        // Pagos del mes (PAGO/RETRASO)
        $paidAgg = DB::table($this->paymentsTable.' as p')
            ->leftJoin($this->vehiclesTable.' as v2', function ($join) {
                $join->on('v2.plate', '=', 'p.legacy_plate')->where('v2.status','active');
            })
            ->selectRaw("
                COALESCE(p.vehicle_id, v2.id) as vid,
                SUM(p.amount) as sum_paid,
                COUNT(DISTINCT DAY(p.date_payment)) as cdays
            ")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull('p.date_payment')
            ->whereBetween('p.date_payment', [$start->toDateString(), $end->toDateString()])
            ->whereRaw('DAYOFWEEK(p.date_payment) <> 1')
            ->groupBy('vid')
            ->get();

        foreach ($paidAgg as $r) {
            $vid = (int)$r->vid;
            if (!isset($map[$vid])) continue;
            $map[$vid]['month_amount'] = round((float)$r->sum_paid, 2);
            $map[$vid]['dt_days']      = (int)$r->cdays;
        }

        // DNT
        foreach ($map as $vid => &$row) {
            $row['dnt_days'] = max(0, $laborables - (int)$row['dt_days']);
        }
        unset($row);

        // Días con pago (para separar costos pagados/no pagados)
        $paidDays = DB::table($this->paymentsTable.' as p')
            ->leftJoin($this->vehiclesTable.' as v2', function ($join) {
                $join->on('v2.plate', '=', 'p.legacy_plate')->where('v2.status','active');
            })
            ->selectRaw("COALESCE(p.vehicle_id, v2.id) as vid, DAY(p.date_payment) as d")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull('p.date_payment')
            ->whereBetween('p.date_payment', [$start->toDateString(), $end->toDateString()])
            ->whereRaw('DAYOFWEEK(p.date_payment) <> 1')
            ->groupBy('vid','d')
            ->get();

        $paidByVid = [];
        foreach ($paidDays as $pd) {
            $paidByVid[(int)$pd->vid][(int)$pd->d] = true;
        }

        // Costos por día (sin domingos)
        $costByVid = [];
        $costs = DB::table($this->costTable)
            ->selectRaw("vehicle_id, DAY(`date`) as d, SUM(amount) as a")
            ->whereYear('date', $start->year)
            ->whereMonth('date', $start->month)
            ->whereRaw('DAYOFWEEK(`date`) <> 1')
            ->groupBy('vehicle_id','d')
            ->get();

        foreach ($costs as $c) {
            $costByVid[(int)$c->vehicle_id][(int)$c->d] = (float)$c->a;
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

            $cond = strtoupper(trim($r['condition'] ?? ''));
            $isEX = str_starts_with($cond, 'EX');
            $isDT = ($cond === 'DT');

            // separar costos
            $costOnPaid = 0.0; $costOnUnpaid = 0.0;
            if (isset($costByVid[$vid])) {
                foreach ($costByVid[$vid] as $day => $amt) {
                    if (isset($paidByVid[$vid][$day])) $costOnPaid += (float)$amt;
                    else                                $costOnUnpaid += (float)$amt;
                }
            }

            if ($isEX) {
                $tdebt = 0.0;
            } elseif ($isDT) {
                $tdebt = max(0.0, $costOnPaid - (float)$r['month_amount']);
            } else {
                $tdebt = $costOnUnpaid;
            }
            $tdebt = round($tdebt, 2);

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
                $cond ?: '-',
                $tdebt,
            ];

            $sumPrevDebt += (float)$r['prev_debt'];
            $sumPrevExo  += (float)$r['prev_exonerated'];
            $sumPrevPaid += (float)$r['prev_paid_debt'];
            $sumMonth    += (float)$r['month_amount'];
            $sumDt       += (int)$r['dt_days'];
            $sumDnt      += (int)$r['dnt_days'];
            $sumTdebt    += $tdebt;
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
