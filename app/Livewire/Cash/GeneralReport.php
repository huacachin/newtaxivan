<?php

namespace App\Livewire\Cash;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
class GeneralReport extends Component
{
    // Filtros
    public string $month = '';                 // ej. "2025-09"
    public bool   $onlyActive = true;         // por si luego filtras vehículos/usuarios activos

    // Datos calculados
    public array  $weeks = [];                // rangos de semanas del mes
    public array  $sections = [];             // bloques semanales con movimientos y sumas
    public float  $grandIncome = 0.0;
    public float  $grandExpense = 0.0;
    public float  $grandProfit = 0.0;

    // Mapas útiles
    protected array $userMap = [];            // id => name
    protected array $hqMap   = [];            // id => name (si existe tabla headquarters)

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->buildMaps();
        $this->computeWeeks();
        $this->recalc();
    }

    public function updatedMonth(): void
    {
        $this->computeWeeks();
        $this->recalc();
    }

    public function prevMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->subMonth()->format('Y-m');
        $this->computeWeeks();
        $this->recalc();
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->addMonth()->format('Y-m');
        $this->computeWeeks();
        $this->recalc();
    }

    public function render()
    {
        return view('livewire.cash.general-report');
    }

    /* ================== Helpers ================== */

    protected function buildMaps(): void
    {
        // users
        if (Schema::hasTable('users')) {
            DB::table('users')->select('id','name')->orderBy('id')->chunk(1000, function($rows){
                foreach ($rows as $r) {
                    $this->userMap[(int)$r->id] = (string)$r->name;
                }
            });
        }

        // headquarters (si existe)
        if (Schema::hasTable('headquarters')) {
            DB::table('headquarters')->select('id','name')->orderBy('id')->chunk(1000, function($rows){
                foreach ($rows as $r) {
                    $this->hqMap[(int)$r->id] = (string)$r->name;
                }
            });
        }
    }

    protected function computeWeeks(): void
    {
        $this->weeks = [];

        $mStart = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
        $mEnd   = (clone $mStart)->endOfMonth();

        // semanas Lunes-Domingo dentro del mes
        $cursor = (clone $mStart)->startOfWeek(Carbon::MONDAY);
        $idx = 1;

        while ($cursor <= $mEnd) {
            $wStart = (clone $cursor);
            $wEnd   = (clone $cursor)->endOfWeek(Carbon::SUNDAY);

            // recortar al mes
            if ($wEnd < $mStart) { $cursor = $cursor->addWeek(); continue; }
            $rangeStart = $wStart->greaterThan($mStart) ? $wStart : $mStart;
            $rangeEnd   = $wEnd->lessThan($mEnd) ? $wEnd : $mEnd;

            $this->weeks[] = [
                'i'     => $idx,
                'start' => $rangeStart->toDateString(),
                'end'   => $rangeEnd->toDateString(),
                'label' => sprintf('Semana %d (%s–%s)', $idx, $rangeStart->format('d'), $rangeEnd->format('d')),
            ];

            $idx++;
            $cursor = $cursor->addWeek();
        }
    }

    public function recalc(): void
    {
        $this->sections = [];
        $this->grandIncome = 0.0;
        $this->grandExpense = 0.0;
        $this->grandProfit = 0.0;

        // ¿existen tablas?
        $hasPayments   = Schema::hasTable('payments');
        $hasDepartures = Schema::hasTable('departures');
        $hasIncomes    = Schema::hasTable('incomes');
        $hasExpenses   = Schema::hasTable('expenses');

        // departures: decide columna a sumar (evitar Expression concatenation)
        $depCol = null;          // 'price' | 'amount' | 'COALESCE(price, amount)'
        $depSumExpr = null;      // 'SUM(...)' listo para selectRaw
        if ($hasDepartures) {
            $hasPrice  = Schema::hasColumn('departures', 'price');
            $hasAmount = Schema::hasColumn('departures', 'amount');
            if ($hasPrice && $hasAmount) {
                $depCol     = 'COALESCE(price, amount)';
                $depSumExpr = 'SUM(COALESCE(price, amount))';
            } elseif ($hasPrice) {
                $depCol     = 'price';
                $depSumExpr = 'SUM(price)';
            } elseif ($hasAmount) {
                $depCol     = 'amount';
                $depSumExpr = 'SUM(amount)';
            }
        }

        foreach ($this->weeks as $wk) {
            $wStart = $wk['start'];
            $wEnd   = $wk['end'];

            $rows = [];

            /* ====== PAYMENTS (ingresos) — usar date_register ====== */
            if ($hasPayments) {
                $pQuery = DB::table('payments as p')
                    ->whereBetween('p.date_register', [$wStart, $wEnd])
                    ->select('p.date_register as date','p.user_id','p.headquarter_id','p.type')
                    ->selectRaw('SUM(p.amount) as total')
                    ->groupBy('p.date_register','p.user_id','p.headquarter_id','p.type');

                $pData = $pQuery->get()->map(function($r){
                    $user = $this->userMap[$r->user_id] ?? '-';
                    $hq   = $this->hqMap[$r->headquarter_id] ?? ($r->headquarter_id ? ('HQ#'.$r->headquarter_id) : '-');
                    return [
                        'date'    => (string)$r->date,
                        'user'    => $user,
                        'source'  => 'Pago'.($r->type ? " ({$r->type})" : ''),
                        'detail'  => $hq,
                        'income'  => (float)$r->total,
                        'expense' => 0.0,
                    ];
                })->all();

                $rows = array_merge($rows, $pData);
            }

            /* ====== DEPARTURES (ingresos) — usar d.date ====== */
            if ($hasDepartures && $depSumExpr) {
                $dQuery = DB::table('departures as d')
                    ->whereBetween('d.date', [$wStart, $wEnd])
                    ->select('d.date','d.user_id','d.headquarter_id')
                    ->selectRaw("$depSumExpr as total")
                    ->groupBy('d.date','d.user_id','d.headquarter_id');

                $dData = $dQuery->get()->map(function($r){
                    $user = $this->userMap[$r->user_id] ?? '-';
                    $hq   = $this->hqMap[$r->headquarter_id] ?? ($r->headquarter_id ? ('HQ#'.$r->headquarter_id) : '-');
                    return [
                        'date'    => (string)$r->date,
                        'user'    => $user,
                        'source'  => 'Salida',
                        'detail'  => $hq,
                        'income'  => (float)$r->total,
                        'expense' => 0.0,
                    ];
                })->all();

                $rows = array_merge($rows, $dData);
            }

            /* ====== INCOMES (ingresos) — tabla incomes ====== */
            if ($hasIncomes) {
                $iData = DB::table('incomes as i')
                    ->whereBetween('i.date', [$wStart, $wEnd])
                    ->select('i.date','i.user_id','i.reason','i.detail','i.total')
                    ->orderBy('i.date')
                    ->get()
                    ->map(function($r){
                        $user = $this->userMap[$r->user_id] ?? '-';
                        $glosa = trim(implode(' - ', array_filter([(string)$r->reason, (string)$r->detail])));
                        return [
                            'date'    => (string)$r->date,
                            'user'    => $user,
                            'source'  => 'Ingreso',
                            'detail'  => $glosa !== '' ? $glosa : 'Ingreso',
                            'income'  => (float)$r->total,
                            'expense' => 0.0,
                        ];
                    })->all();

                $rows = array_merge($rows, $iData);
            }

            /* ====== EXPENSES (egresos) — tabla expenses ====== */
            if ($hasExpenses) {
                $eData = DB::table('expenses as e')
                    ->whereBetween('e.date', [$wStart, $wEnd])
                    ->select('e.date','e.user_id','e.reason','e.detail','e.total','e.document_type','e.in_charge')
                    ->orderBy('e.date')
                    ->get()
                    ->map(function($r){
                        $user = $this->userMap[$r->user_id] ?? '-';
                        $parts = [(string)$r->reason, (string)$r->detail];
                        if (!empty($r->document_type)) $parts[] = 'Doc: '.$r->document_type;
                        if (!empty($r->in_charge))     $parts[] = 'Resp: '.$r->in_charge;
                        $glosa = trim(implode(' - ', array_filter($parts)));
                        return [
                            'date'    => (string)$r->date,
                            'user'    => $user,
                            'source'  => 'Gasto',
                            'detail'  => $glosa !== '' ? $glosa : 'Gasto',
                            'income'  => 0.0,
                            'expense' => (float)$r->total,
                        ];
                    })->all();

                $rows = array_merge($rows, $eData);
            }

            // Orden por fecha
            usort($rows, function($a,$b){
                if ($a['date'] === $b['date']) return $a['source'] <=> $b['source'];
                return $a['date'] <=> $b['date'];
            });

            // Sumas de la semana
            $inc = 0.0; $exp = 0.0;
            foreach ($rows as $rr) {
                $inc += $rr['income'];
                $exp += $rr['expense'];
            }
            $profit = $inc - $exp;

            $this->sections[] = [
                'label'   => $wk['label'],
                'start'   => $wStart,
                'end'     => $wEnd,
                'rows'    => $rows,
                'summary' => [
                    'income'  => $inc,
                    'expense' => $exp,
                    'profit'  => $profit,
                ],
            ];

            // Acumulados del mes
            $this->grandIncome  += $inc;
            $this->grandExpense += $exp;
            $this->grandProfit  += $profit;
        }
    }
}
