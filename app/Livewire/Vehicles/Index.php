<?php

namespace App\Livewire\Vehicles;

use App\Models\Driver;
use App\Models\Headquarter;
use App\Models\Owner;
use App\Models\Vehicle;
use Livewire\Component;

class Index extends Component
{
    public $status = "active";
    public $search;
    public $filter = "plate";
    public $vehicles;
    public $owners;
    public $drivers;
    public $listDrivers;
    public $listOwners;
    public $listHeadquarters;

    public $vehicleId;
    public $plate;
    public $headquarter;
    public $entry_date;
    public $termination_date;
    public $class = '';
    public $brand = '';
    public $year;
    public $model;
    public $bodywork = '';
    public $color;
    public $type;
    public $affiliated_company;
    public $condition;
    public $owner_id;
    public $driver_id;
    public $fuel;
    public $soat_date;
    public $technical_review;
    public $certificate_date;
    public $detail;


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
        "detail" => "nullable|string"
    ];

    public function export(){
        $route = route('exports.vehicles',["search" => $this->search, "filter" => $this->filter, "status" => $this->status]);
        $this->dispatch('url-open',["url" => $route]);
    }

    public function mount()
    {

        $search = trim($this->search);
        $filter = $this->filter;
        $status = strtolower(trim($this->status));

        $this->listOwners = Owner::all();
        $this->listDrivers = Driver::all();
        $this->listHeadquarters = Headquarter::all();

        $query = Vehicle::query()
            // status
            ->when(in_array($status, ['active', 'inactive'], true),
                fn($q) => $q->whereRaw('LOWER(TRIM(status)) = ?', [$status])
            )
            // filtro + search
            ->when($search !== '' && $filter !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->where('plate', 'like', "%{$search}%"),
                    'brand' => $q->where('brand', 'like', "%{$search}%"),
                    'category' => $q->where('class', 'like', "%{$search}%"),
                    'year' => ctype_digit($search)
                        ? $q->where('year', (int)$search)
                        : $q->where('year', 'like', "%{$search}%"),
                    'owner' => $q->whereHas('owner', fn($r) => $r->where('name', 'like', "%{$search}%")
                    ),
                    'driver' => $q->whereHas('driver', fn($r) => $r->where('name', 'like', "%{$search}%")
                    ),
                    'condition' => $q->where('condition', 'like', "%{$search}%"),
                    'company' => $q->where('affiliated_company', 'like', "%{$search}%"),
                    'code' => $q->where('id', $search),
                    default => $q,
                };
            })
            ->with([
                'owner:id,name',
                'driver:id,name',
            ]);

        $this->vehicles = $query->get([
            'id', 'owner_id', 'driver_id', 'plate', 'status', 'year', 'condition', 'affiliated_company','termination_date','year','brand','class','type','fuel'
        ]);

        $this->totals();
    }

    public function updatedSearch() { $this->mount(); }
    public function updatedFilter() { $this->mount(); }
    public function updatedStatus() { $this->mount(); }

    public function openAddModal(){
        $this->reset(['plate','headquarter','entry_date','termination_date','class','brand','year','model','bodywork','color','type','affiliated_company','condition','owner_id','driver_id','fuel','soat_date','certificate_date','technical_review','detail']);
        $this->dispatch('open-modal', ['name' => 'modalAddVehicle', 'focus' => 'plate']);
    }



    public function save(){

        $this->validate();

        $vehicle = Vehicle::create([
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
            "detail" => $this->detail
        ]);

        $this->reset(['plate','headquarter','entry_date','termination_date','class','brand','year','model','bodywork','color','type','affiliated_company','condition','owner_id','driver_id','fuel','soat_date','certificate_date','technical_review','detail']);
        $this->mount();
        $this->dispatch('modal-close',["name" => "modalAddVehicle"]);
        $this->dispatch('successAlert',["message" => "Vehiculo creado correctamente"]);

    }

    public function openEditModal($id){
        $this->vehicleId = $id;
        $vehicle = Vehicle::find($this->vehicleId);
        $this->plate = $vehicle->plate;
        $this->headquarter = $vehicle->headquarters;
        $this->entry_date = optional($vehicle->entry_date)->format('Y-m-d');
        $this->termination_date = optional($vehicle->termination_date)->format('Y-m-d');
        $this->class = $vehicle->class;
        $this->brand = $vehicle->brand;
        $this->year = $vehicle->year;
        $this->model = $vehicle->model;
        $this->bodywork = $vehicle->bodywork;
        $this->color = $vehicle->color;
        $this->type = $vehicle->type;
        $this->affiliated_company = $vehicle->affiliated_company;
        $this->condition = $vehicle->condition;
        $this->owner_id = $vehicle->owner_id;
        $this->driver_id = $vehicle->driver_id;
        $this->fuel = $vehicle->fuel;
        $this->soat_date = optional($vehicle->soat_date)->format('Y-m-d');
        $this->certificate_date = optional($vehicle->certificate_date)->format('Y-m-d');
        $this->technical_review = optional($vehicle->technical_review)->format('Y-m-d');
        $this->detail = $vehicle->detail;
        $this->dispatch('open-modal', ['name' => 'modalEditVehicle', 'focus' => 'plate']);
    }

    public function update(){
        $this->validate([
            "plate" => "required|string|max:20|unique:vehicles,plate," . $this->vehicleId,
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
            "detail" => "nullable|string"
        ]);

        $vehicle = Vehicle::find($this->vehicleId);
        $vehicle->update([
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
            "detail" => $this->detail
        ]);

        $this->reset(['plate','headquarter','entry_date','termination_date','class','brand','year','model','bodywork','color','type','affiliated_company','condition','owner_id','driver_id','fuel','soat_date','certificate_date','technical_review','detail']);
        $this->mount();
        $this->dispatch('modal-close',["name" => "modalEditVehicle"]);
        $this->dispatch('successAlert',["message" => "Vehiculo actualizado correctamente"]);



    }

    public function totals(): void
    {

        $this->owners = Vehicle::query()
            ->whereIn('fuel', ['GAS', 'D2'])
            ->whereColumn('owner_id', 'driver_id')
            ->count();

        $this->drivers = Vehicle::query()
            ->whereColumn('owner_id', '!=', 'driver_id')
            ->count();

    }

    public function render()
    {
        return view('livewire.vehicles.index');
    }
}

