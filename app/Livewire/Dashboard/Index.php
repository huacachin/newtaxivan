<?php

namespace App\Livewire\Dashboard;

use App\Models\Departure;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\Payment;
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

    /** Rango del mes en timestamps completos para usar índices (sin DATE()) */
    protected function monthRange(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        // Retorno timestamps/datetimes exactos
        return [$start->copy(), $end->copy()];
    }

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
        $today = now()->toDateString();
        return (float) Expense::whereDate('date', $today)->sum('total');
    }

    /** Top HQ por ingresos: solo departures.price (columna S/ de Empresa) */
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

    /** Top tipos de pago (evita DATE() y usa rango indexable) */
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

    /**
     * Balances diarios del mes — 4 consultas (una por tabla), agrupadas por fecha,
     * luego fusiono en PHP. Sin loops con queries (evita N+1 x día).
     */
    protected function dailyBalances(): array
    {
        [$start, $end] = $this->monthRange();

        // Mapeos por día: 'YYYY-MM-DD' => suma
        $payByDay = Payment::selectRaw('DATE(date_register) AS d, SUM(amount) AS s')
            ->whereBetween('date_register', [$start, $end])
            ->groupBy('d')
            ->pluck('s', 'd');

        $depByDay = Departure::selectRaw('date AS d, SUM(price) AS s')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('d')
            ->pluck('s', 'd');

        $incByDay = Income::selectRaw('date AS d, SUM(total) AS s')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('d')
            ->pluck('s', 'd');

        $expByDay = Expense::selectRaw('date AS d, SUM(total) AS s')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('d')
            ->pluck('s', 'd');

        // Construyo el arreglo de días del mes en memoria
        $days = [];
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor->lte($endDay)) {
            $d = $cursor->toDateString();

            $income  = (float) ($payByDay[$d] ?? 0) + (float) ($depByDay[$d] ?? 0) + (float) ($incByDay[$d] ?? 0);
            $expense = (float) ($expByDay[$d] ?? 0);
            $days[] = [
                'date'    => $d,
                'income'  => $income,
                'expense' => $expense,
                'balance' => $income - $expense,
            ];

            $cursor->addDay();
        }

        return $days;
    }

    public function render()
    {
        // Totales de mes (2 consultas grandes)
        $ingMes  = $this->sumMonthIncomes();
        $egrMes  = $this->sumMonthExpenses();
        $utilMes = $ingMes - $egrMes;

        // Totales de hoy (2–4 consultas)
        $ingHoy  = $this->sumTodayIncomes();
        $egrHoy  = $this->sumTodayExpenses();
        $saldoHoy= $ingHoy - $egrHoy;

        // Ranking (2 consultas)
        $topHQ    = $this->topHeadquartersByIncome(5);
        $topTypes = $this->topPaymentTypes(3);

        // Serie diaria (4 consultas)
        $days = $this->dailyBalances();

        // Promedio diario (sobre días con movimiento)
        $daysWithMove = array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0));
        $promSaldoDia = count($daysWithMove)
            ? array_sum(array_column($daysWithMove, 'balance')) / count($daysWithMove)
            : 0.0;

        // Datos filtrados para el chart
        $chartData = array_values(array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0)));
        $this->dispatch('chart-data', data: $chartData);

        return view('livewire.dashboard.index', compact(
            'ingMes','egrMes','utilMes',
            'ingHoy','egrHoy','saldoHoy',
            'topHQ','topTypes','days','promSaldoDia'
        ));
    }
}
