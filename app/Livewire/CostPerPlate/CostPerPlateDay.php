<?php

namespace App\Livewire\CostPerPlate;

use App\Models\CostPerPlateDay as CostPerPlateDayModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CostPerPlateDay extends Component
{

    public $year;
    public $month;
    public $plate;
    public $now;

    public function mount(){
        $this->now = Carbon::now('America/Lima');
    }

    public function openCalendar($plate,$year,$month){
        $route = route('settings.cost-per-plate.calendar',["plate"=>$plate,"year" => $year, "month" => $month]);

        $this->dispatch('url-open',["url" => $route]);;
    }

    public function goBack()
    {
        $this->dispatch('go-back', ["fallback" => route('settings.cost-per-plate.index')]);
    }

    public function render()
    {
        // Día actual (clamp al mes pedido)
        $now = $this->now;
        $plate = $this->plate;
        $daysInMonth = Carbon::create($this->year, $this->month, 1)->daysInMonth;
        $dom = min($now->day, $daysInMonth);
        $targetDate = Carbon::create($this->year, $this->month, $dom)->toDateString();

        $table = (new CostPerPlateDayModel)->getTable();

        $result = CostPerPlateDayModel::query()
            ->from("$table as d")
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->when($plate, fn($q) => $q->where('v.plate','like', '%'.$plate.'%'))
            ->where('d.year',  $this->year)
            ->where('d.month', $this->month)
            ->whereDate('d.date', $targetDate)
            ->groupBy('v.plate','d.year','d.month')
            ->orderBy('v.plate')
            ->get([
                'v.plate',
                'd.year',
                'd.month',
                DB::raw('SUM(d.amount) as amount'),
            ]);


        return view('livewire.cost-per-plate.cost-per-plate-day', compact('result'));
    }
}
