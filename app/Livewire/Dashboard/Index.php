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

    protected function monthRange(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth()->toDateString();
        $end   = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();
        return [$start, $end];
    }

    protected function sumMonthIncomes(): float
    {
        [$start, $end] = $this->monthRange();

        $payments = Payment::whereBetween(DB::raw('DATE(date_register)'), [$start, $end])->sum('amount');
        $departures = Departure::whereBetween(DB::raw('DATE(date)'), [$start, $end])->sum('price');
        $incomes = Income::whereBetween(DB::raw('DATE(date)'), [$start, $end])->sum('total');

        return (float) ($payments + $departures + $incomes);
    }

    protected function sumMonthExpenses(): float
    {
        [$start, $end] = $this->monthRange();
        return (float) Expense::whereBetween(DB::raw('DATE(date)'), [$start, $end])->sum('total');
    }

    protected function sumTodayIncomes(): float
    {
        $today = now()->toDateString();
        $payments = Payment::whereDate('date_register', $today)->sum('amount');
        $departures = Departure::whereDate('date', $today)->sum('price');
        $incomes = Income::whereDate('date', $today)->sum('total');

        return (float) ($payments + $departures + $incomes);
    }

    protected function sumTodayExpenses(): float
    {
        $today = now()->toDateString();
        return (float) Expense::whereDate('date', $today)->sum('total');
    }

    protected function topHeadquartersByIncome(int $limit = 5): array
    {
        [$start, $end] = $this->monthRange();

        // Ingresos por HQ desde payments + departures + incomes (incomes no tiene headquarter_id, si lo tienes via users, omite o ajusta)
        $payments = Payment::selectRaw('headquarter_id, SUM(amount) AS sum_amount')
            ->whereBetween(DB::raw('DATE(date_register)'), [$start, $end])
            ->groupBy('headquarter_id');

        $departures = Departure::selectRaw('headquarter_id, SUM(price) AS sum_amount')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->groupBy('headquarter_id');

        // NOTA: INCOMES no tiene headquarter_id en tu esquema; si deseas atribuirlo por usuario, necesitaríamos join a users.

        // Unimos por HQ en PHP para simplicidad temporal
        $totals = [];

        foreach ($payments->get() as $p) {
            $totals[$p->headquarter_id] = ($totals[$p->headquarter_id] ?? 0) + (float) $p->sum_amount;
        }
        foreach ($departures->get() as $d) {
            $totals[$d->headquarter_id] = ($totals[$d->headquarter_id] ?? 0) + (float) $d->sum_amount;
        }

        // Resuelve nombres y ordena
        $names = Headquarter::pluck('name', 'id');
        $rows = [];
        foreach ($totals as $hqId => $sum) {
            $rows[] = [
                'hq'   => $names[$hqId] ?? '—',
                'sum'  => $sum,
            ];
        }
        usort($rows, fn($a, $b) => $b['sum'] <=> $a['sum']);

        return array_slice($rows, 0, $limit);
    }

    protected function topPaymentTypes(int $limit = 3): array
    {
        [$start, $end] = $this->monthRange();
        $rows = Payment::selectRaw('type, SUM(amount) AS sum_amount')
            ->whereBetween(DB::raw('DATE(date_register)'), [$start, $end])
            ->groupBy('type')
            ->orderByDesc('sum_amount')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => [
            'type' => (string) $r->type,
            'sum'  => (float) $r->sum_amount,
        ])->all();
    }

    protected function dailyBalances(): array
    {
        [$start, $end] = $this->monthRange();
        $days = [];
        $cursor = Carbon::parse($start);
        $endC   = Carbon::parse($end);

        while ($cursor->lte($endC)) {
            $d = $cursor->toDateString();

            $dayIncomes = (float) Payment::whereDate('date_register', $d)->sum('amount')
                + (float) Departure::whereDate('date', $d)->sum('price')
                + (float) Income::whereDate('date', $d)->sum('total');

            $dayExpenses = (float) Expense::whereDate('date', $d)->sum('total');

            $days[] = [
                'date'    => $d,
                'income'  => $dayIncomes,
                'expense' => $dayExpenses,
                'balance' => $dayIncomes - $dayExpenses,
            ];

            $cursor->addDay();
        }

        return $days;
    }

    public function render()
    {
        $ingMes = $this->sumMonthIncomes();
        $egrMes = $this->sumMonthExpenses();
        $utilMes = $ingMes - $egrMes;

        $ingHoy = $this->sumTodayIncomes();
        $egrHoy = $this->sumTodayExpenses();
        $saldoHoy = $ingHoy - $egrHoy;

        $topHQ   = $this->topHeadquartersByIncome(5);
        $topTypes= $this->topPaymentTypes(3);
        $days    = $this->dailyBalances();

        // Promedio diario (solo sobre días con movimiento, opcional)
        $daysWithMove = array_filter($days, fn($r) => ($r['income'] != 0 || $r['expense'] != 0));
        $promSaldoDia = count($daysWithMove) ? array_sum(array_column($daysWithMove, 'balance')) / count($daysWithMove) : 0.0;

        return view('livewire.dashboard.index', [
            'ingMes'       => $ingMes,
            'egrMes'       => $egrMes,
            'utilMes'      => $utilMes,
            'ingHoy'       => $ingHoy,
            'egrHoy'       => $egrHoy,
            'saldoHoy'     => $saldoHoy,
            'topHQ'        => $topHQ,
            'topTypes'     => $topTypes,
            'days'         => $days,
            'promSaldoDia' => $promSaldoDia,
        ]);
    }
}
