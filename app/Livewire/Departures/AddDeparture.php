<?php

namespace App\Livewire\Departures;

use App\Models\Departure;
use App\Models\Headquarter;
use App\Models\Vehicle;
use App\Traits\NormalizesDecimals;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AddDeparture extends Component
{
    use NormalizesDecimals;

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

    // Sugerencias para los chips (consumidas por Alpine via $wire)
    public array $passageSuggestions   = [];
    public array $passengerSuggestions = [];

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

        $this->applyPriceByHeadquarter($this->headquarter_id);

        $this->passage   = 8;
        $this->passenger = 10;
        $this->autoPassenger = 10;
        $this->applyPassengerByPlate();
    }

    public function updatedHeadquarterId($value): void
    {
        $this->applyPriceByHeadquarter($value !== null && $value !== '' ? (int)$value : null);
        $this->recomputeSuggestions();
    }

    /**
     * Ultimo valor que puso el prefill en precio/pasajeros. Si el campo ya no
     * coincide es que el usuario escribio el suyo y los prefills posteriores no
     * deben pisarlo: updatedPlate corre en cada tecla de la placa (wire:model.live)
     * y sobreescribia lo tecleado, que ademas rebotaba al input y ensuciaba lo
     * que la persona estaba escribiendo.
     */
    public ?float $autoPrice = null;
    public ?int $autoPassenger = null;

    private function applyPriceByHeadquarter(?int $hqId): void
    {
        if ($this->autoPrice !== null && (float) ($this->price ?? 0) !== (float) $this->autoPrice) {
            return; // el usuario escribio su propio precio: no pisarlo
        }

        if (!$hqId) {
            $this->price = 0;
            $this->autoPrice = 0.0;
            return;
        }

        $name = mb_strtolower(trim((string) Headquarter::find($hqId)?->name));

        $normalizedPlate = preg_replace('/\s+/', '', strtoupper(trim((string) $this->plate)));
        $plateNotFound   = strlen($normalizedPlate) >= 6 && !$this->plateExists;

        switch ($name) {
            case 'huaycan':
                $this->price = $plateNotFound ? 7 : 3;
                break;
            case 'h.gamarra':
                $this->price = $plateNotFound ? 7 : 4;
                break;
            case 'la victoria':
                if (strlen($normalizedPlate) < 6) {
                    $this->price = 4;
                    break;
                }

                if ($plateNotFound) {
                    $this->price = 2;
                    break;
                }

                ['vehicle_id' => $vehicleId] = $this->resolveVehicleByPlate($this->plate);

                $query = Departure::where('date', $this->date)
                    ->where('headquarter_id', $hqId);

                if ($vehicleId) {
                    $query->where('vehicle_id', $vehicleId);
                } else {
                    $query->whereRaw('UPPER(REPLACE(legacy_plate," ","")) = ?', [$normalizedPlate]);
                }

                $this->price = $query->exists() ? 3 : 4;
                break;
            default:
                $this->price = 0;
                break;
        }

        $this->autoPrice = (float) ($this->price ?? 0);
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

        if ($this->headquarter_id) {
            $this->applyPriceByHeadquarter($this->headquarter_id);
        }

        $this->applyPassengerByPlate();
        $this->recomputeSuggestions();
    }

    /**
     * Recalcula sugerencias de chips para pasaje y pasajeros.
     * - passage: top 3 valores más frecuentes en últimos 90 días para la
     *   placa actual; si no hay placa, top 3 para la sede actual.
     * - passenger: deriva [seats, seats-1, seats-2] del vehículo + top 3 histórico.
     */
    protected function recomputeSuggestions(): void
    {
        $vehicle = null;
        $plate = strtoupper(trim((string) $this->plate));
        $plate = preg_replace('/\s+/', '', $plate);
        if (strlen($plate) >= 6) {
            $vehicle = Vehicle::whereRaw('UPPER(TRIM(plate)) = ?', [$plate])->first();
        }

        $since = now(config('app.timezone','America/Lima'))->subDays(90)->toDateString();

        // ---------- Pasaje ----------
        $passageQ = \DB::table('departures')->where('passage', '>', 0)->where('date', '>=', $since);
        if ($vehicle?->id) {
            $passageQ->where('vehicle_id', $vehicle->id);
        } elseif ($this->headquarter_id) {
            $passageQ->where('headquarter_id', $this->headquarter_id);
        }
        $this->passageSuggestions = $passageQ
            ->select('passage', \DB::raw('COUNT(*) as c'))
            ->groupBy('passage')
            ->orderByDesc('c')
            ->limit(3)
            ->pluck('passage')
            ->map(fn ($v) => number_format((float) $v, 2, '.', ''))
            ->values()
            ->all();

        // ---------- Pasajeros ----------
        $derived = [];
        if ($vehicle && $vehicle->seats > 0) {
            $derived = collect([$vehicle->seats, $vehicle->seats - 1, $vehicle->seats - 2])
                ->filter(fn ($v) => $v >= 1)
                ->map(fn ($v) => (string) (int) $v)
                ->unique()
                ->values()
                ->all();
        }

        $passQ = \DB::table('departures')->where('passenger', '>', 0)->where('date', '>=', $since);
        if ($vehicle?->id) {
            $passQ->where('vehicle_id', $vehicle->id);
        } elseif ($this->headquarter_id) {
            $passQ->where('headquarter_id', $this->headquarter_id);
        }
        $hist = $passQ
            ->select('passenger', \DB::raw('COUNT(*) as c'))
            ->groupBy('passenger')
            ->orderByDesc('c')
            ->limit(3)
            ->pluck('passenger')
            ->map(fn ($v) => (string) (int) $v)
            ->all();

        // Mezcla: derived primero, luego histórico, sin duplicados
        $this->passengerSuggestions = collect($derived)
            ->merge($hist)
            ->unique()
            ->values()
            ->take(5)
            ->all();
    }

    private function applyPassengerByPlate(): void
    {
        if ($this->autoPassenger !== null && (int) ($this->passenger ?? 0) !== $this->autoPassenger) {
            return; // el usuario escribio sus propios pasajeros: no pisarlos
        }

        $normalizedPlate = preg_replace('/\s+/', '', strtoupper(trim((string) $this->plate)));

        if (strlen($normalizedPlate) >= 6 && $this->plateExists) {
            $passengers = DB::table('vehicles')
                ->where('status', 'active')
                ->whereRaw('UPPER(REPLACE(plate," ","")) = ?', [$normalizedPlate])
                ->value('passengers');
            $this->passenger = (int) ($passengers ?: 10);
        } else {
            $this->passenger = 10;
        }

        $this->autoPassenger = (int) $this->passenger;
    }

    // Persistencia: guardar (misma lógica del modal original, sin dispatch)
    // ==============================
    public function save(): void
    {
        if (empty($this->latitude) || empty($this->longitude)) {
            $this->dispatch('errorAlert', ['message' => 'Activa tu ubicación para poder agregar']);
            return;
        }

        $this->normalizeDecimalProps(['price', 'passenger', 'passage']);
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
        $this->passenger = 10;
        $this->passage = 8;
        $this->autoPrice = null;
        $this->autoPassenger = 10;
        $this->latitude = null;
        $this->longitude = null;

        $this->applyPriceByHeadquarter($this->headquarter_id);
        $this->applyPassengerByPlate();
    }

    // ==============================
    // Render
    // ==============================
    public function render(): View
    {
        return view('livewire.departures.add-departure');
    }
}
