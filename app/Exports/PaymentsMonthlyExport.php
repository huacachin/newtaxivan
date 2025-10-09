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
                $s = $event->sheet->getDelegate();

                // --- Paleta
                $blue      = 'FF2874A6';
                $footerBg  = 'FFCEE7FF';
                $white     = 'FFFFFFFF';
                $black     = 'FF000000';
                $borderC   = 'FFCFD8DC';

                // ========== Layout (insertamos 2 filas: Título y Cabecera de grupos) ==========
                $s->insertNewRowBefore(1, 2);            // empuja headings a la fila 3
                $lastCol = 'L';                          // A..L
                $titleRow  = 1;
                $groupRow  = 2;
                $headRow   = 3;
                $dataRow1  = 4;

                // Título
                $s->mergeCells("A{$titleRow}:{$lastCol}{$titleRow}");
                $s->setCellValue("A{$titleRow}", 'REPORTE MENSUAL DE PAGOS '.$this->mesTexto($this->month).' '.$this->year);
                $s->getStyle("A{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                $s->getRowDimension($titleRow)->setRowHeight(18);

                // Cabecera de grupos (D:F y H:K)
                $s->mergeCells("D{$groupRow}:F{$groupRow}");
                $s->mergeCells("H{$groupRow}:K{$groupRow}");
                $s->setCellValue("D{$groupRow}", 'DEUDA DEL MES ANTERIOR');
                $s->setCellValue("H{$groupRow}", 'PAGOS');
                $s->getStyle("A{$groupRow}:{$lastCol}{$groupRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                $s->getRowDimension($groupRow)->setRowHeight(16);

                // Headings (fila 3)
                $s->getStyle("A{$headRow}:{$lastCol}{$headRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);

                // Congelar
                $s->freezePane("A{$dataRow1}");

                // Bordes finos para toda la tabla (incl. pies)
                $lastRow = (int)$s->getHighestRow();
                $s->getStyle("A{$groupRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$groupRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->getColor()->setARGB($borderC);

                // Anchos
                foreach (range('A','L') as $col) $s->getColumnDimension($col)->setAutoSize(false);
                $s->getColumnDimension('A')->setWidth(5.5);
                $s->getColumnDimension('B')->setWidth(6.5);
                $s->getColumnDimension('C')->setWidth(12);
                foreach (['D','E','F'] as $c) $s->getColumnDimension($c)->setWidth(10);
                $s->getColumnDimension('G')->setWidth(12);
                foreach (['H','I','J'] as $c) $s->getColumnDimension($c)->setWidth(6.5);
                $s->getColumnDimension('K')->setWidth(10);
                $s->getColumnDimension('L')->setWidth(12);

                // Alineaciones: texto a la izq en B,C,K; resto centrado/numérico
                $dataEnd = $lastRow - 2;  // antes de footers (hay 2)
                if ($dataEnd >= $dataRow1) {
                    $s->getStyle("B{$dataRow1}:B{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $s->getStyle("C{$dataRow1}:C{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $s->getStyle("K{$dataRow1}:K{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // Formatos numéricos
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

                // Rellenar vacíos numéricos con 0 en todo el bloque de datos + pies
                $colIdxsNum = ['A','D','E','F','G','H','I','J','L'];
                for ($r = $dataRow1; $r <= $lastRow; $r++) {
                    foreach ($colIdxsNum as $c) {
                        $cell = $s->getCell("{$c}{$r}");
                        $val  = $cell->getValue();
                        if ($val === null || $val === '') {
                            $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                        }
                    }
                }

                // Pies (últimas 2 filas) en #CEE7FF
                $footer1 = $lastRow - 1;
                $footer2 = $lastRow;
                foreach ([$footer1,$footer2] as $fr) {
                    $s->getStyle("A{$fr}:{$lastCol}{$fr}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                        'font' => ['bold'=>true,'color'=>['argb'=>$black]],
                        'borders' => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['argb'=>$blue]]],
                        'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
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
            0, 0, 0,
            round($sumMonth + $sumPrevPaid, 2), // Suma que te muestran en la segunda línea
            0, 0, 0, '', 0,
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
