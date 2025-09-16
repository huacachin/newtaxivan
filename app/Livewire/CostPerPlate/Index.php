<?php

namespace App\Livewire\CostPerPlate;

use App\Models\CostPerPlate;
use Carbon\Carbon;
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

    public function generate(){
        dd($this->type, $this->date);
    }

    public function openDetail($year, $month){

        $route = route('settings.cost-per-plate.cost-per-plate-day',["year" => $year, "month" => $month]);

        $this->dispatch('url-open',["url" => $route]);
    }

    public function render()
    {

        $result = CostPerPlate::from('cost_per_plates as c')
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
