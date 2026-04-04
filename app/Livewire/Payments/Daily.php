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
    // order, plate, cond,
    // days[1..N], total, days_paid,
    // debt_days, debt_amount,
    // real_debt_days, real_debt_amount
    public array $rows = [];

    // Totales
    public array $totalsPerDay = [];     // suma por día (S/)
    public float|int $grandTotal = 0;    // suma total del mes (S/)
    public int $sumDaysPaid = 0;         // Total Pagos (días)
    public int $sumDebtDays = 0;         // Total Deuda (días)
    public float $sumDebtAmount = 0.0;   // Total Deuda (S/)
    public int $sumRealDebtDays = 0;     // Total D. Real (días)
    public float $sumRealDebtAmount = 0.0; // Total D. Real (S/)

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
        $route = route('exports.payments-daily', [
            "year"  => $this->year,
            "month" => $this->month,
        ]);

        $this->dispatch('url-open',["url" => $route]);
    }

    protected function loadData(): void
    {
        $this->rows              = [];
        $this->totalsPerDay      = [];
        $this->grandTotal        = 0;
        $this->sumDaysPaid       = 0;
        $this->sumDebtDays       = 0;
        $this->sumDebtAmount     = 0.0;
        $this->sumRealDebtDays   = 0;
        $this->sumRealDebtAmount = 0.0;

        if (!Schema::hasTable('vehicles') || !Schema::hasTable('payments')) return;

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();
        $this->daysInMonth = (int) $start->daysInMonth;

        $startStr = $start->toDateString();
        $endStr   = $end->toDateString();

        // ===== Vehículos activos =====
        $vehicles = DB::table('vehicles')
            ->where('status', 'active')
            ->select(['id','plate','sort_order','condition'])
            ->orderBy('sort_order')
            ->get();

        $vehiclePlates = [];
        foreach ($vehicles as $v) {
            $vehiclePlates[(int)$v->id] = (string)$v->plate;
            $this->rows[(int)$v->id] = [
                'order'           => (string)($v->sort_order ?? ''),
                'plate'           => (string)$v->plate,
                'cond'            => (string)($v->condition ?? ''),
                'days'            => array_fill(1, $this->daysInMonth, 0.0),
                'total'           => 0.0,
                'days_paid'       => 0,
                'debt_days'       => 0,
                'debt_amount'     => 0.0,
                'real_debt_days'  => 0,
                'real_debt_amount'=> 0.0,
            ];
        }

        $dateCol = $this->mode === 'Pago' ? 'date_payment' : 'date_register';
        $typeFilter = $this->mode === 'Pago' ? ['PAGO','RETRASO'] : ['PAGO','RETRASO','DEUDA'];

        // ===== Importes por día (celdas de la tabla) =====
        $aggs = DB::table('payments')
            ->selectRaw("vehicle_id as vid, DAY($dateCol) as d, SUM(amount) as s")
            ->whereIn(DB::raw('UPPER(type)'), $typeFilter)
            ->whereNotNull($dateCol)
            ->whereBetween($dateCol, [$startStr, $endStr])
            ->groupBy('vid', 'd')
            ->get();

        foreach ($aggs as $r) {
            $vid = (int) $r->vid;
            $day = (int) $r->d;
            if (!isset($this->rows[$vid]) || $day < 1 || $day > $this->daysInMonth) continue;
            $this->rows[$vid]['days'][$day] = (float) $r->s;
        }

        // ===== Total Pagos: count registros + sum monto (como legacy count(placa)) =====
        $paidTotals = DB::table('payments')
            ->selectRaw("vehicle_id as vid, COUNT(*) as kt, SUM(amount) as montox")
            ->whereIn(DB::raw('UPPER(type)'), ['PAGO','RETRASO'])
            ->whereNotNull($dateCol)
            ->whereBetween($dateCol, [$startStr, $endStr])
            ->groupBy('vid')
            ->get();

        $paidCountByVehicle = [];
        $paidSumByVehicle = [];
        foreach ($paidTotals as $pt) {
            $vid = (int) $pt->vid;
            $paidCountByVehicle[$vid] = (int) $pt->kt;
            $paidSumByVehicle[$vid] = (float) $pt->montox;
        }

        // ===== Costos por vehículo (sin domingos) =====
        $costTotals = DB::table('cost_per_plate_days')
            ->selectRaw("vehicle_id, COUNT(*) as dias, SUM(amount) as total_costo")
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->whereRaw("DAYOFWEEK(`date`) <> 1")
            ->groupBy('vehicle_id')
            ->get();

        $costDaysByVehicle = [];
        $costSumByVehicle = [];
        foreach ($costTotals as $ct) {
            $vid = (int) $ct->vehicle_id;
            $costDaysByVehicle[$vid] = (int) $ct->dias;
            $costSumByVehicle[$vid] = (float) $ct->total_costo;
        }

        // ===== Deuda Real DT: días con salidas (excluyendo Huachipa/lima, sin domingos) =====
        $dtDepartures = DB::table('departures as d')
            ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->selectRaw("d.vehicle_id as vid, COUNT(DISTINCT DATE(d.date)) as dias_trab,
                         (SELECT SUM(cpd.amount) FROM cost_per_plate_days cpd
                          WHERE cpd.vehicle_id = d.vehicle_id
                          AND cpd.year = ? AND cpd.month = ?
                          AND DAYOFWEEK(cpd.date) <> 1
                          AND cpd.date IN (SELECT DISTINCT DATE(d2.date) FROM departures d2
                              LEFT JOIN headquarters h2 ON h2.id = d2.headquarter_id
                              WHERE d2.vehicle_id = d.vehicle_id
                              AND DATE(d2.date) BETWEEN ? AND ?
                              AND (h2.name IS NULL OR h2.name NOT IN ('Huachipa','lima'))
                              AND DAYOFWEEK(d2.date) <> 1)
                         ) as monto_pen", [$this->year, $this->month, $startStr, $endStr])
            ->whereBetween(DB::raw('DATE(d.date)'), [$startStr, $endStr])
            ->where(function($q) {
                $q->whereNull('h.name')->orWhereNotIn('h.name', ['Huachipa','lima']);
            })
            ->whereRaw('DAYOFWEEK(d.date) <> 1')
            ->groupBy('d.vehicle_id')
            ->get();

        $dtDataByVehicle = [];
        foreach ($dtDepartures as $dt) {
            $dtDataByVehicle[(int)$dt->vid] = [
                'dias_trab' => (int) $dt->dias_trab,
                'monto_pen' => (float) ($dt->monto_pen ?? 0),
            ];
        }

        // ===== Totales por fila =====
        for ($d=1; $d <= $this->daysInMonth; $d++) {
            $this->totalsPerDay[$d] = 0;
        }

        foreach ($this->rows as $vid => &$row) {
            $row['total'] = array_sum($row['days']);

            $cond  = strtoupper(trim($row['cond'] ?? ''));
            $isEx  = str_starts_with($cond, 'EX');
            $isEx5 = ($cond === 'EX5');
            $isDt  = ($cond === 'DT');
            $isGn  = ($cond === 'GN');

            $kt     = $paidCountByVehicle[$vid] ?? 0;
            $montox = $paidSumByVehicle[$vid] ?? 0.0;

            // Total Pagos: días = count registros (como legacy)
            $row['days_paid'] = $kt;

            // Total Deuda
            $costDias = $costDaysByVehicle[$vid] ?? 0;
            $costSum  = $costSumByVehicle[$vid] ?? 0.0;
            $costUnit = $costDias > 0 ? round($costSum / $costDias, 2) : 10.0;

            if ($isEx && !$isEx5) {
                $row['debt_days']   = 0;
                $row['debt_amount'] = 0.0;
            } else {
                $debtDays = $costDias > 0 ? round(($costSum - $montox) / $costUnit, 0) : 0;
                $debtAmount = round($debtDays * $costUnit, 2);
                if ($debtDays < 0) { $debtDays = 0; $debtAmount = 0.0; }
                $row['debt_days']   = (int) $debtDays;
                $row['debt_amount'] = $debtAmount;
            }

            // Deuda Real
            $realDays = 0;
            $realAmount = 0.0;

            if ($isEx && !$isEx5) {
                $realDays = 0;
                $realAmount = 0.0;
            } elseif ($isEx5) {
                // EX5: si deuda > 5 días, descuenta 5 de gracia
                $debtRaw = $costDias > 0 ? ($costSum - $montox) / $costUnit : 0;
                if ($debtRaw > 5) {
                    $realDays = (int) round($debtRaw - 5, 0);
                    $realAmount = round($realDays * $costUnit, 2);
                }
            } elseif ($isDt) {
                // DT: días trabajados - pagos, monto trabajado - pagos
                $dtData = $dtDataByVehicle[$vid] ?? ['dias_trab' => 0, 'monto_pen' => 0];
                $realDays = max(0, $dtData['dias_trab'] - $kt);
                $realAmount = max(0, round($dtData['monto_pen'] - $montox, 2));
            } elseif ($isGn) {
                // GN: días costo - pagos, monto costo - pagos
                $realDays = max(0, $costDias - $kt);
                $realAmount = max(0, round($costSum - $montox, 2));
            } else {
                $realDays = (int) $row['debt_days'];
                $realAmount = $row['debt_amount'];
            }

            $row['real_debt_days']   = (int) $realDays;
            $row['real_debt_amount'] = round($realAmount, 2);

            // Acumulados
            $this->sumDaysPaid       += (int)$row['days_paid'];
            $this->sumDebtDays       += (int)$row['debt_days'];
            $this->sumDebtAmount     += (float)$row['debt_amount'];
            $this->sumRealDebtDays   += (int)$row['real_debt_days'];
            $this->sumRealDebtAmount += (float)$row['real_debt_amount'];

            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $this->totalsPerDay[$d] += (float)$row['days'][$d];
            }
            $this->grandTotal += (float)$row['total'];
        }
        unset($row);

        // ===== Pagos sin vehicle_id (legacy_plate) =====
        $legacyAggs = DB::table('payments')
            ->selectRaw("legacy_plate as plate, DAY($dateCol) as d, SUM(amount) as s")
            ->whereIn(DB::raw('UPPER(type)'), $typeFilter)
            ->whereNotNull($dateCol)
            ->whereBetween($dateCol, [$startStr, $endStr])
            ->whereNull('vehicle_id')
            ->whereNotNull('legacy_plate')
            ->where('legacy_plate', '!=', '')
            ->groupBy('plate', 'd')
            ->get();

        $legacyPlates = [];
        foreach ($legacyAggs as $r) {
            $plate = strtoupper(trim($r->plate));
            $day   = (int) $r->d;
            if (!isset($legacyPlates[$plate])) {
                $legacyPlates[$plate] = [
                    'order'           => '',
                    'plate'           => $plate,
                    'cond'            => '',
                    'days'            => array_fill(1, $this->daysInMonth, 0.0),
                    'total'           => 0.0,
                    'days_paid'       => 0,
                    'debt_days'       => 0,
                    'debt_amount'     => 0.0,
                    'real_debt_days'  => 0,
                    'real_debt_amount'=> 0.0,
                ];
            }
            if ($day >= 1 && $day <= $this->daysInMonth) {
                $legacyPlates[$plate]['days'][$day] = (float) $r->s;
            }
        }

        // Calcular totales y days_paid para legacy
        $legacyPaidCounts = DB::table('payments')
            ->selectRaw("legacy_plate as plate, COUNT(*) as kt")
            ->whereIn(DB::raw('UPPER(type)'), ['PAGO','RETRASO'])
            ->whereNotNull($dateCol)
            ->whereBetween($dateCol, [$startStr, $endStr])
            ->whereNull('vehicle_id')
            ->whereNotNull('legacy_plate')
            ->where('legacy_plate', '!=', '')
            ->groupBy('plate')
            ->pluck('kt', 'plate');

        foreach ($legacyPlates as $plate => &$row) {
            $row['total']     = array_sum($row['days']);
            $row['days_paid'] = (int) ($legacyPaidCounts[strtoupper(trim($plate))] ?? 0);

            $this->sumDaysPaid += (int)$row['days_paid'];

            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $this->totalsPerDay[$d] += (float)$row['days'][$d];
            }
            $this->grandTotal += (float)$row['total'];
        }
        unset($row);

        // Agregar al final de rows con keys negativos para no colisionar
        $negKey = -1;
        foreach ($legacyPlates as $plate => $row) {
            $this->rows[$negKey] = $row;
            $negKey--;
        }
    }
}
