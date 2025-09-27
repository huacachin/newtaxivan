<?php
// app/Livewire/Payments/Daily.php

namespace App\Livewire\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;

class Daily extends Component
{
    #[Url(except: null)] public ?int $year  = null;      // 1..N
    #[Url(except: null)] public ?int $month = null;      // 1..12
    #[Url(except: null)] public string $mode = 'Pago';   // 'Pago' | 'Caja'

    public int $daysInMonth = 30;

    // Por fila
    public array $rows = [];             // order, plate, cond, days[1..N], total, days_paid, debt_days, debt_amount, real_debt_amount
    // Totales
    public array $totalsPerDay = [];     // suma por día (S/)
    public float|int $grandTotal = 0;    // suma total del mes (S/)
    public int $sumDaysPaid = 0;         // footer: Total Pagos (conteo de días)
    public int $sumDebtDays = 0;         // footer: deuda días
    public float $sumDebtAmount = 0.0;   // footer: deuda S/
    public float $sumRealDebtAmount = 0.0; // footer: deuda real S/

    // Config
    protected string $amountCol = 'amount';
    protected string $costTable = 'cost_per_plate_days';

    public function mount(): void
    {
        $now = CarbonImmutable::now();
        $this->year  = $this->year  ?: (int) $now->year;
        $this->month = $this->month ?: (int) $now->month;
        $this->loadData();
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['year','month','mode'], true)) {
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
        return view('livewire.payments.daily', compact('years','months'));
    }

    public function export(){
        $route = route('exports.payments-daily',
            [   "year" => $this->year,
                "month" => $this->month,
            ]);

        $this->dispatch('url-open',["url" => $route]);
    }

    protected function loadData(): void
    {
        $this->rows = [];
        $this->totalsPerDay = [];
        $this->grandTotal = 0;
        $this->sumDaysPaid = 0;
        $this->sumDebtDays = 0;
        $this->sumDebtAmount = 0.0;
        $this->sumRealDebtAmount = 0.0;

        if (!Schema::hasTable('vehicles') || !Schema::hasTable('payments')) return;

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();
        $this->daysInMonth = (int) $start->daysInMonth;

        // ===== Vehículos activos =====
        $orderCol = Schema::hasColumn('vehicles','sort_order')
            ? 'sort_order'
            : (Schema::hasColumn('vehicles','plate') ? 'plate' : 'id');

        $vehCols = ['id','plate','status'];
        if (Schema::hasColumn('vehicles','sort_order')) $vehCols[] = 'sort_order';
        if (Schema::hasColumn('vehicles','condition'))  $vehCols[] = 'condition';

        $vehicles = DB::table('vehicles')
            ->where('status', 'active')
            ->select($vehCols)
            ->orderBy($orderCol)
            ->get();

        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'order'     => (string)($v->sort_order ?? ''),
                'plate'     => (string)$v->plate,
                'cond'      => (string)($v->condition ?? ''),
                'days'      => array_fill(1, $this->daysInMonth, 0.0),
                'total'     => 0.0,
                'days_paid' => 0,
                'debt_days' => 0,
                'debt_amount' => 0.0,        // Total Deuda (costos por días sin pago, sin domingos; EX=0)
                'real_debt_amount' => 0.0,   // Deuda real según condición
            ];
        }

        $amountCol = $this->amountCol;

        // ===== Importes por día para la tabla (columna "days") =====
        if ($this->mode === 'Pago') {
            $dateCol = 'date_payment';
            $aggs = DB::table('payments as p')
                ->leftJoin('vehicles as v2', function ($join) {
                    $join->on('v2.plate', '=', 'p.legacy_plate')
                        ->where('v2.status', 'active');
                })
                ->selectRaw("
                    COALESCE(p.vehicle_id, v2.id) as vid,
                    DAY($dateCol) as d,
                    SUM(p.$amountCol) as s
                ")
                ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
                ->whereNotNull($dateCol)
                ->whereBetween($dateCol, [$start->toDateString(), $end->toDateString()])
                ->groupBy('vid', 'd')
                ->get();
        } else {
            $dateCol = 'date_register';
            $aggs = DB::table('payments as p')
                ->leftJoin('vehicles as v2', function ($join) {
                    $join->on('v2.plate', '=', 'p.legacy_plate')
                        ->where('v2.status', 'active');
                })
                ->selectRaw("
                    COALESCE(p.vehicle_id, v2.id) as vid,
                    DAY($dateCol) as d,
                    SUM(p.$amountCol) as s
                ")
                ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO','DEUDA'])
                ->whereNotNull($dateCol)
                ->whereBetween($dateCol, [$start->toDateString(), $end->toDateString()])
                ->groupBy('vid', 'd')
                ->get();
        }

        foreach ($aggs as $r) {
            $vid = (int) $r->vid;
            $day = (int) $r->d;
            $sum = (float) $r->s;
            if (!isset($this->rows[$vid])) continue;
            if ($day < 1 || $day > $this->daysInMonth) continue;
            $this->rows[$vid]['days'][$day] = $sum;
        }

        // ===== DÍAS pagados (PAGO/RETRASO) sin domingos, para "Dias Pag" =====
        $paidDateCol = $this->mode === 'Pago' ? 'date_payment' : 'date_register';
        $paidDaysAgg = DB::table('payments as p')
            ->leftJoin('vehicles as v2', function ($join) {
                $join->on('v2.plate', '=', 'p.legacy_plate')
                    ->where('v2.status', 'active');
            })
            ->selectRaw("COALESCE(p.vehicle_id, v2.id) as vid, DAY($paidDateCol) as d")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull($paidDateCol)
            ->whereBetween($paidDateCol, [$start->toDateString(), $end->toDateString()])
            ->whereRaw("DAYOFWEEK($paidDateCol) <> 1")
            ->groupBy('vid','d')
            ->get();

        $paidDaysByVehicle = [];
        foreach ($paidDaysAgg as $p) {
            $vid = (int) $p->vid;
            $d   = (int) $p->d;
            if (!isset($this->rows[$vid])) continue;
            $paidDaysByVehicle[$vid][$d] = true;
        }

        // ===== Suma de pagos del mes (PAGO/RETRASO) — SIN excluir domingos =====
        $paidSumAgg = DB::table('payments as p')
            ->leftJoin('vehicles as v2', function ($join) {
                $join->on('v2.plate', '=', 'p.legacy_plate')
                    ->where('v2.status', 'active');
            })
            ->selectRaw("COALESCE(p.vehicle_id, v2.id) as vid, SUM(p.$amountCol) as s")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull($paidDateCol)
            ->whereBetween($paidDateCol, [$start->toDateString(), $end->toDateString()])
            ->groupBy('vid')
            ->get();

        $paidSumByVehicle = [];
        foreach ($paidSumAgg as $pa) {
            $paidSumByVehicle[(int)$pa->vid] = (float) $pa->s;
        }

        // ===== Costos por día (sin domingos) para Total Deuda =====
        $costsByVehicle = [];
        if (Schema::hasTable($this->costTable)) {
            $costs = DB::table($this->costTable)
                ->selectRaw("vehicle_id, DAY(`date`) as d, SUM(amount) as a")
                ->where('year', $this->year)
                ->where('month', $this->month)
                ->whereRaw("DAYOFWEEK(`date`) <> 1")
                ->groupBy('vehicle_id','d')
                ->get();

            foreach ($costs as $c) {
                $vid = (int) $c->vehicle_id;
                $d   = (int) $c->d;
                $a   = (float) $c->a;
                $costsByVehicle[$vid][$d] = $a;
            }
        }

        // ===== Totales por fila + deudas =====
        for ($d=1; $d <= $this->daysInMonth; $d++) $this->totalsPerDay[$d] = 0;

        foreach ($this->rows as $vid => &$row) {
            // total mensual mostrado (suma de celdas de la tabla)
            $row['total'] = array_sum($row['days']);

            // condición
            $cond = strtoupper(trim($row['cond'] ?? ''));
            $isEx = str_starts_with($cond, 'EX');
            $isDt = ($cond === 'DT');   // puedes ajustar si hay variantes "DT " etc.
            $isGn = ($cond === 'GN');

            // días pagados (para "Dias Pag")
            $row['days_paid'] = isset($paidDaysByVehicle[$vid]) ? count($paidDaysByVehicle[$vid]) : 0;

            // Total Deuda: suma costos de días SIN pago (sin domingos). EX => 0.
            $debtDays   = 0;
            $debtAmount = 0.0;
            if (isset($costsByVehicle[$vid])) {
                foreach ($costsByVehicle[$vid] as $day => $amt) {
                    $isPaidDay = isset($paidDaysByVehicle[$vid][$day]); // hubo pago PAGO/RETRASO ese día
                    if (!$isPaidDay) {
                        $debtDays++;
                        $debtAmount += (float)$amt;
                    }
                }
            }
            if ($isEx) {
                $row['debt_days']   = 0;
                $row['debt_amount'] = 0.0;
            } else {
                $row['debt_days']   = $debtDays;
                $row['debt_amount'] = round($debtAmount, 2);
            }

            // Suma de pagos del mes (PAGO/RETRASO), sin excluir domingos
            $paidSum = $paidSumByVehicle[$vid] ?? 0.0;

            // === Deuda REAL por condición ===

            $real = 0.0;
            if ($isEx) {
                Log::info("Deuda EX: $vid");
                $real = 0.0;
            } elseif ($isGn) {
                // GN: (Total pagos (tabla) + Total deuda) - sumaPagosSinDeuda
                Log::info("Deuda GN: $vid => " . $row['total'] . " + " . $row['debt_amount'] . " - " . $paidSum );
                $real = ($row['total'] + $row['debt_amount']) - $paidSum;
            } elseif ($isDt) {
                Log::info("Deuda DT: $vid => " . $row['total'] . " - " . $paidSum );
                // DT: Total pagos (tabla) - sumaPagosSinDeuda
                $real = $row['total'] - $paidSum;
            } else {
                // Otras condiciones (si las hubiera), mantenemos comportamiento previo: deuda - pagos.
                $real = $row['debt_amount'] - $paidSum;
                Log::info("Deuda OT: $vid => " . $row['debt_amount'] . " - " . $paidSum );
            }
            if ($real < 0) $real = 0.0;

            $row['real_debt_amount'] = round($real, 2);

            // acumulados
            $this->sumDaysPaid       += (int)$row['days_paid'];
            $this->sumDebtDays       += (int)$row['debt_days'];
            $this->sumDebtAmount     += (float)$row['debt_amount'];
            $this->sumRealDebtAmount += (float)$row['real_debt_amount'];

            // totales por día (importes)
            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $this->totalsPerDay[$d] += (float)$row['days'][$d];
            }
            $this->grandTotal += (float)$row['total'];
        }
        unset($row);
    }
}
