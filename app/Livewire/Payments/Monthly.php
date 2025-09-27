<?php
// app/Livewire/Payments/Monthly.php

namespace App\Livewire\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;

class Monthly extends Component
{
    #[Url(except: null)] public ?int $year  = null;
    #[Url(except: null)] public ?int $month = null;
    #[Url(except: null)] public ?string $cond = null;   // null|EX|GN|DT|EX5

    public int $daysInMonth = 30;
    public int $laborableDays = 0; // días del mes sin domingos

    // Filas por vehículo
    public array $rows = []; // order, plate, condition, prev_debt, prev_exonerated, prev_paid_debt, month_amount, dt_days, dnt_days, tdebt

    // Totales
    public float $sumPrevDebt = 0.0;
    public float $sumPrevExonerated = 0.0;
    public float $sumPrevPaidDebt = 0.0;
    public float $sumMonthAmount = 0.0;
    public int   $sumDtDays = 0;
    public int   $sumDntDays = 0;
    public float $sumTDebt = 0.0;

    // Tablas/columnas
    protected string $paymentsTable   = 'payments';
    protected string $vehiclesTable   = 'vehicles';
    protected string $costTable       = 'cost_per_plate_days';
    protected string $departuresTable = 'departures'; // ya no se usa para T.Deuda
    protected string $debtDaysTable   = 'debt_days';
    protected string $amountCol       = 'amount';

    public function mount(): void
    {
        $now = CarbonImmutable::now();
        $this->year  = $this->year  ?: (int) $now->year;
        $this->month = $this->month ?: (int) $now->month;
        $this->loadData();
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['year','month','cond'], true)) {
            $this->loadData();
        }
    }

    public function render()
    {
        $years  = range((int)date('Y') - 10, (int)date('Y') + 1);
        $months = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ];
        $conditions = [ null => 'Todos', 'EX' => 'EX', 'GN' => 'GN', 'DT' => 'DT', 'EX5' => 'EX5' ];

        return view('livewire.payments.monthly', compact('years','months','conditions'));
    }

    /* ======================= CORE ======================= */

    protected function loadData(): void
    {
        // Reset
        $this->rows = [];
        $this->sumPrevDebt = $this->sumPrevExonerated = $this->sumPrevPaidDebt = 0.0;
        $this->sumMonthAmount = $this->sumTDebt = 0.0;
        $this->sumDtDays = $this->sumDntDays = 0;

        if (!Schema::hasTable($this->vehiclesTable)) return;

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();
        $this->daysInMonth  = (int) $start->daysInMonth;
        $this->laborableDays = $this->countLaborableDays($start, $end);

        // ===== Vehículos activos (+ filtro condición) =====
        $orderCol = Schema::hasColumn($this->vehiclesTable,'sort_order')
            ? 'sort_order'
            : (Schema::hasColumn($this->vehiclesTable,'plate') ? 'plate' : 'id');

        $vehCols = ['id','plate','status'];
        if (Schema::hasColumn($this->vehiclesTable,'sort_order')) $vehCols[] = 'sort_order';
        if (Schema::hasColumn($this->vehiclesTable,'condition'))  $vehCols[] = 'condition';

        $vehiclesQ = DB::table($this->vehiclesTable)
            ->select($vehCols)
            ->where('status', 'active');

        if ($this->cond) {
            $vehiclesQ->where(function ($q) {
                $q->where('condition', $this->cond)
                    ->orWhere('condition', 'like', $this->cond.'%');
            });
        }

        $vehicles = $vehiclesQ->orderBy($orderCol)->get();
        if ($vehicles->isEmpty()) return;

        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'order'            => (string)($v->sort_order ?? ''),
                'plate'            => (string)$v->plate,
                'condition'        => (string)($v->condition ?? ''),
                // Deuda mes anterior (debt_days)
                'prev_debt'        => 0.0,
                'prev_exonerated'  => 0.0,
                'prev_paid_debt'   => 0.0,
                // Pagos del mes y días
                'month_amount'     => 0.0,
                'dt_days'          => 0,
                'dnt_days'         => 0,
                // Deuda actual
                'tdebt'            => 0.0,
            ];
        }

        // ====== Deuda del mes anterior desde DEBT_DAYS ======
        if (Schema::hasTable($this->debtDaysTable)) {
            $this->fillPreviousMonthDebtFromDebtDays($vehicles, $start->subMonth());
        }

        // ====== Pagos del mes (PAGO/RETRASO) ======
        if (Schema::hasTable($this->paymentsTable)) {
            $this->fillCurrentMonthPayments($vehicles, $start, $end);
        }

        // ====== T.Deuda del mes con la MISMA lógica que el export ======
        if (Schema::hasTable($this->costTable)) {
            $this->fillCurrentMonthTDebt_MatchExport($vehicles, $start, $end);
        }

        // Totales + DNT
        foreach ($this->rows as $vid => &$row) {
            $row['dnt_days'] = max(0, $this->laborableDays - (int)$row['dt_days']);

            $this->sumPrevDebt       += (float)$row['prev_debt'];
            $this->sumPrevExonerated += (float)$row['prev_exonerated'];
            $this->sumPrevPaidDebt   += (float)$row['prev_paid_debt'];
            $this->sumMonthAmount    += (float)$row['month_amount'];
            $this->sumDtDays         += (int)$row['dt_days'];
            $this->sumDntDays        += (int)$row['dnt_days'];
            $this->sumTDebt          += (float)$row['tdebt'];
        }
        unset($row);
    }

    /* ======================= HELPERS ======================= */

    protected function countLaborableDays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $count = 0;
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            if ((int)$d->dayOfWeekIso === 7) continue; // 7=Domingo
            $count++;
        }
        return $count;
    }

    /**
     * Deuda del mes anterior desde debt_days:
     *  - prev_debt       = total - exonerated - amortized
     *  - prev_exonerated = exonerated
     *  - prev_paid_debt  = amortized
     */
    protected function fillPreviousMonthDebtFromDebtDays($vehicles, CarbonImmutable $prev): void
    {
        $prevStart = $prev->startOfMonth();

        $aggs = DB::table($this->debtDaysTable)
            ->selectRaw("
                vehicle_id,
                COALESCE(SUM(total),0)      as total_sum,
                COALESCE(SUM(exonerated),0) as exo_sum,
                COALESCE(SUM(amortized),0)  as amo_sum
            ")
            ->whereYear('date', $prevStart->year)
            ->whereMonth('date', $prevStart->month)
            ->groupBy('vehicle_id')
            ->get();

        $map = [];
        foreach ($aggs as $r) {
            $vid   = (int)$r->vehicle_id;
            $total = (float)$r->total_sum;
            $exo   = (float)$r->exo_sum;
            $amo   = (float)$r->amo_sum;
            $map[$vid] = [
                'prev_debt'       => max(0.0, round($total - $exo - $amo, 2)),
                'prev_exonerated' => round($exo, 2),
                'prev_paid_debt'  => round($amo, 2),
            ];
        }

        foreach ($vehicles as $v) {
            $vid = (int)$v->id;
            if (!isset($this->rows[$vid])) continue;
            if (isset($map[$vid])) {
                $this->rows[$vid]['prev_debt']       = $map[$vid]['prev_debt'];
                $this->rows[$vid]['prev_exonerated'] = $map[$vid]['prev_exonerated'];
                $this->rows[$vid]['prev_paid_debt']  = $map[$vid]['prev_paid_debt'];
            }
        }
    }

    /**
     * Pagos del mes (PAGO/RETRASO) por date_payment
     * month_amount = suma S/
     * dt_days = # de días con pago (distinct DAY) excluyendo domingos
     */
    protected function fillCurrentMonthPayments($vehicles, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $aggs = DB::table($this->paymentsTable.' as p')
            ->leftJoin($this->vehiclesTable.' as v2', function ($join) {
                $join->on('v2.plate', '=', 'p.legacy_plate')
                    ->where('v2.status', 'active');
            })
            ->selectRaw("
                COALESCE(p.vehicle_id, v2.id) as vid,
                SUM(p.amount) as s,
                COUNT(DISTINCT DAY(p.date_payment)) as cdays
            ")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull('p.date_payment')
            ->whereBetween('p.date_payment', [$start->toDateString(), $end->toDateString()])
            ->whereRaw("DAYOFWEEK(p.date_payment) <> 1")
            ->groupBy('vid')
            ->get();

        foreach ($aggs as $r) {
            $vid = (int) $r->vid;
            if (!isset($this->rows[$vid])) continue;
            $this->rows[$vid]['month_amount'] = round((float)$r->s, 2);
            $this->rows[$vid]['dt_days']      = (int) $r->cdays;
        }
    }

    public function export(){
        $route = route('exports.payments-monthly',
            [   "year" => $this->year,
                "month" => $this->month,
            ]);

        $this->dispatch('url-open',["url" => $route]);
    }

    /**
     * T.Deuda del mes con la MISMA lógica que el export:
     *  - EX/EX5 => 0
     *  - DT     => max( costo_en_días_con_pago - total_pagado_mes , 0 )
     *  - GN/otros => suma de costos en días SIN pago (sin domingos)
     */
    protected function fillCurrentMonthTDebt_MatchExport($vehicles, CarbonImmutable $start, CarbonImmutable $end): void
    {
        // 1) DÍAS con pago (PAGO/RETRASO), para marcar días pagados (sin domingos)
        $paidByVid = [];
        $paidDays = DB::table($this->paymentsTable.' as p')
            ->leftJoin($this->vehiclesTable.' as v2', function ($join) {
                $join->on('v2.plate', '=', 'p.legacy_plate')
                    ->where('v2.status', 'active');
            })
            ->selectRaw("COALESCE(p.vehicle_id, v2.id) as vid, DAY(p.date_payment) as d")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull('p.date_payment')
            ->whereBetween('p.date_payment', [$start->toDateString(), $end->toDateString()])
            ->whereRaw("DAYOFWEEK(p.date_payment) <> 1")
            ->groupBy('vid','d')
            ->get();

        foreach ($paidDays as $pd) {
            $paidByVid[(int)$pd->vid][(int)$pd->d] = true;
        }

        // 2) Total pagado del mes por vehículo (ya cargado en rows)
        $paidTotalByVid = [];
        foreach ($this->rows as $vid => $r) {
            $paidTotalByVid[(int)$vid] = (float)$r['month_amount'];
        }

        // 3) COSTOS por día del mes (sin domingos)
        $costByVid = [];
        $costs = DB::table($this->costTable)
            ->selectRaw("vehicle_id, DAY(`date`) as d, SUM(amount) as a")
            ->whereYear('date', $start->year)
            ->whereMonth('date', $start->month)
            ->whereRaw("DAYOFWEEK(`date`) <> 1")
            ->groupBy('vehicle_id','d')
            ->get();

        foreach ($costs as $c) {
            $costByVid[(int)$c->vehicle_id][(int)$c->d] = (float)$c->a;
        }

        // 4) Aplicar reglas exactamente como el export
        foreach ($this->rows as $vid => &$row) {
            $vid  = (int)$vid;
            $cond = strtoupper(trim($row['condition'] ?? ''));

            // Separar costos en pagados / no pagados
            $costOnPaid   = 0.0;
            $costOnUnpaid = 0.0;
            if (isset($costByVid[$vid])) {
                foreach ($costByVid[$vid] as $day => $amt) {
                    if (isset($paidByVid[$vid][$day])) {
                        $costOnPaid += (float)$amt;
                    } else {
                        $costOnUnpaid += (float)$amt;
                    }
                }
            }

            if (str_starts_with($cond, 'EX')) {
                $row['tdebt'] = 0.0;
            } elseif ($cond === 'DT') {
                $paidTotal   = $paidTotalByVid[$vid] ?? 0.0;
                $row['tdebt'] = round(max(0.0, $costOnPaid - $paidTotal), 2);
            } else { // GN u otros
                $row['tdebt'] = round($costOnUnpaid, 2);
            }
        }
        unset($row);
    }
}
