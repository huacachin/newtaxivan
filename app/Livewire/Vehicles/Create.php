<?php

namespace App\Livewire\Vehicles;

use App\Models\Driver;
use App\Models\Headquarter;
use App\Models\Owner;
use App\Models\Vehicle;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public $headquarter, $entry_date, $termination_date;
    public $class = '', $brand = '', $year, $model, $bodywork = '';
    public $color, $type, $affiliated_company, $condition;
    public $owner_id, $driver_id, $fuel, $soat_date, $technical_review, $certificate_date;
    public $detail, $plate, $sort_order, $seats = 0,$passengers = 0;
    public $image_file;

    public $listDrivers, $listOwners, $listHeadquarters;

    protected $rules = [
        "plate" => "required|string|min:6|max:20|unique:vehicles,plate",
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
        "condition" => "required|string|min:1|max:255",
        "owner_id" => "nullable|exists:owners,id",
        "driver_id" => "nullable|exists:drivers,id",
        "fuel" => "nullable|string|max:255",
        "soat_date" => "nullable|date",
        "technical_review" => "nullable|date",
        "certificate_date" => "nullable|date",
        "detail" => "nullable|string",
        "image_file" => "nullable|image|max:5120",
        "sort_order" => "required|integer",
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
        $this->termination_date = null;
        $this->certificate_date = $today;
        $this->soat_date = $today;
        $this->technical_review = $today;
        $this->listOwners = Owner::all();
        $this->listDrivers = Driver::all();
        $this->listHeadquarters = Headquarter::all();
    }

    public function save()
    {
        try {
            if ($this->termination_date === '0000-00-00' || $this->termination_date === '') {
                $this->termination_date = null;
            }

            $this->validate();

            $payload = [
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
                "passengers" => $this->passengers,
            ];

            if ($this->image_file) {
                $payload['image_path'] = $this->image_file->storePublicly('vehicles', 'public');
            }

            Vehicle::create($payload);

            session()->flash('vehicle_success', 'Vehículo creado correctamente.');
            $this->redirectRoute('settings.vehicles.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('vehicle_error', 'Error al guardar: ' . $e->getMessage());
            $this->redirectRoute('settings.vehicles.index');
        }
    }

    public function clean(){

        $this->reset(['plate','headquarter','entry_date','termination_date','class','brand','year','model','bodywork','color','type','affiliated_company','condition','owner_id','driver_id','fuel','soat_date','technical_review','certificate_date','detail','sort_order','seats','passengers']);

        $this->mount();
    }

    public function removeNewImage(): void
    {
        $this->image_file = null;
    }

    public function render()
    {
        return view('livewire.vehicles.create');
    }
}
