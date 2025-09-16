<?php

namespace App\Livewire\Debts;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

use Illuminate\Support\Str;
use Livewire\Component;

class DebtPerDays extends Component
{
    /** Filtros */
    public string $monthDate = '';   // cualquier fecha del mes (YYYY-mm-dd)
    public bool   $onlyActive = true;
    public string $condition  = '';  // '', 'DT', 'GN', 'EX', 'EX5', etc.

    /** Datos calculados para la vista */
    public array $rows = [];       // filas de la grilla
    public array $summary = [];    // totales generales
    public array $days = [];       // cabecera de días del mes
    public array $dayTotals = [];  // totales por día (conteo de P y montos)

    public function mount(?string $monthDate = null, ?bool $onlyActive = true, ?string $condition = '')
    {
        $this->monthDate  = $monthDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $monthDate)
            ? $monthDate : now()->toDateString();
        $this->onlyActive = (bool) ($onlyActive ?? true);
        $this->condition  = (string) ($condition ?? '');
    }

    /** Navegación de mes */
    public function prevMonth(): void
    {
        $this->monthDate = Carbon::parse($this->monthDate)->subMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->monthDate = Carbon::parse($this->monthDate)->addMonth()->toDateString();
    }

    /** Recalcular al cambiar filtros */
    public function updated($name): void
    {
        if (in_array($name, ['monthDate','onlyActive','condition'], true)) {
            // no hacemos nada especial; Livewire llamará a render()
        }
    }

    public function render()
    {
        $this->build();
        return view('livewire.debts.debt-per-days');
    }

    // =========================================================
    // ===================== CORE LÓGICA =======================
    // =========================================================

    private function build(): void
    {
        [$from, $toMonthEnd] = $this->monthBoundaries($this->monthDate);

        // Si es mes actual, cortar en hoy; si no, fin de mes
        $today   = now()->toDateString();
        $isCurr  = Carbon::parse($from)->isSameMonth($today);
        $cutoff  = $isCurr ? min($today, $toMonthEnd) : $toMonthEnd;

        // Días del mes (marcando domingos)
        $this->days = $this->makeDays($from, $toMonthEnd);

        // --- Vehículos a considerar ---
        $vehiclesQ = DB::table('vehicles as v')
            ->select('v.id','v.plate','v.sort_order','v.condition','v.status');

        if ($this->onlyActive) {
            $vehiclesQ->where('v.status','active');
        }
        if ($this->condition !== '') {
            $vehiclesQ->where('v.condition', $this->condition);
        }

        $vehicles = $vehiclesQ
            ->orderByRaw('COALESCE(v.sort_order, 999999)')
            ->orderBy('v.plate')
            ->get();

        if ($vehicles->isEmpty()) {
            $this->rows    = [];
            $this->summary = ['paid_days'=>0,'paid_amount'=>0.0,'debt_days'=>0,'debt_amount'=>0.0];
            $this->dayTotals = [];
            return;
        }

        $vehicleIds = $vehicles->pluck('id')->all();

        // --- Costos por placa/día (todo el mes) ---
        $costs = DB::table('cost_per_plate_days as c')
            ->select('c.vehicle_id','c.date', DB::raw('SUM(c.amount) as amount'))
            ->whereIn('c.vehicle_id', $vehicleIds)
            ->whereBetween('c.date', [$from, $toMonthEnd])
            ->groupBy('c.vehicle_id','c.date')
            ->get();

        $costMap = [];
        foreach ($costs as $c) {
            $costMap[$c->vehicle_id][$c->date] = (float)$c->amount;
        }

        // --- Pagos por día (al menos 1 pago; excluye DEUDA) ---
        $payDay = DB::table('payments as p')
            ->select('p.vehicle_id', DB::raw('DATE(p.date_payment) as date'))
            ->whereIn('p.vehicle_id', $vehicleIds)
            ->where('p.type','<>','DEUDA')
            ->whereBetween(DB::raw('DATE(p.date_payment)'), [$from, $toMonthEnd])
            ->groupBy('p.vehicle_id', DB::raw('DATE(p.date_payment)'))
            ->get();

        $payExists = [];
        foreach ($payDay as $p) {
            $payExists[$p->vehicle_id][$p->date] = true;
        }

        // --- Salidas por día (sum(times)) excluyendo Huachipa / Lima ---
        $deps = DB::table('departures as d')
            ->leftJoin('headquarters as h','h.id','=','d.headquarter_id')
            ->select('d.vehicle_id','d.date', DB::raw('SUM(d.times) as k1'))
            ->whereIn('d.vehicle_id', $vehicleIds)
            ->whereBetween('d.date', [$from, $toMonthEnd])
            ->where(function($q){
                $q->whereNull('h.name')->orWhereNotIn('h.name', ['Huachipa','Lima']);
            })
            ->groupBy('d.vehicle_id','d.date')
            ->get();

        $depMap = [];
        foreach ($deps as $d) {
            $depMap[$d->vehicle_id][$d->date] = (int)$d->k1;
        }

        // --- Totales esperados (hasta cutoff, sin domingos) ---
        $expectedTotals = DB::table('cost_per_plate_days as c')
            ->select('c.vehicle_id', DB::raw('SUM(c.amount) as amt'))
            ->whereIn('c.vehicle_id', $vehicleIds)
            ->whereBetween('c.date', [$from, $cutoff])
            ->whereRaw('DAYOFWEEK(c.date) <> 1') // 1 = domingo
            ->groupBy('c.vehicle_id')
            ->pluck('amt','vehicle_id');

        // --- Inicializar totales por día (para el tfoot) ---
        $dayTotals = [];
        foreach ($this->days as $d) {
            $dayTotals[$d['d']] = [
                'p_count'     => 0,   // cantidad de "P" ese día
                'paid_amount' => 0.0, // monto acumulado de pagos (calculado por costo del día)
                'debt_count'  => 0,   // cantidad de días con deuda (celdas numéricas)
                'debt_amount' => 0.0, // monto acumulado de deuda ese día
            ];
        }

        // --- Construir filas ---
        $rows = [];
        $sumPaidDays = 0;   $sumPaidAmount = 0.0;
        $sumDebtDays = 0;   $sumDebtAmount = 0.0;
        $item = 0;

        foreach ($vehicles as $v) {
            $item++;
            $isExempt = Str::startsWith((string)$v->condition, 'EX'); // EX, EX5, ...

            $row = [
                'item'        => $item,
                'cod'         => $v->sort_order,
                'plate'       => $v->plate,
                'condition'   => $v->condition,
                'cells'       => [],
                'paid_days'   => 0,
                'paid_amount' => 0.0,
                'debt_days'   => 0,
                'debt_amount' => 0.0,
            ];

            foreach ($this->days as $d) {
                $date = $d['d'];

                // Domingos: no cuentan, celda vacía
                if ($d['isSunday']) {
                    $row['cells'][] = ['txt'=>'', 'class'=>''];
                    continue;
                }

                // Exonerados: se muestra "NT" como en legacy, sin afectar totales
                if ($isExempt) {
                    $row['cells'][] = ['txt'=>'NT', 'class'=>'nopay'];
                    continue;
                }

                $cost = (float)($costMap[$v->id][$date] ?? 0.0);

                // 1) Si hay al menos un pago → "P"
                if (!empty($payExists[$v->id][$date])) {
                    $row['cells'][] = ['txt'=>'P', 'class'=>'paid'];

                    if ($date <= $cutoff) {
                        $row['paid_days']++;
                        $row['paid_amount'] += $cost;
                        $sumPaidDays++;
                        $sumPaidAmount += $cost;

                        $dayTotals[$date]['p_count']++;
                        $dayTotals[$date]['paid_amount'] += $cost;
                    }
                    continue;
                }

                // 2) Sin pago → ver salidas (mostrar K exacto como informativo)
                $k = (int)($depMap[$v->id][$date] ?? 0);
                if ($k > 0) {
                    $row['cells'][] = ['txt'=>(string)$k, 'class'=>'freq'];
                    if ($date <= $cutoff) {
                        $row['debt_days']++;
                        $row['debt_amount'] += $cost;
                        $sumDebtDays++;
                        $sumDebtAmount += $cost;

                        $dayTotals[$date]['debt_count']++;
                        $dayTotals[$date]['debt_amount'] += $cost;
                    }
                } else {
                    // 3) Sin pago y sin salidas → NT
                    $row['cells'][] = ['txt'=>'NT', 'class'=>'nopay'];
                }
            }

            // Redondeos finales (EX ya es 0)
            if (!$isExempt) {
                $row['paid_amount'] = round($row['paid_amount'], 2);
                $row['debt_amount'] = round($row['debt_amount'], 2);
            }

            $rows[] = $row;
        }

        $this->rows = $rows;
        $this->summary = [
            'paid_days'   => $sumPaidDays,
            'paid_amount' => round($sumPaidAmount, 2),
            'debt_days'   => $sumDebtDays,
            'debt_amount' => round($sumDebtAmount, 2),
        ];
        $this->dayTotals = $dayTotals;
    }

    // ======================= Helpers =========================

    private function monthBoundaries(string $anyDay): array
    {
        $d = Carbon::parse($anyDay)->startOfMonth();
        return [$d->toDateString(), $d->copy()->endOfMonth()->toDateString()];
    }

    private function makeDays(string $from, string $to): array
    {
        $days = [];
        $c = Carbon::parse($from);
        $end = Carbon::parse($to);
        while ($c->lte($end)) {
            $days[] = [
                'd' => $c->toDateString(),         // 2025-09-01
                'n' => (int)$c->format('j'),       // 1..31
                'isSunday' => $c->dayOfWeekIso === 7, // 7 = Domingo
            ];
            $c->addDay();
        }
        return $days;
    }
}
