<?php

namespace App\Livewire\Departures;

use App\Models\Departure;
use App\Models\Headquarter;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class EditDeparture extends Component
{
    // ==============================
    // Identificador del registro a editar
    // ==============================
    public ?int $depId = null;

    // ==============================
    // Campos del formulario (mismos que el modal)
    // ==============================
    public ?string $plate = null;
    public bool $plateExists = true;
    public ?string $date = null;
    public ?int    $headquarter_id = null;
    public ?float  $price = 0;
    public ?int    $passenger = 0;
    public ?float  $passage = 0;

    public ?string $hour = null;
    public ?string $latitude = null;
    public ?string $longitude = null;

    /**
     * Catálogos para selects (id, name)
     * @var \Illuminate\Support\Collection|array
     */
    public $listHeadquarters = [];
    public $headquarters;

    // ====== Roles/Sedes
    private array $userHqIds = [];

    // ==============================
    // Validaciones (idénticas al modal)
    // ==============================
    protected function rules()
    {
        return [
            'plate' => ['required','string','min:6','max:20'],
            'date'           => ['required','date'],
            'headquarter_id' => ['required','integer','exists:headquarters,id'],
            'price'          => ['required','numeric'],
            'passenger'      => ['required','integer'],
            'passage'        => ['required','numeric'],
        ];
    }

    protected function messages(): array
    {
        return [
            'plate.required'            => 'La placa es obligatoria.',
            'date.required'             => 'La fecha es obligatoria.',
            'headquarter_id.required'   => 'La sucursal es obligatoria.',

            'price.required'            => 'El precio es obligatorio.',
            'price.numeric'             => 'El precio debe ser numérico.',

            'passenger.required'        => 'El número de pasajeros es obligatorio.',
            'passenger.integer'         => 'Los pasajeros deben ser un número entero.',

            'passage.required'          => 'El pasaje es obligatorio.',
            'passage.numeric'           => 'El pasaje debe ser numérico.',
        ];
    }

    // ==============================
    // Helpers de rol/sedes
    // ==============================
    private function userHasRole(string $needle): bool
    {
        $u = Auth::user();
        if (!$u) return false;

        $needle = mb_strtolower($needle);
        return $u->getRoleNames()
            ->map(fn ($r) => mb_strtolower($r))
            ->contains($needle);
    }

    private function isAdmin(): bool
    {
        return $this->userHasRole('director') || $this->userHasRole('gerente') || $this->userHasRole('administrador');
    }

    private function loadUserHeadquarters(): void
    {
        $u = Auth::user();
        if (!$u) {
            $this->userHqIds = [];
            return;
        }

        $ids = $u->headquarters()->pluck('headquarters.id')->map(fn($v)=>(int)$v)->all();
        if ($u->headquarter_id && !in_array((int)$u->headquarter_id, $ids, true)) {
            $ids[] = (int)$u->headquarter_id;
        }
        $this->userHqIds = $ids;
    }

    /** Sedes permitidas (pivot + primaria), ints y únicas */
    private function allowedHqIds(): array
    {
        $u = Auth::user();
        if (!$u) return [];

        $ids = $u->headquarters()->pluck('headquarters.id')->map(fn($v)=>(int)$v)->all();
        $primary = (int) ($u->headquarter_id ?? 0);
        if ($primary && !in_array($primary, $ids, true)) {
            $ids[] = $primary;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    // ==============================
    // Ciclo de vida
    // ==============================
    /**
     * Recibe {id} desde la ruta
     */
    public function mount(int $id): void
    {
        $this->depId = $id;

        $this->loadUserHeadquarters();

        // Cargar catálogo de sedes con primaria primero
        $primaryId = (int) (Auth::user()?->headquarter_id ?? 0);

        if ($this->isAdmin()) {
            $this->headquarters = Headquarter::where('status','active')
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$primaryId ?: 0])
                ->orderBy('name')
                ->get(['id','name']);
        } else {
            $ids = $this->allowedHqIds() ?: [-1];
            $this->headquarters = Headquarter::where('status','active')
                ->whereIn('id', $ids)
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$primaryId ?: 0])
                ->orderBy('name')
                ->get(['id','name']);
        }
        $this->listHeadquarters = $this->headquarters;

        // Cargar los datos del registro a editar
        $this->loadRowOrAbort();
    }

    /**
     * Carga el row y rellena el formulario; aborta 404 si no existe
     */
    private function loadRowOrAbort(): void
    {
        $row = DB::table('departures')->where('id', $this->depId)->first();
        if (!$row) {
            abort(404);
        }

        $this->date          = $row->date;
        $this->hour          = $row->hour;
        $this->headquarter_id= $row->headquarter_id;
        $this->price         = (float)($row->price ?? 0);
        $this->passenger     = (int)($row->passenger ?? 0);
        $this->passage       = (float)($row->passage ?? 0);
        $this->latitude      = $row->latitude;
        $this->longitude     = $row->longitude;

        // plate visible: si hay vehicle_id usa placa real; si no, legacy
        if ($row->vehicle_id) {
            $this->plate = (string) DB::table('vehicles')->where('id',$row->vehicle_id)->value('plate');
        } else {
            $this->plate = (string) $row->legacy_plate;
        }

        // Si la sede del registro no está en el catálogo visible (no-admin),
        // mantenemos el valor para que se vea, pero al guardar se validará acceso.
        // Si prefieres forzar selección a primaria/primera aquí, descomenta:
        /*
        if (!$this->isAdmin() && $this->headquarters instanceof \Illuminate\Support\Collection) {
            if (!$this->headquarters->contains('id', (int)$this->headquarter_id)) {
                $primaryId = (int) (Auth::user()?->headquarter_id ?? 0);
                if ($primaryId && $this->headquarters->contains('id', $primaryId)) {
                    $this->headquarter_id = $primaryId;
                } else {
                    $this->headquarter_id = optional($this->headquarters->first())->id;
                }
            }
        }
        */
    }

    // ==============================
    public function updatedPlate(): void
    {
        $plate = strtoupper(trim($this->plate ?? ''));
        if (strlen($plate) >= 6) {
            $this->plateExists = \Illuminate\Support\Facades\DB::table('vehicles')
                ->where('status', 'active')
                ->whereRaw('UPPER(REPLACE(plate," ","")) = ?', [str_replace(' ', '', $plate)])
                ->exists();
        } else {
            $this->plateExists = true;
        }
    }

    // Persistencia: actualizar (misma lógica del modal de editar)
    // ==============================
    public function update(): void
    {
        try {
            $this->validate();
            if (!$this->depId) return;

            // Validación de acceso a sede para no-admin (unificada)
            if (!$this->isAdmin()) {
                $allowed = $this->allowedHqIds();               // ints
                $chosen  = (int) ($this->headquarter_id ?? 0);  // int

                if (!$chosen || !in_array($chosen, $allowed, true)) {
                    $this->addError('headquarter_id', 'No tienes acceso a esta sucursal.');
                    return;
                }
            }

            // Resolver vehículo según placa (idéntico a modal)
            ['vehicle_id' => $vehicleId, 'is_support' => $isSupport, 'legacy_plate' => $legacyPlate]
                = $this->resolveVehicleByPlate($this->plate);

            $now  = now(config('app.timezone','America/Lima'));
            $hour = $this->hour ?: $now->format('H:i:s');

            $departure = \App\Models\Departure::findOrFail($this->depId);
            $departure->update([
                'is_support'     => $isSupport,
                'date'           => $this->date,
                'hour'           => $hour,
                'vehicle_id'     => $vehicleId,
                'legacy_plate'   => $legacyPlate,
                'headquarter_id' => $this->headquarter_id,
                'times'          => 1,
                'price'          => $this->price,
                'passenger'      => $this->passenger,
                'passage'        => $this->passage,
                'latitude'       => $this->latitude,
                'longitude'      => $this->longitude,
            ]);

            session()->flash('departure_success', 'Salida actualizada correctamente.');
            $this->redirectRoute('departures.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('departure_error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirectRoute('departures.index');
        }
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!$this->isAdmin()) {
            abort(403);
        }
        Departure::findOrFail($id)->delete();
        session()->flash('departure_success', 'Salida eliminada correctamente.');
        $this->redirectRoute('departures.index');
    }

    // ==============================
    // Utilidades de dominio (idénticas)
    // ==============================
    private function resolveVehicleByPlate(?string $rawPlate): array
    {
        $plate = strtoupper(trim((string)$rawPlate));  // normaliza
        $plate = preg_replace('/\s+/', '', $plate);    // quita espacios internos

        $vehicle = Vehicle::whereRaw('UPPER(TRIM(plate)) = ?', [$plate])->first();

        if ($vehicle) {
            $today = now(config('app.timezone', 'America/Lima'))->startOfDay();
            $ceased = $vehicle->termination_date && $vehicle->termination_date < $today;

            if ($vehicle->status === 'active' && !$ceased) {
                return [
                    'vehicle_id'   => $vehicle->id,
                    'is_support'   => 0,
                    'legacy_plate' => null,
                ];
            }

            // Vehículo cesado o inactivo → apoyo
            return [
                'vehicle_id'   => null,
                'is_support'   => 1,
                'legacy_plate' => $plate,
            ];
        }

        return [
            'vehicle_id'   => null,
            'is_support'   => 1,
            'legacy_plate' => $plate,
        ];
    }

    // ==============================
    // Render
    // ==============================
    public function render(): View
    {
        return view('livewire.departures.edit-departure');
    }
}
