<?php

namespace App\Livewire\Vehicles;

use App\Models\DrawBlock;
use App\Models\Driver;
use App\Models\Headquarter;
use App\Models\Owner;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Services\CostPerPlateGenerator;
use App\Services\DrawService;
use App\Services\SupportRecordRelinker;
use Carbon\Carbon;
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

    public bool $not_working = false;

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

    public array $existing_images = [];

    public array $deleted_image_ids = [];

    public $listDrivers;

    public $listOwners;

    public $listHeadquarters;

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
        $this->not_working = (bool) $this->vehicle->not_working_since;
        $this->fuel = $this->vehicle->fuel;
        $this->soat_date = optional($this->vehicle->soat_date)->format('Y-m-d');
        $this->certificate_date = optional($this->vehicle->certificate_date)->format('Y-m-d');
        $this->technical_review = optional($this->vehicle->technical_review)->format('Y-m-d');
        $this->detail = $this->vehicle->detail;
        $this->seats = $this->vehicle->seats;
        $this->passengers = $this->vehicle->passengers;
        $this->existing_images = $this->vehicle->images->map(fn ($img) => [
            'id' => (int) $img->id,
            'url' => asset('storage/'.$img->image_path),
        ])->all();
    }

    public function rules()
    {
        return [
            'sort_order' => 'required|integer',
            'plate' => 'required|string|min:6|max:20|unique:vehicles,plate,'.$this->vehicle->id,
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
            'not_working' => 'boolean',
            'driver_id' => [
                'nullable',
                'exists:drivers,id',
                function ($attribute, $value, $fail) {
                    if ($conflict = $this->driverConflict()) {
                        $fail("Este conductor ya está asignado al vehículo {$conflict->plate}.");
                    }
                },
            ],
            'fuel' => 'nullable|string|max:255',
            'soat_date' => 'nullable|date',
            'technical_review' => 'nullable|date',
            'certificate_date' => 'nullable|date',
            'detail' => 'nullable|string',
            'new_images' => 'nullable|array|max:10',
            'new_images.*' => 'image|max:5120',
            'seats' => 'nullable|integer',
            'passengers' => 'nullable|integer',
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

    private function driverConflict(): ?Vehicle
    {
        if (! $this->driver_id) {
            return null;
        }

        return Vehicle::where('driver_id', $this->driver_id)
            ->where('id', '!=', $this->vehicle->id)
            ->whereRaw("LOWER(TRIM(status)) = 'active'")
            ->orderBy('sort_order')
            ->first();
    }

    public function updatedNotWorking()
    {
        if ($this->not_working) {
            $this->driver_id = null;
            $this->resetErrorBag('driver_id');
        }
    }

    public function updatedDriverId()
    {
        if ($conflict = $this->driverConflict()) {
            $driver = $this->listDrivers->firstWhere('id', (int) $this->driver_id);
            $name = $driver->name ?? 'El conductor seleccionado';
            $this->addError('driver_id', "Este conductor ya está asignado al vehículo {$conflict->plate}.");
            $this->dispatch('errorAlert', ['message' => "{$name} ya está asignado al vehículo {$conflict->plate} (cod {$conflict->sort_order}). Cambia el conductor para poder guardar."]);
        } else {
            $this->resetErrorBag('driver_id');
        }
    }

    /** Eliminar, dar de baja y reactivar: los mismos roles que podían eliminar. */
    private function authorizeStatusChange(): void
    {
        if (! auth()->user()?->hasAnyRole('director', 'gerente', 'administrador')) {
            abort(403);
        }
    }

    /**
     * Historial que impide borrar la placa ("12 salida(s)", "3 sorteo(s)"…).
     * cost_per_plates y los bloqueos de Sorteos se borran en cascada.
     *
     * @return array<int, string>
     */
    private function historyRelations(Vehicle $vehicle): array
    {
        $relations = [];
        $departures = DB::table('departures')->where('vehicle_id', $vehicle->id)->count();
        $payments = DB::table('payments')->where('vehicle_id', $vehicle->id)->count();
        $debtDays = DB::table('debt_days')->where('vehicle_id', $vehicle->id)->count();
        $draws = DB::table('draw_entries')->where('vehicle_id', $vehicle->id)->count();

        if ($departures > 0) {
            $relations[] = "{$departures} salida(s)";
        }
        if ($payments > 0) {
            $relations[] = "{$payments} pago(s)";
        }

        $cond = strtoupper(trim($vehicle->condition ?? ''));
        if ($debtDays > 0 && $cond !== 'EX') {
            $relations[] = "{$debtDays} deuda(s)";
        }
        if ($draws > 0) {
            $relations[] = "{$draws} sorteo(s)";
        }

        return $relations;
    }

    /** Sin historial se puede borrar; con historial se ofrece darla de baja. */
    public function questionDelete($id): void
    {
        $vehicle = Vehicle::findOrFail($id);
        if ($this->historyRelations($vehicle) !== []) {
            $this->questionRetire($vehicle->id);

            return;
        }

        $this->dispatch('questionDelete', ['id' => $vehicle->id, 'role' => 'vehículo', 'name' => $vehicle->plate]);
    }

    #[On('register_destroy')]
    public function delete(int $id): void
    {
        $this->authorizeStatusChange();

        $vehicle = Vehicle::findOrFail($id);
        $relations = $this->historyRelations($vehicle);

        if (! empty($relations)) {
            session()->flash('vehicle_error', "No se puede eliminar {$vehicle->plate} porque tiene: ".implode(', ', $relations).'. Usa "Dar de baja".');
            $this->redirectRoute('settings.vehicles.index');

            return;
        }

        $vehicle->delete();
        session()->flash('vehicle_success', "Vehículo {$vehicle->plate} eliminado correctamente.");
        $this->redirectRoute('settings.vehicles.index');
    }

    /**
     * Confirmación de "Dar de baja": la fecha de baja pasa a hoy, su último día
     * de trabajo. Avisa si ya está en sorteos de después.
     */
    public function questionRetire(int $id): void
    {
        $this->authorizeStatusChange();

        $vehicle = Vehicle::findOrFail($id);
        $today = Carbon::today();

        if ($vehicle->isRetired()) {
            $this->dispatch('errorAlert', ['message' => "{$vehicle->plate} ya está de baja."]);

            return;
        }
        if ($vehicle->termination_date?->isSameDay($today)) {
            $this->dispatch('errorAlert', ['message' => "{$vehicle->plate} ya tiene fecha de baja hoy: hoy es su último día."]);

            return;
        }

        $html = 'Su fecha de baja será <b>hoy '.$today->format('d/m/Y').'</b>: es su último día de trabajo. '
            .'Desde mañana ya no entra a los sorteos y a medianoche pasa a inactiva.';
        if ($vehicle->termination_date) {
            $html .= '<br><small>Tenía la baja para el '.$vehicle->termination_date->format('d/m/Y').'; se adelanta a hoy.</small>';
        }
        $relations = $this->historyRelations($vehicle);
        if ($relations) {
            $html .= '<br><small class="text-muted">No se puede eliminar porque tiene historial: '.e(implode(', ', $relations)).'.</small>';
        }
        if ($warning = DrawService::retiredInDrawsWarning(DrawService::drawsAfter($vehicle->id, $today->toDateString()))) {
            $html .= '<div class="text-danger mt-2">'.e($warning).'</div>';
        }

        $this->dispatch('questionRetire', ['id' => $vehicle->id, 'plate' => $vehicle->plate, 'html' => $html]);
    }

    #[On('register_retire')]
    public function retire(int $id): void
    {
        $this->authorizeStatusChange();

        $vehicle = Vehicle::findOrFail($id);
        $today = Carbon::today()->toDateString();

        if ($vehicle->isRetired()) {
            session()->flash('vehicle_error', "{$vehicle->plate} ya estaba de baja.");
            $this->redirectRoute('settings.vehicles.index');

            return;
        }

        // Hoy todavía trabaja (salidas y pagos de hoy); vehicles:deactivate-ceased la pasa a inactiva a medianoche
        $vehicle->update(['termination_date' => $today]);

        session()->flash('vehicle_success', "Vehículo {$vehicle->plate} dado de baja: hoy es su último día y desde mañana ya no entra a los sorteos.");
        if ($warning = DrawService::retiredInDrawsWarning(DrawService::drawsAfter($vehicle->id, $today))) {
            session()->flash('vehicle_warning', "{$vehicle->plate}: {$warning}");
        }
        $this->redirectRoute('settings.vehicles.index');
    }

    public function questionReactivate(int $id): void
    {
        $this->authorizeStatusChange();

        $vehicle = Vehicle::findOrFail($id);
        $this->dispatch('questionActivate', ['id' => $vehicle->id, 'role' => 'vehículo', 'name' => $vehicle->plate]);
    }

    /**
     * Reactivar: quita la fecha de baja y la vuelve a activar; recupera los
     * registros que se fueron a apoyo mientras estuvo de baja y genera sus
     * costos del mes (como al guardar el formulario).
     */
    #[On('register_activate')]
    public function reactivate(int $id): void
    {
        $this->authorizeStatusChange();

        $vehicle = Vehicle::findOrFail($id);
        if ($vehicle->id === $this->vehicle->id && ($conflict = $this->driverConflict())) {
            $this->dispatch('errorAlert', ['message' => "Su conductor ahora está en el vehículo {$conflict->plate}. Cambia el conductor, borra la fecha de baja y guarda."]);

            return;
        }

        $relinker = app(SupportRecordRelinker::class);
        $costGenerator = app(CostPerPlateGenerator::class);
        $relinked = null;
        $costs = null;

        DB::transaction(function () use ($vehicle, $relinker, $costGenerator, &$relinked, &$costs) {
            $vehicle->update(['termination_date' => null, 'status' => 'active']);
            $vehicle->refresh();
            $relinked = $relinker->relink($vehicle);
            $costs = $costGenerator->generateForVehicle($vehicle);
        });

        session()->flash('vehicle_success', $this->withRelinkSummary("Vehículo {$vehicle->plate} reactivado.", $relinker, $relinked, $costs));
        $this->redirectRoute('settings.vehicles.index');
    }

    /**
     * @param  array{departures:int,payments:int,debt_days:int,debt_days_skipped:int}|null  $relinked
     * @param  array{monthly:int,daily:int}|null  $costs
     */
    private function withRelinkSummary(string $message, SupportRecordRelinker $relinker, ?array $relinked, ?array $costs): string
    {
        if ($relinked && ($summary = $relinker->summaryMessage($relinked))) {
            $message .= ' '.$summary;
        }
        if ($costs && ($costs['monthly'] > 0 || $costs['daily'] > 0)) {
            $message .= ' Se generaron sus costos por placa del mes actual.';
        }

        return $message;
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
                'sort_order' => $this->sort_order,
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
                // "No trabaja" libera al conductor; si ya estaba marcado se
                // conserva la fecha original para no reiniciar el conteo de dias
                'not_working_since' => $this->not_working
                    ? ($this->vehicle->not_working_since?->toDateString() ?? Carbon::today()->toDateString())
                    : null,
                'driver_id' => $this->not_working ? null : $this->driver_id,
                'fuel' => $this->fuel,
                'soat_date' => $this->soat_date,
                'certificate_date' => $this->certificate_date,
                'technical_review' => $this->technical_review,
                'detail' => $this->detail,
                'seats' => $this->seats,
                'passengers' => $this->passengers,
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

            // Si cambió la fecha de baja, el estado la sigue: activo hasta su último
            // día (borrarla o moverla a hoy o después la reactiva), inactivo si ya pasó
            $today = Carbon::today()->toDateString();
            $newTermination = array_key_exists('termination_date', $payload) && $payload['termination_date'] ? Carbon::parse($payload['termination_date'])->toDateString() : null;
            $terminationChanged = array_key_exists('termination_date', $payload) && $newTermination !== $this->vehicle->termination_date?->toDateString();
            if ($terminationChanged) {
                $payload['status'] = Vehicle::statusForTermination($newTermination, $today);
            }

            $relinker = app(SupportRecordRelinker::class);
            $costGenerator = app(CostPerPlateGenerator::class);
            $relinked = null;
            $costs = null;

            DB::transaction(function () use ($payload, $relinker, $costGenerator, &$relinked, &$costs) {
                $this->vehicle->update($payload);

                // Si el vehículo quedó activo y sin cese vigente (p. ej. reactivación),
                // recupera registros que se fueron a apoyo mientras estuvo cesado
                // y completa sus costos por placa del mes en curso.
                $vehicle = $this->vehicle->refresh();
                $relinked = $relinker->relink($vehicle);
                $costs = $costGenerator->generateForVehicle($vehicle);

                if (! empty($this->deleted_image_ids)) {
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

            session()->flash('vehicle_success', $this->withRelinkSummary('Vehículo actualizado correctamente.', $relinker, $relinked, $costs));
            if ($terminationChanged && $newTermination !== null
                && ($warning = DrawService::retiredInDrawsWarning(DrawService::drawsAfter($this->vehicle->id, $newTermination)))) {
                session()->flash('vehicle_warning', "{$this->vehicle->plate}: {$warning}");
            }
            $this->redirectRoute('settings.vehicles.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('vehicle_error', 'Error al actualizar: '.$e->getMessage());
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
        if (! in_array($imageId, $this->deleted_image_ids, true)) {
            $this->deleted_image_ids[] = $imageId;
        }
    }

    public function restoreExistingImage(int $imageId): void
    {
        $this->deleted_image_ids = array_values(array_filter(
            $this->deleted_image_ids,
            fn ($id) => $id !== $imageId
        ));
    }

    public function render()
    {
        return view('livewire.vehicles.edit', [
            // Historial de sanciones en Sorteos, para quien tiene acceso a Sorteos
            'drawHistory' => auth()->user()?->can('draws') ? DrawBlock::historyFor($this->vehicle->id) : null,
            // Botones: Reactivar si ya está de baja; Dar de baja si tiene historial; si no, Eliminar
            'retired' => $this->vehicle->isRetired(),
            'hasHistory' => $this->historyRelations($this->vehicle) !== [],
        ]);
    }
}
