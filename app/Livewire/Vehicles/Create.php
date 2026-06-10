<?php

namespace App\Livewire\Vehicles;

use App\Models\Driver;
use App\Models\Headquarter;
use App\Models\Owner;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Services\CostPerPlateGenerator;
use App\Services\SupportRecordRelinker;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

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

    public $plate;

    public $sort_order;

    public $seats = 0;

    public $passengers = 0;

    public $new_images = [];

    public $image_files = [];

    public $listDrivers;

    public $listOwners;

    public $listHeadquarters;

    protected $rules = [
        'plate' => 'required|string|min:6|max:20|unique:vehicles,plate',
        'entry_date' => 'nullable|date',
        'termination_date' => 'nullable|date',
        'headquarter' => 'nullable|string|max:255',
        'class' => 'nullable|string|max:255',
        'brand' => 'nullable|string|max:255',
        'year' => 'nullable|integer',
        'model' => 'nullable|string|max:255',
        'bodywork' => 'nullable|string|max:255',
        'color' => 'nullable|string|max:255',
        'type' => 'nullable|string|max:255',
        'affiliated_company' => 'nullable|string|max:255',
        'condition' => 'required|string|min:1|max:255',
        'owner_id' => 'nullable|exists:owners,id',
        'driver_id' => 'nullable|exists:drivers,id',
        'fuel' => 'nullable|string|max:255',
        'soat_date' => 'nullable|date',
        'technical_review' => 'nullable|date',
        'certificate_date' => 'nullable|date',
        'detail' => 'nullable|string',
        'new_images' => 'nullable|array|max:10',
        'new_images.*' => 'image|max:5120',
        'sort_order' => 'required|integer',
        'seats' => 'nullable|integer',
        'passengers' => 'nullable|integer',
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
                'sort_order' => $this->sort_order,
                'plate' => $this->plate,
                'headquarters' => $this->headquarter,
                'entry_date' => $this->entry_date,
                'termination_date' => $this->termination_date,
                'class' => $this->class,
                'brand' => $this->brand,
                'year' => $this->year,
                'model' => $this->model,
                'bodywork' => $this->bodywork,
                'color' => $this->color,
                'type' => $this->type,
                'affiliated_company' => $this->affiliated_company,
                'condition' => $this->condition,
                'owner_id' => $this->owner_id,
                'driver_id' => $this->driver_id,
                'fuel' => $this->fuel,
                'soat_date' => $this->soat_date,
                'certificate_date' => $this->certificate_date,
                'technical_review' => $this->technical_review,
                'detail' => $this->detail,
                'seats' => $this->seats,
                'passengers' => $this->passengers,
            ];

            $relinker = app(SupportRecordRelinker::class);
            $costGenerator = app(CostPerPlateGenerator::class);
            $relinked = null;
            $costs = null;

            DB::transaction(function () use ($payload, $relinker, $costGenerator, &$relinked, &$costs) {
                $vehicle = Vehicle::create($payload);

                foreach ($this->image_files as $file) {
                    $path = $file->storePublicly('vehicles', 'public');
                    VehicleImage::create([
                        'vehicle_id' => $vehicle->id,
                        'image_path' => $path,
                    ]);
                }

                // Registros que entraron como apoyo antes de existir el vehículo
                $relinked = $relinker->relink($vehicle);

                // Costos del mes en curso (la generación mensual ya corrió sin él)
                $costs = $costGenerator->generateForVehicle($vehicle);
            });

            $message = 'Vehículo creado correctamente.';
            if ($relinked && ($summary = $relinker->summaryMessage($relinked))) {
                $message .= ' '.$summary;
            }
            if ($costs && ($costs['monthly'] > 0 || $costs['daily'] > 0)) {
                $message .= ' Se generaron sus costos por placa del mes actual.';
            }

            session()->flash('vehicle_success', $message);
            $this->redirectRoute('settings.vehicles.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('vehicle_error', 'Error al guardar: '.$e->getMessage());
            $this->redirectRoute('settings.vehicles.index');
        }
    }

    public function clean()
    {

        $this->reset(['plate', 'headquarter', 'entry_date', 'termination_date', 'class', 'brand', 'year', 'model', 'bodywork', 'color', 'type', 'affiliated_company', 'condition', 'owner_id', 'driver_id', 'fuel', 'soat_date', 'technical_review', 'certificate_date', 'detail', 'sort_order', 'seats', 'passengers', 'new_images', 'image_files']);

        $this->mount();
    }

    public function updatedNewImages(): void
    {
        foreach ($this->new_images as $file) {
            $this->image_files[] = $file;
        }
        $this->new_images = [];
    }

    public function removeNewImage(int $index): void
    {
        array_splice($this->image_files, $index, 1);
    }

    public function render()
    {
        return view('livewire.vehicles.create');
    }
}
