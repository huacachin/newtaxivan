<?php

namespace App\Livewire\Cash;

use App\Models\Departure;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class GeneralReport extends Component
{
    /** ========= Filtros (enlazados a la URL) ========= */
    #[Url(except: null, history: true, keep: true)]
    public ?int $year = null;

    #[Url(except: null, history: true, keep: true)]
    public ?int $month = null;

    /** ========= Estado para la vista ========= */
    public array $days = [];            // Lista de YYYY-MM-DD del mes
    public float $totalIncomes = 0.0;   // Total ingresos del mes
    public float $totalExpenses = 0.0;  // Total egresos del mes
    public float $finalBalance = 0.0;   // Utilidad del mes (saldo acumulado final)

    /** ========= Caches persistentes (arrays para Livewire) ========= */
    public array $hqNames = [];   // [headquarter_id => name]
    public array $userMap = [];   // [user_id => "Nombre · DOC"]

    /** ========= Lifecycle ========= */
    public function mount(): void
    {
        // Si NO hay query string, usar año/mes actuales
        if ($this->year === null || $this->month === null) {
            $today = now();
            $this->year  ??= (int)$today->year;
            $this->month ??= (int)$today->month;
        }

        // Saneamiento por si llega algo fuera de rango en la URL
        $this->month = max(1, min(12, (int)$this->month));
        $this->year  = (int)$this->year;

        $this->prepareStatic();
    }

    public function updatedYear(): void  { $this->prepareStatic(); }
    public function updatedMonth(): void { $this->prepareStatic(); }

    /** ========= Preparación de días y caches ========= */
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
        $this->hqNames = Headquarter::pluck('name', 'id')->toArray();

        // Users: username
        $this->userMap = User::select('id', 'username')
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => $u->username ?: '—'])
            ->all();

        // Reset de totales
        $this->totalIncomes  = 0.0;
        $this->totalExpenses = 0.0;
        $this->finalBalance  = 0.0;
    }

    /** ========= Carga en bloque del mes =========
     * payments: agrupado por día, type, headquarter_id y user_id (date_register)
     * departures: agrupado por día, headquarter_id y user_id (date)
     * incomes/expenses: filas individuales (incluye user_id)
     */
    protected function loadMonthBatches(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth()->toDateString();
        $end   = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();

        $payments = Payment::query()
            ->selectRaw('DATE(date_register) AS d, type, headquarter_id, user_id, SUM(amount) AS amount')
            ->whereBetween(\DB::raw('DATE(date_register)'), [$start, $end])
            ->groupBy('d', 'type', 'headquarter_id', 'user_id')
            ->get()
            ->groupBy('d');

        $departures = Departure::query()
            ->selectRaw('DATE(date) AS d, headquarter_id, user_id, SUM(price) AS amount')
            ->whereBetween(\DB::raw('DATE(date)'), [$start, $end])
            ->groupBy('d', 'headquarter_id', 'user_id')
            ->get()
            ->groupBy('d');

        $incomes = Income::query()
            ->selectRaw('id, DATE(date) AS d, reason, detail, total, user_id')
            ->whereBetween(\DB::raw('DATE(date)'), [$start, $end])
            ->orderBy('date')
            ->get()
            ->groupBy('d');

        $expenses = Expense::query()
            ->selectRaw('id, DATE(date) AS d, reason, detail, total, user_id')
            ->whereBetween(\DB::raw('DATE(date)'), [$start, $end])
            ->orderBy('date')
            ->get()
            ->groupBy('d');

        return compact('payments', 'departures', 'incomes', 'expenses');
    }

    /** ========= Render principal ========= */
    public function render()
    {
        $batches = $this->loadMonthBatches();

        $rowsByDay = [];
        $this->totalIncomes   = 0.0;
        $this->totalExpenses  = 0.0;
        $runningAccumulated   = 0.0; // saldo acumulado dentro del mes (día a día)

        foreach ($this->days as $d) {
            $dayRows    = [];
            $dayIncome  = 0.0;
            $dayExpense = 0.0;

            // PAYMENTS (ingresos)
            foreach (($batches['payments'][$d] ?? collect()) as $p) {
                $hqName  = $this->hqNames[$p->headquarter_id] ?? '—';
                $cliente = $this->userMap[$p->user_id]       ?? '—';
                $glosa   = strtoupper($p->type) . '-' . $hqName;
                $amount  = (float)$p->amount;

                $dayRows[] = [
                    'date'    => $d,
                    'cliente' => $cliente,
                    'glosa'   => $glosa,
                    'ingreso' => $amount,
                    'egreso'  => 0.0,
                ];
                $dayIncome += $amount;
            }

            // DEPARTURES (ingresos)
            foreach (($batches['departures'][$d] ?? collect()) as $dep) {
                $hqName  = $this->hqNames[$dep->headquarter_id] ?? '—';
                $cliente = $this->userMap[$dep->user_id]        ?? '—';
                $glosa   = 'Salidas-' . $hqName;
                $amount  = (float)$dep->amount;

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
                $cliente = $this->userMap[$inc->user_id] ?? '—';
                $glosa   = trim(($inc->reason ?? '') . ' - ' . ($inc->detail ?? ''), ' - ');
                $amount  = (float)$inc->total;

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
                $cliente = $this->userMap[$exp->user_id] ?? '—';
                $glosa   = trim(($exp->reason ?? '') . ' - ' . ($exp->detail ?? ''), ' - ');
                $amount  = (float)$exp->total;

                $dayRows[] = [
                    'date'    => $d,
                    'cliente' => $cliente,
                    'glosa'   => $glosa,
                    'ingreso' => 0.0,
                    'egreso'  => $amount,
                ];
                $dayExpense += $amount;
            }

            // Saldos
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

        // Utilidad del mes = último acumulado (ingresos totales − egresos totales)
        $this->finalBalance = $runningAccumulated;

        return view('livewire.cash.general-report', [
            'rowsByDay' => $rowsByDay,
        ]);
    }

    /** ========= Export: abre la ruta que genera el Excel =========
     * Ruta esperada (GET): route('exports.cash-general-report', ['year' => ..., 'month' => ...])
     * En el front escucha:
     *   Livewire.on('url-open', ({ url }) => window.open(url, '_blank'))
     */
    public function export(): void
    {
        $route = route('exports.cash-general-report', [
            'year'  => $this->year,
            'month' => $this->month,
        ]);

        $this->dispatch('url-open', ['url' => $route]);
    }
}
