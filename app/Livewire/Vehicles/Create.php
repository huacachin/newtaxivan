<?php

namespace App\Livewire\Vehicles;

use App\Models\Driver;
use App\Models\Headquarter;
use App\Models\Owner;
use App\Models\Vehicle;
use Carbon\Carbon;
use Livewire\Component;

class Create extends Component
{
    public $headquarter, $entry_date, $termination_date;
    public $class = '', $brand = '', $year, $model, $bodywork = '';
    public $color, $type, $affiliated_company, $condition;
    public $owner_id, $driver_id, $fuel, $soat_date, $technical_review, $certificate_date;
    public $detail, $plate, $sort_order = 0, $seats = 0,$passengers = 0;

    public $listDrivers, $listOwners, $listHeadquarters;

    protected $rules = [
        "plate" => "required|string|max:20|unique:vehicles,plate",
        "entry_date" => "required|date",
        "termination_date" => "nullable|date",
        "headquarter" => "required|string|max:255",
        "class" => "required|string|max:255",
        "brand" => "required|string|max:255",
        "year" => "required|integer",
        "model" => "required|string|max:255",
        "bodywork" => "required|string|max:255",
        "color" => "required|string|max:255",
        "type"=>"required|string|max:255",
        "affiliated_company" => "required|string|max:255",
        "condition" => "required|string|max:255",
        "owner_id" => "required|exists:owners,id",
        "driver_id" => "required|exists:drivers,id",
        "fuel" => "required|string|max:255",
        "soat_date" => "nullable|date",
        "technical_review" => "nullable|date",
        "certificate_date" => "nullable|date",
        "detail" => "nullable|string",
        "sort_order" => "nullable|integer",
        "seats" => "nullable|integer",
        "passengers" => "nullable|integer"
    ];

    public function mount()
    {
        $today = Carbon::today()->toDateString();
        $this->entry_date = $today;
        $this->termination_date = $today;
        $this->certificate_date = $today;
        $this->soat_date = $today;
        $this->technical_review = $today;
        $this->listOwners = Owner::all();
        $this->listDrivers = Driver::all();
        $this->listHeadquarters = Headquarter::all();
    }

    public function save()
    {
        $this->validate();

        Vehicle::create([
            "sort_order" => $this->sort_order,
            "plate" => $this->plate,
            "headquarters" => $this->headquarter,
            "entry_date" => $this->entry_date,
            "termination_date" => $this->termination_date,
            "class" => $this->class,
            "brand" => $this->brand,
            "year" => $this->year,
            "model" => $this->model,
            "bodywork" => $this->bodywork,
            "color" => $this->color,
            "type" => $this->type,
            "affiliated_company" => $this->affiliated_company,
            "condition" => $this->condition,
            "owner_id" => $this->owner_id,
            "driver_id" => $this->driver_id,
            "fuel" => $this->fuel,
            "soat_date" => $this->soat_date,
            "certificate_date" => $this->certificate_date,
            "technical_review" => $this->technical_review,
            "detail" => $this->detail,
            "seats" => $this->seats,
            "passengers" => $this->passengers
        ]);

        $this->reset(['plate','headquarter','entry_date','termination_date','class','brand','year','model','bodywork','color','type','affiliated_company','condition','owner_id','driver_id','fuel','soat_date','technical_review','certificate_date','detail','sort_order','seats','passengers']);
        $this->dispatch('successAlert', ['message' => 'Vehículo creado correctamente.']);
    }

    public function render()
    {
        return view('livewire.vehicles.create');
    }
}
