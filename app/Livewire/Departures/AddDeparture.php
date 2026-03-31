<?php

namespace App\Livewire\Departures;

use App\Models\Headquarter;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AddDeparture extends Component
{
    // ==============================
    // Campos del formulario (idénticos al modal original)
    // ==============================
    public ?string $plate = null;
    public bool $plateExists = true;
    public ?string $date = null;
    public ?int    $headquarter_id = null;
    public ?float  $price;
    public ?int    $passenger;
    public ?float  $passage;

    public ?string $hour = null;
    public ?string $latitude = null;
    public ?string $longitude = null;

    /**
     * Catálogos para selects (id, name)
     * @var \Illuminate\Support\Collection|array
     */
    public $listHeadquarters = [];
    public $headquarters;

    // ====== Roles/Sedes (misma idea que en Index)
    private array $userHqIds = [];

    // ==============================
    // Validaciones (idénticas al modal original)
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
            'latitude'       => ['required','string'],
            'longitude'      => ['required','string'],
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
    // Helpers de rol/sedes (idéntico enfoque)
    // ==============================
    /** Retorna true si el usuario autenticado tiene el rol indicado (insensible a mayúsculas). */
    private function userHasRole(string $needle): bool
    {
        $u = Auth::user();
        if (!$u) return false;

        $needle = mb_strtolower($needle);
        return $u->getRoleNames()
            ->map(fn ($r) => mb_strtolower($r))
            ->contains($needle);
    }

    /** Atajo legible para admin */
    private function isAdmin(): bool
    {
        return $this->userHasRole('director') || $this->userHasRole('gerente') || $this->userHasRole('administrador');
    }

    /** Carga ids de sedes asignadas al usuario (N:N + primaria por compatibilidad) */
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

    /** Sedes permitidas (pivot + primaria), todas como int y únicas */
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
    public function mount(): void
    {
        // Cargar ids de sedes para consistencia
        $this->loadUserHeadquarters();

        $primaryId = (int) (Auth::user()?->headquarter_id ?? 0);

        // Catálogo de sedes visibles según rol:
        if ($this->isAdmin()) {
            // Admin ve todas; primaria (si tiene) va primero
            $this->headquarters = Headquarter::where('status','active')
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$primaryId ?: 0])
                ->orderBy('name')
                ->get(['id','name']);
        } else {
            // Usuario no admin: solo permitidas (pivot + primaria), primaria primero
            $ids = $this->allowedHqIds() ?: [-1];
            $this->headquarters = Headquarter::where('status','active')
                ->whereIn('id', $ids)
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$primaryId ?: 0])
                ->orderBy('name')
                ->get(['id','name']);
        }

        $this->listHeadquarters = $this->headquarters;

        // Defaults fecha/hora (America/Lima)
        $now = now(config('app.timezone','America/Lima'));
        $this->date = $this->date ?: $now->toDateString();
        $this->hour = $this->hour ?: $now->format('H:i:s');

        // Seleccionar por defecto la sede primaria si existe en la lista; sino, la primera disponible
        if (!$this->headquarter_id) {
            if ($primaryId && $this->headquarters->contains('id', $primaryId)) {
                $this->headquarter_id = $primaryId;
            } else {
                $this->headquarter_id = optional($this->headquarters->first())->id;
            }
        }

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

    // Persistencia: guardar (misma lógica del modal original, sin dispatch)
    // ==============================
    public function save(): void
    {
        if (empty($this->latitude) || empty($this->longitude)) {
            $this->dispatch('errorAlert', ['message' => 'Activa tu ubicación para poder agregar']);
            return;
        }

        $this->validate();

        // Validación de acceso a sede para no-admin (unificada)
        if (!$this->isAdmin()) {
            $allowed = $this->allowedHqIds();               // ints
            $chosen  = (int) ($this->headquarter_id ?? 0);  // int

            if (!$chosen || !in_array($chosen, $allowed, true)) {
                $this->addError('headquarter_id', 'No tienes acceso a esta sucursal.');
                return;
            }
        }

        // Resolver vehículo / soporte tal cual el modal original
        ['vehicle_id' => $vehicleId, 'is_support' => $isSupport, 'legacy_plate' => $legacyPlate]
            = $this->resolveVehicleByPlate($this->plate);

        $userId = Auth::id();
        $now    = now(config('app.timezone','America/Lima'));
        $hour   = $this->hour ?: $now->format('H:i:s');

        \App\Models\Departure::create([
            'is_support'     => $isSupport,
            'date'           => $this->date,
            'hour'           => $hour,
            'vehicle_id'     => $vehicleId,
            'legacy_plate'   => $legacyPlate,
            'headquarter_id' => $this->headquarter_id,
            'user_id'        => $userId,
            'times'          => 1,
            'price'          => $this->price,
            'passenger'      => $this->passenger,
            'passage'        => $this->passage,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
        ]);

        // Mostrar alert Bootstrap en la vista (sin redirección)
        session()->flash('departure_success', 'Salida creada correctamente.');
        $this->resetForm();

        redirect()->route('departures.index');
    }

    // ==============================
    // Utilidades de dominio (idénticas)
    // ==============================
    /**
     * Normaliza la placa y resuelve vehicle_id:
     * - Si existe vehículo: is_support=0 y legacy_plate=null
     * - Si no existe: is_support=1 y se usa legacy_plate
     *
     * @param string|null $rawPlate
     * @return array{vehicle_id:int|null,is_support:int,legacy_plate:string|null}
     */
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

    /**
     * Restablece el formulario a valores por defecto (fecha/hora actuales),
     * y asegura selección de sede primaria o primera opción disponible.
     */
    private function resetForm(): void
    {
        $now = now(config('app.timezone','America/Lima'));
        $this->plate = null;
        $this->date  = $now->toDateString();
        $this->hour  = $now->format('H:i:s');

        $primaryId = (int) (Auth::user()?->headquarter_id ?? 0);
        $allowed   = $this->allowedHqIds();

        if ($this->headquarters instanceof \Illuminate\Support\Collection && $this->headquarters->isNotEmpty()) {
            if ($primaryId && $this->headquarters->contains('id', $primaryId)) {
                $this->headquarter_id = $primaryId;
            } else {
                $this->headquarter_id = optional($this->headquarters->first())->id;
            }
        } else {
            // Fallback si aún no hubiera lista cargada (no debería ocurrir)
            $this->headquarter_id = $primaryId ?: ($allowed[0] ?? null);
        }

        $this->price = 0;
        $this->passenger = 0;
        $this->passage = 0;
        $this->latitude = null;
        $this->longitude = null;
    }

    // ==============================
    // Render
    // ==============================
    public function render(): View
    {
        return view('livewire.departures.add-departure');
    }
}
