<?php
namespace App\Livewire\Vehicles;

use App\Models\Driver;
use App\Models\Headquarter;
use App\Models\Owner;
use App\Models\Vehicle;
use Livewire\Component;

class Edit extends Component
{

    public Vehicle $vehicle;
    public $id;

    public $headquarter, $entry_date, $termination_date;
    public $class = '', $brand = '', $year, $model, $bodywork = '';
    public $color, $type, $affiliated_company, $condition;
    public $owner_id, $driver_id, $fuel, $soat_date, $technical_review, $certificate_date;
    public $detail, $plate,$sort_order, $seats = 0,$passengers = 0;

    public $listDrivers, $listOwners, $listHeadquarters;

    public function mount(int $id)
    {
        $this->vehicle = Vehicle::find($id);

        $this->listOwners = Owner::all();
        $this->listDrivers = Driver::all();
        $this->listHeadquarters = Headquarter::all();

        $this->sort_order = $this->vehicle->sort_order;
        $this->plate = $this->vehicle->plate;
        $this->headquarter = $this->vehicle->headquarters;
        $this->entry_date = optional($this->vehicle->entry_date)->format('Y-m-d');
        $this->termination_date = optional($this->vehicle->termination_date)->format('Y-m-d');
        $this->class = $this->vehicle->class;
        $this->brand = $this->vehicle->brand;
        $this->year = $this->vehicle->year;
        $this->model = $this->vehicle->model;
        $this->bodywork = $this->vehicle->bodywork;
        $this->color = $this->vehicle->color;
        $this->type = $this->vehicle->type;
        $this->affiliated_company = $this->vehicle->affiliated_company;
        $this->condition = $this->vehicle->condition;
        $this->owner_id = $this->vehicle->owner_id;
        $this->driver_id = $this->vehicle->driver_id;
        $this->fuel = $this->vehicle->fuel;
        $this->soat_date = optional($this->vehicle->soat_date)->format('Y-m-d');
        $this->certificate_date = optional($this->vehicle->certificate_date)->format('Y-m-d');
        $this->technical_review = optional($this->vehicle->technical_review)->format('Y-m-d');
        $this->detail = $this->vehicle->detail;
        $this->seats = $this->vehicle->seats;
        $this->passengers = $this->vehicle->passengers;
    }

    public function rules()
    {
        return [
            "sort_order" => "nullable|integer",
            "plate" => "required|string|max:20|unique:vehicles,plate," . $this->vehicle->id,
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
            "seats" => "nullable|integer",
            "passengers" => "nullable|integer"
        ];
    }

    public function update()
    {
        $this->validate();

        $this->vehicle->update([
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
            "driver_id"=>$this->driver_id,
            "fuel" => $this->fuel,
            "soat_date" => $this->soat_date,
            "certificate_date" => $this->certificate_date,
            "technical_review" => $this->technical_review,
            "detail" => $this->detail,
            "seats" => $this->seats,
            "passengers" => $this->passengers
        ]);

        $this->dispatch('successAlert', ['message' => 'Vehículo actualizado correctamente.']);
    }

    public function render()
    {
        return view('livewire.vehicles.edit');
    }
}
