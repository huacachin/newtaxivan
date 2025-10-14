<?php

namespace App\Livewire\Cash;

use App\Models\Departure;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GeneralReport extends Component
{
    /** Filtros */
    public int $year;
    public int $month;

    /** Utilidades de la vista */
    public array $days = [];                // Lista de YYYY-MM-DD del mes seleccionado
    public float $totalIncomes = 0.0;       // Suma de ingresos del mes
    public float $totalExpenses = 0.0;      // Suma de egresos del mes
    public float $finalBalance = 0.0;       // Utilidad del mes (suma de saldos diarios)

    /** Caches */
    protected Collection $hqNames;          // headquarter_id => name
    protected Collection $userMap;          // user_id => "Nombre · DOC"

    public function mount(?int $year = null, ?int $month = null): void
    {
        $today = now();
        $this->year  = $year  ?: (int) $today->year;
        $this->month = $month ?: (int) $today->month;

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

    /**
     * Prepara días del mes y caches (HQs y Users).
     */
    protected function prepareStatic(): void
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        // Días del mes
        $this->days = [];
        for ($d = (clone $start); $d->lte($end); $d->addDay()) {
            $this->days[] = $d->toDateString(); // YYYY-MM-DD
        }

        // HQs
        $this->hqNames = Headquarter::query()
            ->pluck('name', 'id');

        // Users: nombre + documento (si existe)
        $this->userMap = User::query()
            ->select('id', 'name', 'document_type', 'document_number')
            ->get()
            ->mapWithKeys(function ($u) {
                $doc = trim(($u->document_type ?? '') . ' ' . ($u->document_number ?? ''));
                $label = trim($u->name . ($doc ? " · $doc" : ''));
                return [$u->id => ($label !== '' ? $label : '—')];
            });

        // Reset totales
        $this->totalIncomes  = 0.0;
        $this->totalExpenses = 0.0;
        $this->finalBalance  = 0.0;
    }

    /**
     * Carga en bloque los datos del mes, agrupando donde corresponde.
     *
     * payments: agrupado por día, type, headquarter_id y user_id (usa date_register)
     * departures: agrupado por día, headquarter_id y user_id (usa date)
     * incomes/expenses: filas individuales (incluye user_id)
     */
    protected function loadMonthBatches(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth()->toDateString();
        $end   = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();

        // PAYMENTS (ingresos)
        $payments = Payment::query()
            ->selectRaw('DATE(date_register) AS d, type, headquarter_id, user_id, SUM(amount) AS amount')
            ->whereBetween(DB::raw('DATE(date_register)'), [$start, $end])
            ->groupBy('d', 'type', 'headquarter_id', 'user_id')
            ->get()
            ->groupBy('d');

        // DEPARTURES (ingresos)
        $departures = Departure::query()
            ->selectRaw('DATE(date) AS d, headquarter_id, user_id, SUM(price) AS amount')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->groupBy('d', 'headquarter_id', 'user_id')
            ->get()
            ->groupBy('d');

        // INCOMES (filas)
        $incomes = Income::query()
            ->selectRaw('id, DATE(date) AS d, reason, detail, total, user_id')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->orderBy('date')
            ->get()
            ->groupBy('d');

        // EXPENSES (filas)
        $expenses = Expense::query()
            ->selectRaw('id, DATE(date) AS d, reason, detail, total, user_id')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->orderBy('date')
            ->get()
            ->groupBy('d');

        return compact('payments', 'departures', 'incomes', 'expenses');
    }

    public function render()
    {
        $batches = $this->loadMonthBatches();

        $rowsByDay = [];

        $this->totalIncomes   = 0.0;
        $this->totalExpenses  = 0.0;
        $runningAccumulated   = 0.0; // saldo acumulado dentro del mes (se suma día a día)

        foreach ($this->days as $d) {
            $dayRows    = [];
            $dayIncome  = 0.0;
            $dayExpense = 0.0;

            // PAYMENTS (ingresos) — agrupados por type + HQ + user
            foreach (($batches['payments'][$d] ?? collect()) as $p) {
                $hqName  = $this->hqNames->get($p->headquarter_id, '—');
                $cliente = $this->userMap->get($p->user_id, '—');
                $glosa   = sprintf('%s-%s', strtoupper($p->type), $hqName);
                $amount  = (float) $p->amount;

                $dayRows[] = [
                    'date'    => $d,
                    'cliente' => $cliente,
                    'glosa'   => $glosa,
                    'ingreso' => $amount,
                    'egreso'  => 0.0,
                ];
                $dayIncome += $amount;
            }

            // DEPARTURES (ingresos) — agrupados por HQ + user
            foreach (($batches['departures'][$d] ?? collect()) as $dep) {
                $hqName  = $this->hqNames->get($dep->headquarter_id, '—');
                $cliente = $this->userMap->get($dep->user_id, '—');
                $glosa   = sprintf('Salidas-%s', $hqName);
                $amount  = (float) $dep->amount;

                $dayRows[] = [
                    'date'    => $d,
                    'cliente' => $cliente,
                    'glosa'   => $glosa,
                    'ingreso' => $amount,
                    'egreso'  => 0.0,
                ];
                $dayIncome += $amount;
            }

            // INCOMES (filas individuales)
            foreach (($batches['incomes'][$d] ?? collect()) as $inc) {
                $cliente = $this->userMap->get($inc->user_id, '—');
                $glosa   = trim(($inc->reason ?? '') . ' - ' . ($inc->detail ?? ''), ' - ');
                $amount  = (float) $inc->total;

                $dayRows[] = [
                    'date'    => $d,
                    'cliente' => $cliente,
                    'glosa'   => $glosa,
                    'ingreso' => $amount,
                    'egreso'  => 0.0,
                ];
                $dayIncome += $amount;
            }

            // EXPENSES (filas individuales)
            foreach (($batches['expenses'][$d] ?? collect()) as $exp) {
                $cliente = $this->userMap->get($exp->user_id, '—');
                $glosa   = trim(($exp->reason ?? '') . ' - ' . ($exp->detail ?? ''), ' - ');
                $amount  = (float) $exp->total;

                $dayRows[] = [
                    'date'    => $d,
                    'cliente' => $cliente,
                    'glosa'   => $glosa,
                    'ingreso' => 0.0,
                    'egreso'  => $amount,
                ];
                $dayExpense += $amount;
            }

            // Saldo del día y acumulado del mes (acumulado se suma día a día)
            $dayBalance = $dayIncome - $dayExpense;
            $runningAccumulated += $dayBalance;

            $rowsByDay[$d] = [
                'rows'        => $dayRows,
                'sum_ingreso' => $dayIncome,
                'sum_egreso'  => $dayExpense,
                'saldo_dia'   => $dayBalance,
                'saldo_acum'  => $runningAccumulated,
            ];

            $this->totalIncomes  += $dayIncome;
            $this->totalExpenses += $dayExpense;
        }

        // Utilidad del mes = suma de saldos diarios = último acumulado
        $this->finalBalance = $runningAccumulated;

        return view('livewire.cash.general-report', [
            'rowsByDay' => $rowsByDay,
        ]);
    }
}
