<?php
// app/Livewire/Payments/Daily.php

namespace App\Livewire\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;

class Daily extends Component
{
    #[Url(except: null)] public ?int $year  = null;      // 1..N
    #[Url(except: null)] public ?int $month = null;      // 1..12
    #[Url(except: null)] public string $mode = 'Pago';   // 'Pago' | 'Caja'

    public int $daysInMonth = 30;

    // Datos por fila
    public array $rows = [];             // order, plate, cond, days[1..N], total, days_paid, debt_days, debt_amount, real_debt_amount
    // Totales
    public array $totalsPerDay = [];     // suma por día (S/)
    public float|int $grandTotal = 0;    // suma total del mes (S/)
    public int $sumDaysPaid = 0;         // footer: Total Pagos (conteo)
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
            ->where('status', 'active') // SOLO activos
            ->select($vehCols)
            ->orderBy($orderCol)
            ->get();

        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'order'     => (string)($v->sort_order ?? ''),
                'plate'     => (string)$v->plate,
                'cond'      => (string)($v->condition ?? ''),
                'days'      => array_fill(1, $this->daysInMonth, 0.0), // montos por día (S/)
                'total'     => 0.0,  // monto total mes
                'days_paid' => 0,    // días con pago (PAGO/RETRASO)
                'debt_days' => 0,    // días sin pago (según costos, sin domingos)
                'debt_amount' => 0.0,// S/ por días sin pago (TOTAL DEUDA - BRUTA)
                'real_debt_amount' => 0.0, // S/ Deuda Real (sólo GN)
            ];
        }

        $amountCol = $this->amountCol;

        // ===== Agregado principal para pintar montos por día =====
        if ($this->mode === 'Pago') {
            // PAGO/RETRASO por date_payment
            $dateCol = 'date_payment';
            $aggs = DB::table('payments as p')
                ->leftJoin('vehicles as v2', function ($join) {
                    $join->on('v2.plate', '=', 'p.legacy_plate')
                        ->where('v2.status', 'active');
                })
                ->selectRaw("
                    COALESCE(p.vehicle_id, v2.id) as vid,
                    DAY(p.`{$dateCol}`) as d,
                    SUM(p.`{$amountCol}`) as s
                ")
                ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
                ->whereNotNull("p.{$dateCol}")
                ->whereBetween("p.{$dateCol}", [$start->toDateString(), $end->toDateString()])
                ->groupBy('vid', 'd')
                ->get();
        } else {
            // CAJA: PAGO/RETRASO/DEUDA por date_register
            $dateCol = 'date_register';
            $aggs = DB::table('payments as p')
                ->leftJoin('vehicles as v2', function ($join) {
                    $join->on('v2.plate', '=', 'p.legacy_plate')
                        ->where('v2.status', 'active');
                })
                ->selectRaw("
                    COALESCE(p.vehicle_id, v2.id) as vid,
                    DAY(p.`{$dateCol}`) as d,
                    SUM(p.`{$amountCol}`) as s
                ")
                ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO','DEUDA'])
                ->whereNotNull("p.{$dateCol}")
                ->whereBetween("p.{$dateCol}", [$start->toDateString(), $end->toDateString()])
                ->groupBy('vid', 'd')
                ->get();
        }

        foreach ($aggs as $r) {
            $vid = (int) $r->vid;
            $day = (int) $r->d;
            $sum = (float) $r->s;
            if (!isset($this->rows[$vid])) continue;           // sólo activos
            if ($day < 1 || $day > $this->daysInMonth) continue;
            $this->rows[$vid]['days'][$day] = $sum;
        }

        // ===== DÍAS PAGADOS (EXCLUYENDO DOMINGOS) =====
        $paidDateCol = $this->mode === 'Pago' ? 'date_payment' : 'date_register';
        $paid = DB::table('payments as p')
            ->leftJoin('vehicles as v2', function ($join) {
                $join->on('v2.plate', '=', 'p.legacy_plate')
                    ->where('v2.status', 'active');
            })
            ->selectRaw("COALESCE(p.vehicle_id, v2.id) as vid, DAY(p.`{$paidDateCol}`) as d, COUNT(*) as c")
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO'])
            ->whereNotNull("p.{$paidDateCol}")
            ->whereBetween("p.{$paidDateCol}", [$start->toDateString(), $end->toDateString()])
            ->whereRaw("DAYOFWEEK(p.`{$paidDateCol}`) <> 1") // EXCLUIR DOMINGOS
            ->groupBy('vid', 'd')
            ->get();

        $paidDaysByVehicle = [];
        foreach ($paid as $p) {
            $vid = (int) $p->vid;
            $d   = (int) $p->d;
            if (!isset($this->rows[$vid])) continue;
            $paidDaysByVehicle[$vid][$d] = true;
        }

        // ===== COSTOS POR PLACA/DÍA (EXCLUYENDO DOMINGOS) =====
        $costsByVehicle = [];
        if (Schema::hasTable($this->costTable)) {
            $costs = DB::table($this->costTable)
                ->selectRaw("vehicle_id, DAY(`date`) as d, SUM(amount) as a")
                ->where('year', $this->year)
                ->where('month', $this->month)
                ->whereRaw("DAYOFWEEK(`date`) <> 1") // EXCLUIR DOMINGOS
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
            // total mensual (S/)
            $row['total'] = array_sum($row['days']);

            // días pagados (PAGO/RETRASO) sin domingos
            $row['days_paid'] = isset($paidDaysByVehicle[$vid]) ? count($paidDaysByVehicle[$vid]) : 0;

            // deuda bruta: días (en costos) que NO están pagados (sin domingos)
            $debtDays = 0;
            $debtAmount = 0.0;
            if (isset($costsByVehicle[$vid])) {
                foreach ($costsByVehicle[$vid] as $day => $amt) {
                    $isPaid = isset($paidDaysByVehicle[$vid][$day]);
                    if (!$isPaid) {
                        $debtDays++;
                        $debtAmount += (float)$amt;
                    }
                }
            }

            // Condición
            $cond = strtoupper(trim($row['cond'] ?? ''));
            $isEx = str_starts_with($cond, 'EX'); // EX, EX5, etc.
            $isDt = ($cond === 'DT');             // DT
            $isGn = str_starts_with($cond, 'GN'); // GN

            // ===== Total Deuda (NO TOCAR la lógica: EX no cuenta días ni monto)
            $row['debt_days']   = $isEx ? 0 : $debtDays;
            $row['debt_amount'] = $isEx ? 0.0 : round($debtAmount, 2);

            // ===== Deuda REAL: sólo GN
            $row['real_debt_amount'] = $isGn ? $row['debt_amount'] : 0.0;

            // Acumulados
            $this->sumDaysPaid       += (int)$row['days_paid'];
            $this->sumDebtDays       += (int)$row['debt_days'];        // Total Deuda días
            $this->sumDebtAmount     += (float)$row['debt_amount'];    // Total Deuda S/
            $this->sumRealDebtAmount += (float)$row['real_debt_amount']; // Deuda Real S/

            // totales por día (importes S/)
            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $this->totalsPerDay[$d] += (float)$row['days'][$d];
            }
            $this->grandTotal += (float)$row['total'];
        }
        unset($row);
    }
}
