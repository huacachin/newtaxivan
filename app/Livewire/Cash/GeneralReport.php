<?php

namespace App\Livewire\Cash;

use App\Models\Departure;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GeneralReport extends Component
{
    public int $year;
    public int $month;
    public array $days = [];              // YYYY-MM-DD de todo el mes
    public float $carryFromPrevMonth = 0; // saldo final del mes anterior

    // Totales del mes (para el footer)
    public float $totalIncomes = 0;
    public float $totalExpenses = 0;
    public float $finalBalance = 0;

    // Cache de HQ id=>name
    protected Collection $hqNames;

    public function mount(?int $year = null, ?int $month = null): void
    {
        $today = now();
        $this->year  = $year  ?: (int)$today->year;
        $this->month = $month ?: (int)$today->month;

        $this->prepareStatic();
    }

    public function updatedYear(): void
    {
        $this->prepareStatic();
    }

    public function updatedMonth(): void
    {
        $this->prepareStatic();
    }

    /** Prepara días del mes, HQ y saldo arrastrado */
    protected function prepareStatic(): void
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        // lista de días
        $this->days = [];
        for ($d = (clone $start); $d->lte($end); $d->addDay()) {
            $this->days[] = $d->toDateString(); // YYYY-MM-DD
        }

        // HQs
        $this->hqNames = Headquarter::query()
            ->pluck('name', 'id');

        // Saldo que arrastra: (Ingresos prev) - (Egresos prev)
        $this->carryFromPrevMonth = $this->computePreviousMonthBalance($start);

        // Reset pie
        $this->totalIncomes = 0;
        $this->totalExpenses = 0;
        $this->finalBalance = 0;
    }

    /** Saldo final del mes anterior (para usar como saldo inicial del 1° del mes) */
    protected function computePreviousMonthBalance(Carbon $monthStart): float
    {
        $prevStart = (clone $monthStart)->subMonth()->startOfMonth();
        $prevEnd   = (clone $monthStart)->subMonth()->endOfMonth();

        // INGRESOS prev
        $prevPayments = Payment::query()
            ->whereBetween(DB::raw('DATE(date_register)'), [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->sum('amount');

        $prevDepartures = Departure::query()
            ->whereBetween(DB::raw('DATE(date)'), [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->sum('price');

        $prevIncomes = Income::query()
            ->whereBetween(DB::raw('DATE(date)'), [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->sum('total');

        // EGRESOS prev
        $prevExpenses = Expense::query()
            ->whereBetween(DB::raw('DATE(date)'), [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->sum('total');

        return (float)($prevPayments + $prevDepartures + $prevIncomes - $prevExpenses);
    }

    /** Devuelve un hash con todo el material del mes, por día */
    protected function loadMonthBatches(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth()->toDateString();
        $end   = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();

        // PAYMENTS agrupados por dia, type, hq
        $payments = Payment::query()
            ->selectRaw('DATE(date_register) AS d, type, headquarter_id, SUM(amount) AS amount')
            ->whereBetween(DB::raw('DATE(date_register)'), [$start, $end])
            ->groupBy('d', 'type', 'headquarter_id')
            ->get()
            ->groupBy('d');

        // DEPARTURES agrupados por dia, hq
        $departures = Departure::query()
            ->selectRaw('DATE(date) AS d, headquarter_id, SUM(price) AS amount')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->groupBy('d', 'headquarter_id')
            ->get()
            ->groupBy('d');

        // INCOMES por fila
        $incomes = Income::query()
            ->selectRaw('id, DATE(date) AS d, reason, detail, total')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->orderBy('date')
            ->get()
            ->groupBy('d');

        // EXPENSES por fila
        $expenses = Expense::query()
            ->selectRaw('id, DATE(date) AS d, reason, detail, total')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->orderBy('date')
            ->get()
            ->groupBy('d');

        return compact('payments', 'departures', 'incomes', 'expenses');
    }

    public function render()
    {
        // Cargamos todo el mes en memoria (rápido/1 query por tabla)
        $batches = $this->loadMonthBatches();

        $running = $this->carryFromPrevMonth; // saldo acumulado día a día
        $rowsByDay = [];                      // para la vista

        $this->totalIncomes = 0;
        $this->totalExpenses = 0;

        foreach ($this->days as $d) {
            $dayRows = [];

            $dayIncome = 0.0;
            $dayExpense = 0.0;

            // PAYMENTS (ingreso) – agrupados por type+hq
            foreach (($batches['payments'][$d] ?? collect()) as $p) {
                $hq  = $this->hqNames->get($p->headquarter_id, '—');
                $glosa = sprintf('%s-%s', strtoupper($p->type), $hq);
                $amount = (float) $p->amount;

                $dayRows[] = [
                    'date'   => $d,
                    'glosa'  => $glosa,
                    'ingreso'=> $amount,
                    'egreso' => 0.0,
                ];

                $dayIncome += $amount;
            }

            // DEPARTURES (ingreso) – agrupados por hq
            foreach (($batches['departures'][$d] ?? collect()) as $dep) {
                $hq  = $this->hqNames->get($dep->headquarter_id, '—');
                $glosa = sprintf('Salidas-%s', $hq);
                $amount = (float) $dep->amount;

                $dayRows[] = [
                    'date'   => $d,
                    'glosa'  => $glosa,
                    'ingreso'=> $amount,
                    'egreso' => 0.0,
                ];

                $dayIncome += $amount;
            }

            // INCOMES (filas individuales, ingreso)
            foreach (($batches['incomes'][$d] ?? collect()) as $inc) {
                $glosa = trim($inc->reason . ' - ' . $inc->detail, ' -');

                $dayRows[] = [
                    'date'   => $d,
                    'glosa'  => $glosa,
                    'ingreso'=> (float) $inc->total,
                    'egreso' => 0.0,
                ];
                $dayIncome += (float) $inc->total;
            }

            // EXPENSES (filas individuales, egreso)
            foreach (($batches['expenses'][$d] ?? collect()) as $exp) {
                $glosa = trim($exp->reason . ' - ' . $exp->detail, ' -');

                $dayRows[] = [
                    'date'   => $d,
                    'glosa'  => $glosa,
                    'ingreso'=> 0.0,
                    'egreso' => (float) $exp->total,
                ];
                $dayExpense += (float) $exp->total;
            }

            // Totales del día y saldo
            $running += ($dayIncome - $dayExpense);

            // Guardamos totales/foot del día
            $rowsByDay[$d] = [
                'rows'        => $dayRows,
                'sum_ingreso' => $dayIncome,
                'sum_egreso'  => $dayExpense,
                'saldo_final' => $running,
            ];

            $this->totalIncomes += $dayIncome;
            $this->totalExpenses += $dayExpense;
        }

        $this->finalBalance = $this->carryFromPrevMonth + $this->totalIncomes - $this->totalExpenses;

        return view('livewire.cash.general-report', [
            'rowsByDay' => $rowsByDay,
        ]);
    }
}
