<?php

namespace App\Livewire\Departures;

use App\Models\Departure;
use App\Models\Headquarter;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire para gestionar y listar "salidas" (departures).
 *
 * Ofrece:
 * - Filtros por fecha, placa, usuario y sede.
 * - Modo agrupado/detalle.
 * - Creación/edición con autocompletado de vehículo por placa.
 * - Totales y totales combinados de registros "apoyo" (sin vehicle_id).
 */
class Index extends Component
{
    // ==============================
    // Estado / Filtros de la pantalla
    // ==============================

    /**
     * Tipo de búsqueda: 1=Placa, 2=Usuario, 3=Sucursal.
     * @var int
     */
    public int $searchType = 1;

    /**
     * Texto de búsqueda libre (según $searchType).
     * @var string|null
     */
    public ?string $searchText = null;

    /**
     * Filtro adicional por nombre de usuario (independiente de searchType).
     * @var string|null
     */
    public ?string $searchUser = null;

    /**
     * Historial del buscador servido desde la BD. Se calculan las dos listas a la vez
     * porque searchType viaja diferido con @entangle: el cliente elige cual mostrar.
     * @var array<int,string>
     */
    public array $plateSuggestions = [];
    public array $userSuggestions  = [];

    /**
     * Sección a mostrar: all, principal, apoyo.
     * @var string
     */
    public string $showSection = 'all';

    /**
     * Fecha inicial del rango (YYYY-MM-DD).
     * @var string|null
     */
    public ?string $fromDate = null;

    /**
     * Fecha final del rango (YYYY-MM-DD).
     * @var string|null
     */
    public ?string $toDate = null;

    /**
     * Fechas del UI (no disparan consulta hasta presionar "Aplicar").
     * @var string|null
     */
    public ?string $uiFromDate = null;
    public ?string $uiToDate   = null;

    /**
     * Último rango aplicado (para mostrar “cambios sin aplicar” si lo deseas).
     * @var string|null
     */
    public ?string $lastAppliedFrom = null;
    public ?string $lastAppliedTo   = null;

    /**
     * Colección de sedes activas para combos y filtros.
     * @var \Illuminate\Support\Collection|array
     */
    public $headquarters;

    /**
     * Modo de agrupación ON/OFF (false=Detalle, true=Agrupado).
     * @var bool
     */
    public bool $groupMode = false;

    public bool $byHQ;

    /**
     * Estado sincronizado con query string.
     * @var array<string, array<string, mixed>>
     */
    protected $queryString = [
        'searchType' => ['except' => 1],
        'searchText' => ['except' => null],
        'searchUser' => ['except' => null],
        'showSection' => ['except' => 'all'],
        'fromDate'   => ['except' => null],
        'toDate'     => ['except' => null],
        'groupMode'  => ['except' => false]
    ];

    // ==============================
    // Campos del formulario (crear/editar)
    // ==============================

    /**
     * Placa ingresada (se resuelve a vehicle_id o queda como legacy_plate).
     * @var string|null
     */
    public ?string $plate = null;

    /**
     * Fecha del servicio (YYYY-MM-DD).
     * @var string|null
     */
    public ?string $date = null;

    /**
     * ID de sede (headquarters.id).
     * @var int|null
     */
    public ?int $headquarter_id = null;

    /**
     * Precio del servicio (monto cobrado).
     * @var float|null
     */
    public ?float $price = 0;

    /**
     * Número de pasajeros.
     * @var int|null
     */
    public ?int $passenger = 0;

    /**
     * Precio por pasajero (para total_pasaje).
     * @var float|null
     */
    public ?float $passage = 0;

    /** Autollenados desde cliente o server */

    /**
     * Hora del servicio (HH:MM).
     * @var string|null
     */
    public ?string $hour = null;

    /**
     * Latitud desde geolocalización del navegador.
     * @var string|null
     */
    public ?string $latitude = null;

    /**
     * Longitud desde geolocalización del navegador.
     * @var string|null
     */
    public ?string $longitude = null;

    /**
     * Id del registro a editar (si aplica).
     * @var int|null
     */
    public ?int $depId = null;

    /**
     * Lista simple de sedes para selects (id, name).
     * @var \Illuminate\Support\Collection|array
     */
    public $listHeadquarters = [];

    /**
     * Reglas de validación para crear/actualizar.
     * @return array<string, array<int, string>>
     */
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

    /**
     * Mensajes personalizados de validación.
     * @return array<string, string>
     */
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
    // === INTEGRACIÓN ROLES/SEDES: helpers de rol y sedes del usuario
    // ==============================

    /** IDs de sedes asignadas al usuario autenticado (pivote + primaria por compatibilidad) */
    private array $userHqIds = [];

    /** Cache de roles para evitar reconsultar en cada render */
    private ?bool $cachedIsAdmin = null;
    private ?bool $cachedIsController = null;

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

    /** Atajos legibles (cacheados tras primera llamada) */
    private function isAdmin(): bool
    {
        return $this->cachedIsAdmin ??= ($this->userHasRole('director') || $this->userHasRole('gerente') || $this->userHasRole('administrador'));
    }
    private function isController(): bool
    {
        return $this->cachedIsController ??= $this->userHasRole('controlador');
    }

    /** Carga ids de sedes asignadas al usuario (N:N + primaria en users.headquarter_id) */
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

    // ==============================
    // Ciclo de vida
    // ==============================

    /**
     * Inicializa estado del componente:
     * - Rango de fechas por defecto (hoy..hoy).
     * - Carga de sedes activas (limitadas para controller en UI).
     * - Defaults del formulario (fecha/hora actuales).
     * @return void
     */
    public function mount(): void
    {
        // === INTEGRACIÓN ROLES/SEDES: preparar sedes para filtros/combos
        $this->loadUserHeadquarters();

        // Default: hoy (America/Lima)
        $today = now(config('app.timezone', 'America/Lima'))->toDateString();
        $this->fromDate ??= $today;
        $this->toDate   ??= $today;

        // UI desacoplado del rango aplicado
        $this->uiFromDate = $this->fromDate;
        $this->uiToDate   = $this->toDate;

        // Marcadores de “aplicado”
        $this->lastAppliedFrom = $this->fromDate;
        $this->lastAppliedTo   = $this->toDate;

        // Catálogo de sedes que verá el usuario en filtros/combos:
        if ($this->isAdmin()) {
            $this->headquarters = Headquarter::where('status', 'active')
                ->orderBy('name')->get(['id','name']);
            $this->listHeadquarters = $this->headquarters;
        } else {
            $ids = $this->userHqIds ?: [-1];
            $this->headquarters = Headquarter::where('status','active')
                ->whereIn('id', $ids)->orderBy('name')->get(['id','name']);
            $this->listHeadquarters = $this->headquarters;
        }

        // defaults para el form
        $now = now(config('app.timezone','America/Lima'));
        $this->date = $this->date ?: $now->toDateString();
        $this->hour = $this->hour ?: $now->format('H:i:s');

        $this->byHQ = ($this->searchType ?? 1) == 3;

        $this->loadSearchSuggestions();

        // Si viene del RMP (showSection != all), forzar modo agrupado por placa
        if ($this->showSection !== 'all') {
            $this->groupMode = true;
        }

    }


    public function updatedSearchType($value)
    {
        $this->searchType = (int) $value;
    }

    /**
     * Historial del buscador. Respeta el mismo recorte por usuario que las consultas
     * del listado: quien no es admin solo ve placas y usuarios de sus propias salidas.
     * Se calcula una vez por carga de pagina, no en cada render.
     */
    private function loadSearchSuggestions(): void
    {
        $since   = now(config('app.timezone', 'America/Lima'))->subDays(90)->toDateString();
        $isAdmin = $this->isAdmin();
        $uid     = Auth::id();

        // La busqueda por placa mira v.plate en las salidas con vehiculo de flota y
        // d.legacy_plate en las de apoyo, asi que la sugerencia une ambas fuentes.
        $fleet = DB::table('departures as d')
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->where('d.date', '>=', $since)
            ->whereNotNull('v.plate')->where('v.plate', '!=', '');
        if (!$isAdmin) {
            $fleet->where('d.user_id', $uid);
        }
        $fleet = $fleet->select('v.plate as p', DB::raw('COUNT(*) as c'))
            ->groupBy('v.plate')->orderByDesc('c')->limit(40)->pluck('c', 'p');

        $support = DB::table('departures as d')
            ->where('d.date', '>=', $since)
            ->whereNotNull('d.legacy_plate')->where('d.legacy_plate', '!=', '');
        if (!$isAdmin) {
            $support->where('d.user_id', $uid);
        }
        $support = $support->select('d.legacy_plate as p', DB::raw('COUNT(*) as c'))
            ->groupBy('d.legacy_plate')->orderByDesc('c')->limit(40)->pluck('c', 'p');

        $byPlate = [];
        foreach ([$fleet, $support] as $set) {
            foreach ($set as $plate => $count) {
                $key = strtoupper(trim((string) $plate));
                if ($key === '') {
                    continue;
                }
                $byPlate[$key] = ($byPlate[$key] ?? 0) + (int) $count;
            }
        }
        arsort($byPlate);
        $this->plateSuggestions = array_map('strval', array_slice(array_keys($byPlate), 0, 30));

        $users = DB::table('departures as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.date', '>=', $since)
            ->whereNotNull('u.username')->where('u.username', '!=', '');
        if (!$isAdmin) {
            $users->where('d.user_id', $uid);
        }
        $this->userSuggestions = $users->select('u.username as n', DB::raw('COUNT(*) as c'))
            ->groupBy('u.username')->orderByDesc('c')->limit(30)
            ->pluck('n')->map(fn ($v) => (string) $v)->values()->all();
    }

    // ==============================
    // Acciones UI: abrir modales
    // ==============================

    /**
     * Abre el modal de creación, resetea validaciones y formulario.
     * Dispara evento JS para enfoque y geolocalización.
     * @return void
     */
    public function openAddWindow(): void
    {
        $route = route('departures.add');
        $this->dispatch('url-open',["url" => $route]);
    }

    // ==============================
    // Persistencia: crear / actualizar
    // ==============================

    /**
     * Crea un nuevo registro en departures:
     * - Valida inputs.
     * - Resuelve vehicle_id/is_support según placa.
     * - Inserta registro y notifica.
     * @return void
     */
    public function save(): void
    {
        if (empty($this->latitude) || empty($this->longitude)) {
            $this->dispatch('errorAlert', ['message' => 'Activa tu ubicación para poder agregar']);
            return;
        }

        $this->validate();

        // === INTEGRACIÓN ROLES/SEDES: controller solo puede usar sedes asignadas
        if (!$this->isAdmin() && $this->headquarter_id && !in_array((int)$this->headquarter_id, $this->userHqIds, true)) {
            $this->addError('headquarter_id', 'No tienes acceso a esta sucursal.');
            return;
        }

        // Normaliza y resuelve vehículo
        ['vehicle_id' => $vehicleId, 'is_support' => $isSupport, 'legacy_plate' => $legacyPlate, 'norm_plate' => $normPlate]
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

        $this->resetForm();
        $this->mount();
        $this->dispatch('modal-close', ['name' => 'modalAddDeparture']);
        $this->dispatch('successAlert', ['message' => 'Salida creada correctamente']);
    }

    /**
     * Abre modal de edición y precarga el formulario desde BD.
     * @param int $id ID del departure a editar
     * @return void
     */
    public function openEditWindow(int $id): void
    {
        $route = route('departures.edit',["id" => $id]);

        $this->dispatch('url-open',["url" => $route]);
    }

    /**
     * Actualiza un registro existente:
     * - Valida inputs.
     * - Recalcula vehicle_id/is_support según placa.
     * @return void
     */
    public function update(): void
    {
        $this->validate();
        if (!$this->depId) return;

        // === INTEGRACIÓN ROLES/SEDES: controller solo puede usar sedes asignadas
        if (!$this->isAdmin() && $this->headquarter_id && !in_array((int)$this->headquarter_id, $this->userHqIds, true)) {
            $this->addError('headquarter_id', 'No tienes acceso a esta sucursal.');
            return;
        }

        ['vehicle_id' => $vehicleId, 'is_support' => $isSupport, 'legacy_plate' => $legacyPlate, 'norm_plate' => $normPlate]
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

        $this->resetForm();
        $this->mount();
        $this->dispatch('modal-close', ['name' => 'modalEditDeparture']);
        $this->dispatch('successAlert', ['message' => 'Salida actualizada correctamente']);
    }

    // ==============================
    // Helpers UI/estado
    // ==============================

    /**
     * Restablece el formulario a valores por defecto (fecha/hora actuales, montos en 0).
     * @return void
     */
    private function resetForm(): void
    {
        $now = now(config('app.timezone','America/Lima'));
        $this->depId = null;
        $this->plate = null;
        $this->date  = $now->toDateString();
        $this->hour  = $now->format('H:i:s');
        $this->headquarter_id = null;
        $this->price = 0;
        $this->passenger = 0;
        $this->passage = 0;
        $this->latitude = null;
        $this->longitude = null;
    }

    /**
     * Hook Livewire: al cambiar fromDate normaliza el rango.
     * @return void
     */
    public function updatedFromDate(): void
    {
        $this->normalizeRange();
    }

    /**
     * Hook Livewire: al cambiar toDate normaliza el rango.
     * @return void
     */
    public function updatedToDate(): void
    {
        $this->normalizeRange();
    }

    /**
     * Corrige si el usuario invierte el rango (fromDate > toDate).
     * @return void
     */
    private function normalizeRange(): void
    {
        if ($this->fromDate && $this->toDate && $this->fromDate > $this->toDate) {
            [$this->fromDate, $this->toDate] = [$this->toDate, $this->fromDate];
        }
    }

    /**
     * UI: normaliza el rango ingresado en los inputs de UI.
     */
    public function updatedUiFromDate(): void { $this->normalizeUiRange(); }
    public function updatedUiToDate(): void   { $this->normalizeUiRange(); }
    private function normalizeUiRange(): void
    {
        if ($this->uiFromDate && $this->uiToDate && $this->uiFromDate > $this->uiToDate) {
            [$this->uiFromDate, $this->uiToDate] = [$this->uiToDate, $this->uiFromDate];
        }
    }

    /**
     * Aplica el rango de fechas desde los inputs de UI al filtro real.
     */
    public function applyDateRange(): void
    {


        $this->validate([
            'uiFromDate' => ['required','date'],
            'uiToDate'   => ['required','date'],
        ], [], [
            'uiFromDate' => 'fecha inicio',
            'uiToDate'   => 'fecha fin',
        ]);



        $from = $this->uiFromDate;
        $to   = $this->uiToDate;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        // Asigna al filtro “aplicado” (estos sí afectan las queries)
        $this->fromDate = $from;
        $this->toDate   = $to;

        // Marca como aplicado
        $this->lastAppliedFrom = $from;
        $this->lastAppliedTo   = $to;

    }

    // ==============================
    // Construcción de consultas (Query Builders)
    // ==============================

    /**
     * Base genérica con joins para listar departures + vehículo/usuario/sede.
     * Aplica filtros de fecha y de búsqueda (placa/usuario/sede).
     * Descarta vehículos inactivos.
     *
     * @return \Illuminate\Database\Query\Builder
     */


    /**
     * Calcula totales (conteo y sumas) del dataset filtrado por baseQuery().
     * @return object{records:int,times_total:int,price_total:float,passengers_total:int,passage_total:float,total_pasaje_total:float}
     */

    /**
     * Cambia entre modo Detalle y Agrupado.
     * @return void
     */
    public function toggleGroup(): void
    {
        $this->groupMode = !$this->groupMode;
    }

    // ==============================
    // Render principal (arma datasets y pasa al Blade)
    // ==============================

    /**
     * Construye datasets (principal y apoyo), totales y renderiza la vista.
     * @return \Illuminate\Contracts\View\View
     */
    public function render(): View
    {
        $emptyTotals = (object)['records'=>0,'times_total'=>0,'price_total'=>0,'passengers_total'=>0,'passage_total'=>0,'total_pasaje_total'=>0];

        // ====== PRINCIPAL (vehículos existentes activos) — 1 sola query ======
        if ($this->groupMode) {
            $rows = $this->existingBase()
                ->selectRaw('
                    v.plate as plate,
                    ANY_VALUE(h.name) as headquarter_name,
                    ANY_VALUE(u.username) as user_name,
                    COALESCE(SUM(d.times), 0)  as k1,
                    COALESCE(SUM(d.price), 0)  as p1,
                    COALESCE(SUM(d.passenger), 0) as pasajeros,
                    COALESCE(SUM(d.passage), 0)   as pasaje,
                    COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)), 0) as total_pasaje,
                    MIN(d.date) as from_date,
                    MAX(d.date) as to_date,
                    MAX(d.date) as date
                ')
                ->groupBy('v.plate')
                ->orderBy('v.plate')
                ->get();

            // Ordinal en PHP
            $rows = $rows->values()->map(function ($r, $i) {
                $r->ordinal = $i + 1;
                return $r;
            });
        } else {
            $inner = $this->existingBase()
                ->selectRaw('
                    d.id, d.date, d.hour, d.times, d.price, d.passenger, d.passage,
                    d.latitude, d.longitude,
                    v.plate as plate,
                    h.name as headquarter_name, u.username as user_name,
                    COALESCE(d.passenger,0)*COALESCE(d.passage,0) as total_pasaje,
                    LAG(CONCAT(d.date, " ", d.hour)) OVER (ORDER BY d.date, d.hour, d.id) as prev_dt
                ')
                ->orderBy('d.id');

            $rows = DB::table(DB::raw("({$inner->toSql()}) as sub"))
                ->mergeBindings($inner)
                ->selectRaw('*, CASE WHEN prev_dt IS NOT NULL AND TIMESTAMPDIFF(SECOND, prev_dt, CONCAT(date, " ", hour)) > 0 THEN SEC_TO_TIME(TIMESTAMPDIFF(SECOND, prev_dt, CONCAT(date, " ", hour))) ELSE NULL END as freq')
                ->get();
        }

        // Totales en PHP (evita query separada)
        $totals = $this->computeTotals($rows);

        // ====== APOYO (is_support = 1) — 1 sola query ======
        if ($this->groupMode) {
            $supportRows = $this->supportBase()
                ->selectRaw('
                    d.legacy_plate as plate,
                    ANY_VALUE(h.name) as headquarter_name,
                    ANY_VALUE(u.username) as user_name,
                    COALESCE(SUM(d.times), 0)  as k1,
                    COALESCE(SUM(d.price), 0)  as p1,
                    COALESCE(SUM(d.passenger), 0) as pasajeros,
                    COALESCE(SUM(d.passage), 0)   as pasaje,
                    COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)), 0) as total_pasaje,
                    MIN(d.date) as from_date,
                    MAX(d.date) as to_date,
                    MAX(d.date) as date
                ')
                ->groupBy('d.legacy_plate')
                ->orderBy('d.legacy_plate')
                ->get();

            $supportRows = $supportRows->values()->map(function ($r, $i) {
                $r->ordinal = $i + 1;
                return $r;
            });
        } else {
            $innerSupport = $this->supportBase()
                ->selectRaw('
                    d.id, d.date, d.hour, d.times, d.price, d.passenger, d.passage,
                    d.latitude, d.longitude,
                    d.legacy_plate as plate,
                    h.name as headquarter_name, u.username as user_name,
                    COALESCE(d.passenger,0)*COALESCE(d.passage,0) as total_pasaje,
                    LAG(CONCAT(d.date, " ", d.hour)) OVER (ORDER BY d.date, d.hour, d.id) as prev_dt
                ')
                ->orderBy('d.id');

            $supportRows = DB::table(DB::raw("({$innerSupport->toSql()}) as sub"))
                ->mergeBindings($innerSupport)
                ->selectRaw('*, CASE WHEN prev_dt IS NOT NULL AND TIMESTAMPDIFF(SECOND, prev_dt, CONCAT(date, " ", hour)) > 0 THEN SEC_TO_TIME(TIMESTAMPDIFF(SECOND, prev_dt, CONCAT(date, " ", hour))) ELSE NULL END as freq')
                ->get();
        }

        // Totales en PHP
        $supportTotals = $this->computeTotals($supportRows);
        $grandTotals   = $this->sumTotals($totals, $supportTotals);

        return view('livewire.departures.index', [
            'rows'           => $rows,
            'totals'         => $totals,
            'supportRows'    => $supportRows,
            'supportTotals'  => $supportTotals,
            'groupMode'      => $this->groupMode,
            'grandTotals'    => $grandTotals,
            'headquarters'   => $this->headquarters,
            'showSection'    => $this->showSection,
        ]);
    }

    // ==============================
    // Bases específicas: apoyo / existentes
    // ==============================

    /**
     * Base de registros de apoyo (is_support=1), sin join con vehicles.
     * Aplica filtros de fecha y texto (legacy_plate / usuario / sede).
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function supportBase(): Builder
    {
        $q = DB::table('departures as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->where('d.is_support', 1);

        if ($this->fromDate && $this->toDate)       $q->whereBetween('d.date', [$this->fromDate, $this->toDate]);
        elseif ($this->fromDate)                     $q->whereDate('d.date', '>=', $this->fromDate);
        elseif ($this->toDate)                       $q->whereDate('d.date', '<=', $this->toDate);

        $term = trim((string)($this->searchText ?? ''));
        if ($term !== '') {
            switch ((int)$this->searchType) {
                case 1: $q->where('d.legacy_plate', 'like', '%'.strtoupper($term).'%'); break;
                case 2: $q->where('u.username', 'like', '%'.$term.'%'); break;
                case 3:
                    if (is_numeric($term)) $q->where('h.id', (int)$term);
                    else $q->where('h.name', 'like', '%'.$term.'%');
                    break;
            }
        }

        // Filtro adicional por nombre de usuario
        $userTerm = trim((string)($this->searchUser ?? ''));
        if ($userTerm !== '') {
            $q->where('u.name', 'like', '%'.$userTerm.'%');
        }

        // === INTEGRACIÓN ROLES/SEDES: admin ve todo; controller solo lo suyo
        if (!$this->isAdmin()) {
            $q->where('d.user_id', Auth::id());
        }

        return $q;
    }

    /**
     * Calcula totales para una base dada (existingBase o supportBase).
     * Clona sin 'orders'/'columns' para evitar conflictos.
     *
     * @param \Illuminate\Database\Query\Builder $base
     * @return object{records:int,times_total:int,price_total:float,passengers_total:int,passage_total:float,total_pasaje_total:float}
     */
    private function totalsFor($base): object
    {
        $row = $base->cloneWithout(['orders','columns'])
            ->selectRaw('
            COUNT(*) as records,
            COALESCE(SUM(d.times),0) as times_total,
            COALESCE(SUM(d.price),0) as price_total,
            COALESCE(SUM(d.passenger),0) as passengers_total,
            COALESCE(SUM(d.passage),0) as passage_total,
            COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)),0) as total_pasaje_total
        ')
            ->first();

        return $row ?: (object)[
            'records'=>0,'times_total'=>0,'price_total'=>0,
            'passengers_total'=>0,'passage_total'=>0,'total_pasaje_total'=>0
        ];
    }

    /**
     * Calcula totales desde una colección ya obtenida (evita query adicional).
     */
    private function computeTotals($collection): object
    {
        if ($collection->isEmpty()) {
            return (object)['records'=>0,'times_total'=>0,'price_total'=>0,'passengers_total'=>0,'passage_total'=>0,'total_pasaje_total'=>0];
        }

        // Detectar si es modo agrupado (tiene k1/p1) o detalle (tiene times/price)
        $first = $collection->first();
        $isGrouped = property_exists($first, 'k1');

        return (object)[
            'records'            => $isGrouped ? (int) $collection->sum('k1') : $collection->count(),
            'times_total'        => (int) $collection->sum($isGrouped ? 'k1' : 'times'),
            'price_total'        => (float) $collection->sum($isGrouped ? 'p1' : 'price'),
            'passengers_total'   => (int) $collection->sum($isGrouped ? 'pasajeros' : 'passenger'),
            'passage_total'      => (float) $collection->sum($isGrouped ? 'pasaje' : 'passage'),
            'total_pasaje_total' => (float) $collection->sum('total_pasaje'),
        ];
    }

    /**
     * Base de registros con vehicle_id (JOIN vehicles) y vehicles.status='active'.
     * Aplica filtros de fecha y de búsqueda (placa/usuario/sede).
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function existingBase(): Builder
    {
        $q = DB::table('departures as d')
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->where('v.status', 'active');

        // Fechas
        if ($this->fromDate && $this->toDate)       $q->whereBetween('d.date', [$this->fromDate, $this->toDate]);
        elseif ($this->fromDate)                     $q->whereDate('d.date', '>=', $this->fromDate);
        elseif ($this->toDate)                       $q->whereDate('d.date', '<=', $this->toDate);

        // Filtros
        $term = trim((string)($this->searchText ?? ''));
        if ($term !== '') {
            switch ((int)$this->searchType) {
                case 1: $q->where('v.plate', 'like', '%'.strtoupper($term).'%'); break;
                case 2: $q->where('u.username', 'like', '%'.$term.'%'); break;
                case 3:
                    if (is_numeric($term)) $q->where('h.id', (int)$term);
                    else $q->where('h.name', 'like', '%'.$term.'%');
                    break;
            }
        }

        // Filtro adicional por nombre de usuario
        $userTerm = trim((string)($this->searchUser ?? ''));
        if ($userTerm !== '') {
            $q->where('u.name', 'like', '%'.$userTerm.'%');
        }

        // === INTEGRACIÓN ROLES/SEDES: admin ve todo; controller solo lo suyo
        if (!$this->isAdmin()) {
            $q->where('d.user_id', Auth::id());
        }

        return $q;
    }

    // ==============================
    // Acciones de reporte/export
    // ==============================

    /**
     * Abre reporte mensual (GET a route('departures.monthly')).
     * @return void
     */
    public function reportMonthly(): void
    {
        $route = route('departures.monthly');
        $this->dispatch('url-open',["url" => $route]);
    }

    /**
     * Abre reporte RMP (GET a route('departures.rmp')).
     * @return void
     */
    public function reportRmp(): void
    {
        $route = route('departures.rmp' );
        $this->dispatch('url-open',["url" => $route]);
    }

    /**
     * Abre reporte estadístico (GET a route('departures.stats')).
     * @return void
     */
    public function reportStats(): void
    {
        $route = route('departures.stats' );
        $this->dispatch('url-open',["url" => $route]);
    }

    /**
     * Exporta (GET a route('exports.departures')) con filtros actuales.
     * @return void
     */
    public function export(): void
    {
        $route = route('exports.departures',
            [   "searchType" => $this->searchType,
                "searchText" => $this->searchText,
                "fromDate" => $this->fromDate,
                "toDate" => $this->toDate,
                "groupMode" => $this->groupMode
            ]);

        $this->dispatch('url-open',["url" => $route]);
    }

    // ==============================
    // Utilidades de dominio
    // ==============================

    /**
     * Normaliza la placa y resuelve vehicle_id:
     * - Si existe vehículo: is_support=0 y legacy_plate=null
     * - Si no existe: is_support=1 y se usa legacy_plate
     *
     * @param string|null $rawPlate Placa ingresada por el usuario
     * @return array{
     *     vehicle_id:int|null,
     *     is_support:int,
     *     legacy_plate:string|null,
     *     norm_plate:string
     * }
     */
    private function resolveVehicleByPlate(?string $rawPlate): array
    {
        $plate = strtoupper(trim((string)$rawPlate));              // normaliza
        $plate = preg_replace('/\s+/','',$plate);                  // quita espacios internos

        $vehicle = Vehicle::whereRaw('UPPER(TRIM(plate)) = ?', [$plate])->first();

        if ($vehicle) {
            $today = now(config('app.timezone', 'America/Lima'))->startOfDay();
            $ceased = $vehicle->termination_date && $vehicle->termination_date < $today;

            if ($vehicle->status === 'active' && !$ceased) {
                return [
                    'vehicle_id'   => $vehicle->id,
                    'is_support'   => 0,
                    'legacy_plate' => null,
                    'norm_plate'   => $plate,
                ];
            }

            // Vehículo cesado o inactivo → apoyo
            return [
                'vehicle_id'   => null,
                'is_support'   => 1,
                'legacy_plate' => $plate,
                'norm_plate'   => $plate,
            ];
        }

        return [
            'vehicle_id'   => null,
            'is_support'   => 1,
            'legacy_plate' => $plate,
            'norm_plate'   => $plate,
        ];
    }

    /**
     * Suma dos objetos de totales (principal + apoyo).
     *
     * @param object $a
     * @param object $b
     * @return object{records:int,times_total:int,price_total:float,passengers_total:int,passage_total:float,total_pasaje_total:float}
     */
    private function sumTotals(object $a, object $b): object
    {
        return (object)[
            'records'            => (int)   (($a->records            ?? 0) + ($b->records            ?? 0)),
            'times_total'        => (int)   (($a->times_total        ?? 0) + ($b->times_total        ?? 0)),
            'price_total'        => (float) (($a->price_total        ?? 0) + ($b->price_total        ?? 0)),
            'passengers_total'   => (int)   (($a->passengers_total   ?? 0) + ($b->passengers_total   ?? 0)),
            'passage_total'      => (float) (($a->passage_total      ?? 0) + ($b->passage_total      ?? 0)),
            'total_pasaje_total' => (float) (($a->total_pasaje_total ?? 0) + ($b->total_pasaje_total ?? 0)),
        ];
    }
}
