<?php

namespace App\Livewire\Dashboard;

use App\Models\DebtDay;
use App\Models\Departure;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    public int $year;
    public int $month;

    public function mount(?int $year = null, ?int $month = null): void
    {
        $now = now();
        $this->year  = $year  ?: (int) $now->year;
        $this->month = $month ?: (int) $now->month;
    }

    // ============================== RANGOS ==============================

    /** Rango del mes en datetimes */
    protected function monthRange(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        return [$start->copy(), $end->copy()];
    }

    // ============================== TOTALES =============================

    protected function sumMonthIncomes(): float
    {
        [$start, $end] = $this->monthRange();
        $payments   = Payment::whereBetween('date_register', [$start, $end])->sum('amount');
        $departures = Departure::whereBetween('date', [$start->toDateString(), $end->toDateString()])->sum('price');
        $incomes    = Income::whereBetween('date', [$start->toDateString(), $end->toDateString()])->sum('total');
        return (float) ($payments + $departures + $incomes);
    }

    protected function sumMonthExpenses(): float
    {
        [$start, $end] = $this->monthRange();
        return (float) Expense::whereBetween('date', [$start->toDateString(), $end->toDateString()])->sum('total');
    }

    protected function sumTodayIncomes(): float
    {
        $today = now();
        $payments   = Payment::whereBetween('date_register', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->sum('amount');
        $departures = Departure::whereDate('date', $today->toDateString())->sum('price');
        $incomes    = Income::whereDate('date', $today->toDateString())->sum('total');
        return (float) ($payments + $departures + $incomes);
    }

    protected function sumTodayExpenses(): float
    {
        return (float) Expense::whereDate('date', now()->toDateString())->sum('total');
    }

    protected function sumYesterdayIncomes(): float
    {
        $y = now()->subDay();
        $payments   = Payment::whereBetween('date_register', [$y->copy()->startOfDay(), $y->copy()->endOfDay()])->sum('amount');
        $departures = Departure::whereDate('date', $y->toDateString())->sum('price');
        $incomes    = Income::whereDate('date', $y->toDateString())->sum('total');
        return (float) ($payments + $departures + $incomes);
    }

    // ============================== TOPS ================================

    /** Top HQ por ingresos (departures.price) */
    protected function topHeadquartersByIncome(int $limit = 5): array
    {
        [$start, $end] = $this->monthRange();

        $rows = Departure::query()
            ->select('headquarter_id', DB::raw('SUM(price) AS sum_amount'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('headquarter_id')
            ->orderByDesc('sum_amount')
            ->limit($limit)
            ->get();

        $names = Headquarter::pluck('name', 'id');

        return $rows->map(fn($r) => [
            'hq'    => $names[$r->headquarter_id] ?? '—',
            'hq_id' => (int) $r->headquarter_id,
            'sum'   => (float) $r->sum_amount,
        ])->all();
    }

    protected function topPaymentTypes(int $limit = 3): array
    {
        [$start, $end] = $this->monthRange();
        $rows = Payment::selectRaw('type, SUM(amount) AS sum_amount')
            ->whereBetween('date_register', [$start, $end])
            ->groupBy('type')
            ->orderByDesc('sum_amount')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => [
            'type' => (string) $r->type,
            'sum'  => (float) $r->sum_amount,
        ])->all();
    }

    // ============================== SERIE DIARIA ========================

    /** Balances diarios del mes — 4 consultas agrupadas por día */
    protected function dailyBalances(): array
    {
        [$start, $end] = $this->monthRange();

        $payByDay = Payment::selectRaw('DATE(date_register) AS d, SUM(amount) AS s')
            ->whereBetween('date_register', [$start, $end])->groupBy('d')->pluck('s', 'd');
        $depByDay = Departure::selectRaw('date AS d, SUM(price) AS s')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->groupBy('d')->pluck('s', 'd');
        $incByDay = Income::selectRaw('date AS d, SUM(total) AS s')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->groupBy('d')->pluck('s', 'd');
        $expByDay = Expense::selectRaw('date AS d, SUM(total) AS s')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->groupBy('d')->pluck('s', 'd');

        $days = [];
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor->lte($endDay)) {
            $d = $cursor->toDateString();
            $dep = (float) ($depByDay[$d] ?? 0);
            $inc = (float) ($incByDay[$d] ?? 0);
            $exp = (float) ($expByDay[$d] ?? 0);
            $pay = (float) ($payByDay[$d] ?? 0);
            $days[] = [
                'date'       => $d,
                'departures' => $dep,
                'payments'   => $pay,
                'incomes'    => $inc,
                'expense'    => $exp,
                'income'     => $dep + $inc + $pay,
                'balance'    => $dep + $inc + $pay - $exp,
            ];
            $cursor->addDay();
        }

        return $days;
    }

    /** Spark de los últimos 7 días — ingresos totales por día */
    protected function weekSpark(): array
    {
        $tz = config('app.timezone', 'America/Lima');
        $start = now($tz)->subDays(6)->startOfDay();
        $end   = now($tz)->endOfDay();

        $pay = Payment::selectRaw('DATE(date_register) AS d, SUM(amount) AS s')
            ->whereBetween('date_register', [$start, $end])->groupBy('d')->pluck('s', 'd');
        $dep = Departure::selectRaw('date AS d, SUM(price) AS s')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->groupBy('d')->pluck('s', 'd');
        $inc = Income::selectRaw('date AS d, SUM(total) AS s')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->groupBy('d')->pluck('s', 'd');

        $out = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();
            $out[] = (float) (($pay[$d] ?? 0) + ($dep[$d] ?? 0) + ($inc[$d] ?? 0));
            $cursor->addDay();
        }
        return $out;
    }

    // ============================== FLOTA ===============================

    protected function vehiclesActiveCount(): int
    {
        $today = now()->toDateString();
        return (int) Vehicle::query()
            ->where('status', 'active')
            ->where(function ($q) use ($today) {
                $q->whereNull('termination_date')->orWhere('termination_date', '>', $today);
            })
            ->count();
    }

    /** Top N alertas de vencimiento: vehículos + propietarios + conductores */
    protected function expiringSoonAlerts(int $limit = 5): array
    {
        $today = now()->toDateString();

        $vehicleAlerts = Vehicle::query()
            ->select('id','plate','soat_date','technical_review','certificate_date','status','termination_date')
            ->where('status', 'active')
            ->where(function ($q) use ($today) {
                $q->whereNull('termination_date')->orWhere('termination_date', '>', $today);
            })
            ->get()
            ->flatMap(fn ($v) => $v->expiringAlerts());

        $ownerAlerts = Owner::query()
            ->select('id','name','document_expiration_date','status')
            ->where('status', 'active')
            ->whereNotNull('document_expiration_date')
            ->get()
            ->flatMap(fn ($o) => $o->expiringAlerts());

        $driverAlerts = Driver::query()
            ->select('id','name','document_expiration_date',
                     'license_revalidation_date','road_education_expiration_date',
                     'credential_expiration_date','status')
            ->where('status', 'active')
            ->get()
            ->flatMap(fn ($d) => $d->expiringAlerts());

        return $vehicleAlerts
            ->concat($ownerAlerts)
            ->concat($driverAlerts)
            ->sortBy('days')
            ->take($limit)
            ->values()
            ->all();
    }

    /** Vehículos con deuda pendiente (total - exonerated - amortized > 0) */
    protected function vehiclesWithDebt(int $limit = 5): array
    {
        return DebtDay::query()
            ->selectRaw('vehicle_id, legacy_plate, SUM(GREATEST(total - COALESCE(exonerated,0) - COALESCE(amortized,0), 0)) AS pending')
            ->whereNotNull('total')
            ->groupBy('vehicle_id', 'legacy_plate')
            ->havingRaw('pending > 0')
            ->orderByDesc('pending')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $plate = $r->legacy_plate;
                if (!$plate && $r->vehicle_id) {
                    $plate = Vehicle::where('id', $r->vehicle_id)->value('plate');
                }
                return [
                    'vehicle_id' => $r->vehicle_id,
                    'plate'      => $plate ?: '—',
                    'pending'    => (float) $r->pending,
                ];
            })
            ->all();
    }

    /** Últimos pagos registrados */
    protected function recentPayments(int $limit = 5)
    {
        return Payment::with(['vehicle:id,plate', 'headquarter:id,name'])
            ->orderByDesc('date_register')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** Últimas salidas */
    protected function recentDepartures(int $limit = 8)
    {
        return Departure::with(['vehicle:id,plate', 'headquarter:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    // ============================== RENDER ==============================

    public function render()
    {
        // Mes / hoy / ayer
        $ingMes  = $this->sumMonthIncomes();
        $egrMes  = $this->sumMonthExpenses();
        $utilMes = $ingMes - $egrMes;

        $ingHoy  = $this->sumTodayIncomes();
        $egrHoy  = $this->sumTodayExpenses();
        $saldoHoy= $ingHoy - $egrHoy;

        $ingAyer = $this->sumYesterdayIncomes();
        $deltaIngresos = $ingAyer > 0 ? (($ingHoy - $ingAyer) / $ingAyer) * 100 : 0.0;

        // Series / agregados
        $topHQ        = $this->topHeadquartersByIncome(5);
        $topTypes     = $this->topPaymentTypes(3);
        $days         = $this->dailyBalances();
        $spark7Vals   = $this->weekSpark();

        // Flota
        $vehiclesActive   = $this->vehiclesActiveCount();
        $expiringAlerts   = $this->expiringSoonAlerts(5);
        $debtsTop         = $this->vehiclesWithDebt(5);
        $debtsPending     = (float) DebtDay::query()
            ->selectRaw('SUM(GREATEST(total - COALESCE(exonerated,0) - COALESCE(amortized,0), 0)) AS s')
            ->value('s');
        $vehiclesWithDebtCount = (int) DebtDay::query()
            ->selectRaw('COUNT(DISTINCT COALESCE(vehicle_id, legacy_plate)) AS n')
            ->whereRaw('(total - COALESCE(exonerated,0) - COALESCE(amortized,0)) > 0')
            ->value('n');
        $alertasPct = $vehiclesActive > 0
            ? (count($expiringAlerts) / $vehiclesActive) * 100
            : 0.0;

        // Movimiento reciente
        $recentPayments   = $this->recentPayments(5);
        $recentDepartures = $this->recentDepartures(8);

        // Promedio diario
        $daysWithMove = array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0));
        $promSaldoDia = count($daysWithMove)
            ? array_sum(array_column($daysWithMove, 'balance')) / count($daysWithMove)
            : 0.0;

        // Datos para el chart (filtra días sin movimiento)
        $chartData = array_values(array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0)));
        $this->dispatch('chart-data', data: $chartData);

        return view('livewire.dashboard.index', compact(
            'ingMes','egrMes','utilMes',
            'ingHoy','egrHoy','saldoHoy','deltaIngresos',
            'topHQ','topTypes','days','promSaldoDia',
            'spark7Vals',
            'vehiclesActive','expiringAlerts','debtsTop','debtsPending',
            'vehiclesWithDebtCount','alertasPct',
            'recentPayments','recentDepartures'
        ));
    }
}
