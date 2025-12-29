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
        "entry_date" => "nullable|date",
        "termination_date" => "nullable|date",
        "headquarter" => "nullable|string|max:255",
        "class" => "nullable|string|max:255",
        "brand" => "nullable|string|max:255",
        "year" => "nullable|integer",
        "model" => "nullable|string|max:255",
        "bodywork" => "nullable|string|max:255",
        "color" => "nullable|string|max:255",
        "type"=>"nullable|string|max:255",
        "affiliated_company" => "nullable|string|max:255",
        "condition" => "required|string|max:255",
        "owner_id" => "nullable|exists:owners,id",
        "driver_id" => "nullable|exists:drivers,id",
        "fuel" => "nullable|string|max:255",
        "soat_date" => "nullable|date",
        "technical_review" => "nullable|date",
        "certificate_date" => "nullable|date",
        "detail" => "nullable|string",
        "sort_order" => "nullable|integer",
        "seats" => "nullable|integer",
        "passengers" => "nullable|integer"
    ];

    protected $validationAttributes = [
        'plate' => 'placa',
        'headquarter' => 'sede',
        'class' => 'categoria',
        'brand' => 'marca',
        'model' => 'modelo',
        'bodywork' => 'carroceria',
        'tipo' => 'modalidad',
        'condition' => 'condición',
        'affiliated_company' => 'empresa asociada',
        'owner_id' => 'propietario',
        'driver_id' => 'conductor',
        'fuel' => 'combustible',
        'detail' => 'detalles',
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
        // Mostrar alert Bootstrap en la vista (sin redirección)
        session()->flash('add_success', true);

        redirect()->route('settings.vehicles.index');
    }

    public function clean(){

        $this->reset(['plate','headquarter','entry_date','termination_date','class','brand','year','model','bodywork','color','type','affiliated_company','condition','owner_id','driver_id','fuel','soat_date','technical_review','certificate_date','detail','sort_order','seats','passengers']);

        $this->mount();
    }

    public function render()
    {
        return view('livewire.vehicles.create');
    }
}
