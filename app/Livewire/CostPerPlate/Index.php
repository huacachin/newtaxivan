<?php

namespace App\Livewire\CostPerPlate;

use App\Models\CostPerPlate;
use App\Models\CostPerPlateDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public $type = "cp";
    public $date;
    public $year;
    public $month;

    public function mount(){
        $this->date = Carbon::now()->format('Y-m-d');
    }

    #TODO: Unificar con App/Services/CostPerPlateGenerador
    #[On('generate_cost_per_plates')]
    public function generate()
    {

        $dest = Carbon::parse($this->date)->startOfMonth();
        $src  = $dest->copy()->subMonth();

        $destYear  = (int) $dest->year;
        $destMonth = (int) $dest->month;

        $srcYear   = (int) $src->year;
        $srcMonth  = (int) $src->month;

        // 1) Último día NO domingo por vehículo en el mes fuente (MySQL: DAYOFWEEK(domingo)=1)
        $lastDaily = DB::table('cost_per_plate_days as d')
            ->select('d.vehicle_id', 'd.amount')
            ->whereYear('d.date', $srcYear)
            ->whereMonth('d.date', $srcMonth)
            ->whereRaw(
                'd.date = (
                    SELECT MAX(dd.date)
                    FROM cost_per_plate_days dd
                    WHERE dd.vehicle_id = d.vehicle_id
                      AND YEAR(dd.date) = ?
                      AND MONTH(dd.date) = ?
                      AND DAYOFWEEK(dd.date) <> 1
                )',
                [$srcYear, $srcMonth]
            )
            ->get()
            ->keyBy('vehicle_id'); // vehicle_id => {vehicle_id, amount}

        // 2) Mensual del mes anterior (order y/o fallback amount)
        $srcMonthly = CostPerPlate::query()
            ->where('year', $srcYear)
            ->where('month', $srcMonth)
            ->get(['vehicle_id','amount','order'])
            ->keyBy('vehicle_id');

        if ($lastDaily->isEmpty() && $srcMonthly->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: "No hay base en {$srcMonth}/{$srcYear} para generar.");
            return;
        }

        $daysInDest = $dest->copy()->endOfMonth()->day;
        $now = now();

        $monthlyPayload = [];
        $dailyPayload   = [];

        // Vehículos con base disponible (diario o mensual)
        $vehicleIds = collect($lastDaily->keys())->merge($srcMonthly->keys())->unique()->values();

        foreach ($vehicleIds as $vid) {
            // monto base: último diario no-domingo > mensual > null
            $amount = null;
            if (isset($lastDaily[$vid])) {
                $amount = (float) $lastDaily[$vid]->amount;
            } elseif (isset($srcMonthly[$vid])) {
                $amount = (float) $srcMonthly[$vid]->amount;
            }
            if ($amount === null) {
                continue;
            }

            $order = isset($srcMonthly[$vid]) ? (int) $srcMonthly[$vid]->order : 0;

            // Mensual destino
            $monthlyPayload[] = [
                'vehicle_id' => $vid,
                'year'       => $destYear,
                'month'      => $destMonth,
                'amount'     => $amount,
                'order'      => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Diario destino (domingos => 0)
            for ($day = 1; $day <= $daysInDest; $day++) {
                $dateObj = Carbon::create($destYear, $destMonth, $day);
                $dailyPayload[] = [
                    'vehicle_id' => $vid,
                    'year'       => $destYear,
                    'month'      => $destMonth,
                    'date'       => $dateObj->toDateString(),
                    'amount'     => $dateObj->isSunday() ? 0.0 : $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (empty($monthlyPayload) && empty($dailyPayload)) {
            $this->dispatch('alertError');
            return;
        }

        DB::transaction(function () use ($monthlyPayload, $dailyPayload, $destYear, $destMonth) {

            // BORRAR lo existente del mes/año destino en ambas tablas
            CostPerPlateDay::where('year', $destYear)->where('month', $destMonth)->delete();
            CostPerPlate::where('year', $destYear)->where('month', $destMonth)->delete();

            // Insertar (podrías usar upsert también, pero tras borrar basta insert)
            if (!empty($monthlyPayload)) {
                // chunk por volumen grande
                foreach (array_chunk($monthlyPayload, 1000) as $chunk) {
                    CostPerPlate::insert($chunk);
                }
            }
            if (!empty($dailyPayload)) {
                foreach (array_chunk($dailyPayload, 1000) as $chunk) {
                    CostPerPlateDay::insert($chunk);
                }
            }
        });

        // refrescar listado
        $this->render();
    }

    public function questionGenerate(){
        $this->dispatch('questionGenerate');
    }

    public function openDetail($year, $month){

        $route = route('settings.cost-per-plate.cost-per-plate-day',["year" => $year, "month" => $month]);

        $this->dispatch('url-open',["url" => $route]);
    }

    public function render()
    {

        $result = CostPerPlate::from('cost_per_plates as c')
            ->join('vehicles as v', 'v.id', '=', 'c.vehicle_id')
            ->where('v.status', 'active')
            ->selectRaw('
            c.year,
            c.month,
            COUNT(DISTINCT c.vehicle_id) as plates,
            MIN(c.amount) as amount
        ')
            ->groupBy('c.year','c.month')
            ->orderByDesc('c.year')->orderByDesc('c.month')
            ->get();


        return view('livewire.cost-per-plate.index',compact('result'));
    }
}
