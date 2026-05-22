<?php
namespace App\Livewire\Vehicles;

use App\Models\Driver;
use App\Models\Headquarter;
use App\Models\Owner;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public Vehicle $vehicle;
    public $id;

    public $headquarter, $entry_date, $termination_date;
    public $class = '', $brand = '', $year, $model, $bodywork = '';
    public $color, $type, $affiliated_company, $condition;
    public $owner_id, $driver_id, $fuel, $soat_date, $technical_review, $certificate_date;
    public $detail, $plate,$sort_order, $seats = 0,$passengers = 0;
    public $new_images = [];
    public $image_files = [];
    public array $existing_images = [];
    public array $deleted_image_ids = [];

    public $listDrivers, $listOwners, $listHeadquarters;

    public function mount(int $id)
    {
        $this->vehicle = Vehicle::with('images')->find($id);

        $this->listOwners = Owner::all();
        $this->listDrivers = Driver::all();
        $this->listHeadquarters = Headquarter::all();

        $this->sort_order = $this->vehicle->sort_order;
        $this->plate = $this->vehicle->plate;
        $this->headquarter = $this->vehicle->headquarters;
        $this->entry_date = optional($this->vehicle->entry_date)->format('Y-m-d');
        $this->termination_date = $this->vehicle->termination_date ? $this->vehicle->termination_date->format('Y-m-d') : null;
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
        $this->existing_images = $this->vehicle->images->map(fn($img) => [
            'id'  => (int)$img->id,
            'url' => asset('storage/' . $img->image_path),
        ])->all();
    }

    public function rules()
    {
        return [
            "sort_order" => "required|integer",
            "plate" => "required|string|min:6|max:20|unique:vehicles,plate," . $this->vehicle->id,
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
            "new_images" => "nullable|array|max:10",
            "new_images.*" => "image|max:5120",
            "seats" => "nullable|integer",
            "passengers" => "nullable|integer"
        ];
    }

    protected $validationAttributes = [
        'plate' => 'placa',
        'headquarter' => 'sede',
        'class' => 'categoria',
        'brand' => 'marca',
        'model' => 'modelo',
        'bodywork' => 'carrocería',
        'tipo' => 'modalidad',
        'condition' => 'condición',
        'affiliated_company' => 'empresa asociada',
        'owner_id' => 'propietario',
        'driver_id' => 'conductor',
        'fuel' => 'combustible',
        'detail' => 'detalles',
    ];

    public function questionDelete($id): void
    {
        $this->dispatch('questionDelete',["id" => $id]);
    }

    #[On('register_destroy')]
    public function delete(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director','gerente','administrador')) {
            abort(403);
        }

        $vehicle = Vehicle::findOrFail($id);

        // Verificar relaciones antes de borrar (cost_per_plates se borra en cascada)
        $relations = [];
        $departures = \DB::table('departures')->where('vehicle_id', $id)->count();
        $payments   = \DB::table('payments')->where('vehicle_id', $id)->count();
        $debtDays   = \DB::table('debt_days')->where('vehicle_id', $id)->count();

        if ($departures > 0) $relations[] = "{$departures} salida(s)";
        if ($payments > 0)   $relations[] = "{$payments} pago(s)";

        $cond = strtoupper(trim($vehicle->condition ?? ''));
        if ($debtDays > 0 && $cond !== 'EX') $relations[] = "{$debtDays} deuda(s)";

        if (!empty($relations)) {
            session()->flash('vehicle_error', "No se puede eliminar {$vehicle->plate} porque tiene: " . implode(', ', $relations));
            $this->redirectRoute('settings.vehicles.index');
            return;
        }

        $vehicle->delete();
        session()->flash('vehicle_success', "Vehículo {$vehicle->plate} eliminado correctamente.");
        $this->redirectRoute('settings.vehicles.index');
    }

    public function update()
    {
        try {
            if ($this->termination_date === '0000-00-00' || $this->termination_date === '') {
                $this->termination_date = null;
            }

            $this->validate();

            // 'plate' se omite intencionalmente: campo bloqueado en edicion
            // para preservar referencias en otras tablas (legacy_plate, etc.).
            $payload = [
                "sort_order" => $this->sort_order,
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

            // Controlador no puede tocar F. Ingreso, Fecha cese, Orden ni
            // Condicion aunque el HTML sea bypasseado.
            if (auth()->user()?->hasRole('controlador')) {
                unset(
                    $payload['entry_date'],
                    $payload['termination_date'],
                    $payload['sort_order'],
                    $payload['condition']
                );
            }

            DB::transaction(function () use ($payload) {
                $this->vehicle->update($payload);

                if (!empty($this->deleted_image_ids)) {
                    $imagesToDelete = VehicleImage::where('vehicle_id', $this->vehicle->id)
                        ->whereIn('id', $this->deleted_image_ids)
                        ->get();
                    foreach ($imagesToDelete as $img) {
                        if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                            Storage::disk('public')->delete($img->image_path);
                        }
                        $img->delete();
                    }
                }

                foreach ($this->image_files as $file) {
                    $path = $file->storePublicly('vehicles', 'public');
                    VehicleImage::create([
                        'vehicle_id' => $this->vehicle->id,
                        'image_path' => $path,
                    ]);
                }
            });

            session()->flash('vehicle_success', 'Vehículo actualizado correctamente.');
            $this->redirectRoute('settings.vehicles.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('vehicle_error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirectRoute('settings.vehicles.index');
        }
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

    public function removeExistingImage(int $imageId): void
    {
        if (!in_array($imageId, $this->deleted_image_ids, true)) {
            $this->deleted_image_ids[] = $imageId;
        }
    }

    public function restoreExistingImage(int $imageId): void
    {
        $this->deleted_image_ids = array_values(array_filter(
            $this->deleted_image_ids,
            fn($id) => $id !== $imageId
        ));
    }

    public function render()
    {
        return view('livewire.vehicles.edit');
    }
}
