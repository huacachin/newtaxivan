<?php

namespace App\Livewire\Departures;

use App\Models\Departure;
use App\Models\Headquarter;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    // Filtros
    public int $searchType = 1;      // 1=Placa, 2=Usuario, 3=Sucursal
    public ?string $searchText = null;

    public ?string $fromDate = null; // YYYY-MM-DD
    public ?string $toDate   = null; // YYYY-MM-DD
    public $headquarters;

    public bool $groupMode = false;  // Agrupado ON/OFF

    protected $queryString = [
        'searchType' => ['except' => 1],
        'searchText' => ['except' => null],
        'fromDate'   => ['except' => null],
        'toDate'     => ['except' => null],
        'groupMode'  => ['except' => false],
    ];

    public ?string $plate = null;
    public ?string $date = null;
    public ?int    $headquarter_id = null;
    public ?float  $price = 0;
    public ?int    $passenger = 0;
    public ?float  $passage = 0;

    /** Autollenados */
    public ?string $hour = null;           // HH:MM
    public ?string $latitude = null;
    public ?string $longitude = null;

    /** Para editar */
    public ?int $depId = null;

    /** Listas para selects */
    public $listHeadquarters = [];

    protected function rules()
    {
        return [
            'plate'          => ['required','string','max:20'],
            'date'           => ['required','date'],
            'headquarter_id' => ['required','integer','exists:headquarters,id'],
            'price'          => ['required','numeric','gt:0'],
            'passenger'      => ['required','integer','gt:0'],
            'passage'        => ['required','numeric','gt:0'],
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
            'price.gt'                  => 'El precio debe ser mayor a 0.',

            'passenger.required'        => 'El número de pasajeros es obligatorio.',
            'passenger.integer'         => 'Los pasajeros deben ser un número entero.',
            'passenger.gt'              => 'Los pasajeros deben ser mayores a 0.',

            'passage.required'          => 'El pasaje es obligatorio.',
            'passage.numeric'           => 'El pasaje debe ser numérico.',
            'passage.gt'                => 'El pasaje debe ser mayor a 0.',
        ];
    }



    public function mount(): void
    {
        // Default: hoy (America/Lima)
        $today = now(config('app.timezone', 'America/Lima'))->toDateString();
        $this->fromDate ??= $today;
        $this->toDate   ??= $today;
        $this->headquarters = Headquarter::where('status', 'active')->get();

        $this->listHeadquarters = Headquarter::where('status','active')
            ->orderBy('name')->get(['id','name']);

        // defaults para el form
        $now = now(config('app.timezone','America/Lima'));
        $this->date = $this->date ?: $now->toDateString();
        $this->hour = $this->hour ?: $now->format('H:i');
    }

    /** Abrir modal "Nuevo" (misma línea que Vehicles) */
    public function openAddModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('open-modal', ['name' => 'modalAddDeparture', 'focus' => 'dep_plate']);
        // el JS del modal obtendrá geolocalización y actualizará latitude/longitude
    }

    /** Guardar NUEVO con autocompletados */
    public function save(): void
    {
        $this->validate();

        // Normaliza y resuelve vehículo
        ['vehicle_id' => $vehicleId, 'is_support' => $isSupport, 'legacy_plate' => $legacyPlate, 'norm_plate' => $normPlate]
            = $this->resolveVehicleByPlate($this->plate);

        $userId = \Illuminate\Support\Facades\Auth::id();
        $now    = now(config('app.timezone','America/Lima'));
        $hour   = $this->hour ?: $now->format('H:i');

        \Illuminate\Support\Facades\DB::table('departures')->insert([
            'is_support'     => $isSupport,          // <-- se calcula aquí
            'date'           => $this->date,
            'hour'           => $hour,
            'vehicle_id'     => $vehicleId,
            'legacy_plate'   => $legacyPlate,
            'headquarter_id' => $this->headquarter_id,
            'user_id'        => $userId,             // <-- usuario autenticado
            'times'          => 1,                   // <-- siempre 1
            'price'          => $this->price,
            'passenger'      => $this->passenger,
            'passage'        => $this->passage,
            'latitude'       => $this->latitude,     // <-- se setean desde geoloc del navegador
            'longitude'      => $this->longitude,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->resetForm();
        $this->mount();
        $this->dispatch('modal-close', ['name' => 'modalAddDeparture']);
        $this->dispatch('successAlert', ['message' => 'Salida creada correctamente']);
    }


    /** Abrir modal "Editar" */
    public function openEditModal(int $id): void
    {
        $this->resetValidation();

        $row = DB::table('departures')->where('id',$id)->first();
        if (!$row) return;

        $this->depId         = $id;
        $this->date          = $row->date;
        $this->hour          = $row->hour;
        $this->headquarter_id= $row->headquarter_id;
        $this->price         = (float)$row->price;
        $this->passenger     = (int)$row->passenger;
        $this->passage       = (float)$row->passage;
        $this->latitude      = $row->latitude;
        $this->longitude     = $row->longitude;

        // plate visible para el usuario (si existe vehicle_id usa la placa real)
        if ($row->vehicle_id) {
            $this->plate = (string) DB::table('vehicles')->where('id',$row->vehicle_id)->value('plate');
        } else {
            $this->plate = (string) $row->legacy_plate;
        }

        $this->dispatch('open-modal', ['name' => 'modalEditDeparture', 'focus' => 'dep_plate']);
    }

    /** Actualizar con autocompletados */
    public function update(): void
    {
        $this->validate();
        if (!$this->depId) return;

        ['vehicle_id' => $vehicleId, 'is_support' => $isSupport, 'legacy_plate' => $legacyPlate, 'norm_plate' => $normPlate]
            = $this->resolveVehicleByPlate($this->plate);

        $now  = now(config('app.timezone','America/Lima'));
        $hour = $this->hour ?: $now->format('H:i');

        \Illuminate\Support\Facades\DB::table('departures')->where('id', $this->depId)->update([
            'is_support'     => $isSupport,      // <-- recalculado según placa actual
            'date'           => $this->date,
            'hour'           => $hour,
            'vehicle_id'     => $vehicleId,
            'legacy_plate'   => $legacyPlate,
            'headquarter_id' => $this->headquarter_id,
            // 'user_id'     => Auth::id(), // si prefieres NO cambiar el user histórico, déjalo comentado
            'times'          => 1,
            'price'          => $this->price,
            'passenger'      => $this->passenger,
            'passage'        => $this->passage,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'updated_at'     => now(),
        ]);

        $this->resetForm();
        $this->mount();
        $this->dispatch('modal-close', ['name' => 'modalEditDeparture']);
        $this->dispatch('successAlert', ['message' => 'Salida actualizada correctamente']);
    }


    private function resetForm(): void
    {
        $now = now(config('app.timezone','America/Lima'));
        $this->depId = null;
        $this->plate = null;
        $this->date  = $now->toDateString();
        $this->hour  = $now->format('H:i');
        $this->headquarter_id = null;
        $this->price = 0;
        $this->passenger = 0;
        $this->passage = 0;
        $this->latitude = null;
        $this->longitude = null;
    }

    // Reacciona SIEMPRE que cambie cualquiera de las fechas
    public function updatedFromDate(): void
    {
        $this->normalizeRange();
    }

    public function updatedToDate(): void
    {
        $this->normalizeRange();
    }

    // Corrige si el usuario invierte el rango
    private function normalizeRange(): void
    {
        if ($this->fromDate && $this->toDate && $this->fromDate > $this->toDate) {
            [$this->fromDate, $this->toDate] = [$this->toDate, $this->fromDate];
        }
    }

    /** Base query con joins y filtros comunes */
    private function baseQuery()
    {
        $q = DB::table('departures as d')
            ->leftJoin('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->whereNotNull('d.date')
            ->where('v.status', '=', 'active'); // sólo vehículos activos

        // Fecha: rango (por defecto ya viene hoy–hoy)
        if ($this->fromDate && $this->toDate) {
            $q->whereBetween('d.date', [$this->fromDate, $this->toDate]);
        } elseif ($this->fromDate) {
            $q->whereDate('d.date', '>=', $this->fromDate);
        } elseif ($this->toDate) {
            $q->whereDate('d.date', '<=', $this->toDate);
        }

        // Buscador por tipo (placa/usuario/sede)
        $term = trim((string)($this->searchText ?? ''));
        if ($term !== '') {
            switch ($this->searchType) {
                case 1: // Placa
                    $q->where('v.plate', 'like', '%'.strtoupper($term).'%');
                    break;
                case 2: // Usuario
                    $q->where('u.name', 'like', '%'.$term.'%');
                    break;
                case 3: // Sucursal
                    $q->where('h.id', $term);
                    break;
            }
        }

        return $q;
    }

    /** Totales del dataset filtrado completo */
    private function totals(): object
    {
        $base = $this->baseQuery();

        $row = $base->cloneWithout(['orders', 'columns'])
            ->selectRaw('
            COUNT(*)                                        as records,
            COALESCE(SUM(d.times), 0)                       as times_total,
            COALESCE(SUM(d.price), 0)                       as price_total,
            COALESCE(SUM(d.passenger), 0)                   as passengers_total,
            COALESCE(SUM(d.passage), 0)                     as passage_total,
            COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)), 0) as total_pasaje_total
        ')
            ->first();

        // Si por cualquier motivo viniera null, devolvemos un objeto “cero”
        return $row ?: (object) [
            'records'             => 0,
            'times_total'         => 0,
            'price_total'         => 0,
            'passengers_total'    => 0,
            'passage_total'       => 0,
            'total_pasaje_total'  => 0,
        ];
    }

    public function toggleGroup(): void
    {
        $this->groupMode = !$this->groupMode;
    }


    public function render()
    {
        // Siempre inicializa para evitar “undefined variable”
        $rows          = collect();
        $supportRows   = collect();
        $totals        = (object)['records'=>0,'times_total'=>0,'price_total'=>0,'passengers_total'=>0,'passage_total'=>0,'total_pasaje_total'=>0];
        $supportTotals = (object)['records'=>0,'times_total'=>0,'price_total'=>0,'passengers_total'=>0,'passage_total'=>0,'total_pasaje_total'=>0];

        // ====== PRINCIPAL (vehículos existentes activos) ======
        if ($this->groupMode) {
            // Agrupado por placa
            $aggE = $this->existingBase()
                ->selectRaw("
                v.plate as plate,
                ANY_VALUE(h.name) as headquarter_name,
                ANY_VALUE(u.name) as user_name,
                COALESCE(SUM(d.times), 0)  as k1,
                COALESCE(SUM(d.price), 0)  as p1,
                COALESCE(SUM(d.passenger), 0) as pasajeros,
                COALESCE(SUM(d.passage), 0)   as pasaje,
                COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)), 0) as total_pasaje,
                MIN(d.date) as from_date,
                MAX(d.date) as to_date,
                MAX(d.date) as date
            ")
                ->groupBy('v.plate');

            $rows = DB::query()
                ->fromSub($aggE, 'a')
                ->selectRaw("a.*, ROW_NUMBER() OVER (ORDER BY a.plate) AS ordinal")
                ->orderBy('a.plate')
                ->get();
        } else {
            // Detalle con frecuencia
            $innerE = $this->existingBase()
                ->selectRaw("
                d.id, d.date, d.hour, d.times, d.price, d.passenger, d.passage,
                d.latitude, d.longitude,
                v.plate as plate,
                h.name as headquarter_name, u.name as user_name,
                COALESCE(d.passenger,0)*COALESCE(d.passage,0) as total_pasaje,
                CONCAT(d.date,' ',d.hour) as curr_dt,
                LAG(CONCAT(d.date,' ',d.hour)) OVER (PARTITION BY v.plate ORDER BY d.date, d.hour) as prev_dt
            ");

            $rows = DB::query()
                ->fromSub($innerE, 'x')
                ->selectRaw("x.*, SEC_TO_TIME(TIMESTAMPDIFF(SECOND, x.prev_dt, x.curr_dt)) as freq")
                ->orderBy('x.date')->orderBy('x.hour')
                ->get();
        }
        $totals = $this->totalsFor($this->existingBase());

        // ====== APOYO (is_support = 1) ======
        if ($this->groupMode) {
            // Agrupado por placa legacy
            $aggS = $this->supportBase()
                ->selectRaw("
                d.legacy_plate as plate,
                ANY_VALUE(h.name) as headquarter_name,
                ANY_VALUE(u.name) as user_name,
                COALESCE(SUM(d.times), 0)  as k1,
                COALESCE(SUM(d.price), 0)  as p1,
                COALESCE(SUM(d.passenger), 0) as pasajeros,
                COALESCE(SUM(d.passage), 0)   as pasaje,
                COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)), 0) as total_pasaje,
                MIN(d.date) as from_date,
                MAX(d.date) as to_date,
                MAX(d.date) as date
            ")
                ->groupBy('d.legacy_plate');

            $supportRows = DB::query()
                ->fromSub($aggS, 'a')
                ->selectRaw("a.*, ROW_NUMBER() OVER (ORDER BY a.plate) AS ordinal")
                ->orderBy('a.plate')
                ->get();
        } else {
            // Detalle con frecuencia
            $innerS = $this->supportBase()
                ->selectRaw("
                d.id, d.date, d.hour, d.times, d.price, d.passenger, d.passage,
                d.latitude, d.longitude,
                d.legacy_plate as plate,
                h.name as headquarter_name, u.name as user_name,
                COALESCE(d.passenger,0)*COALESCE(d.passage,0) as total_pasaje,
                CONCAT(d.date,' ',d.hour) as curr_dt,
                LAG(CONCAT(d.date,' ',d.hour)) OVER (PARTITION BY d.legacy_plate ORDER BY d.date, d.hour) as prev_dt
            ");

            $supportRows = DB::query()
                ->fromSub($innerS, 'x')
                ->selectRaw("x.*, SEC_TO_TIME(TIMESTAMPDIFF(SECOND, x.prev_dt, x.curr_dt)) as freq")
                ->orderBy('x.date')->orderBy('x.hour')
                ->get();
        }
        $supportTotals = $this->totalsFor($this->supportBase());
        $grandTotals = $this->sumTotals($totals, $supportTotals);

        // Pasa SIEMPRE todas las variables al Blade
        return view('livewire.departures.index', [
            'rows'           => $rows,
            'totals'         => $totals,
            'supportRows'    => $supportRows,
            'supportTotals'  => $supportTotals,
            'groupMode'      => $this->groupMode,
            'grandTotals'    => $grandTotals   // <— NUEVO
        ]);
    }

    private function supportBase()
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
                case 2: $q->where('u.name', 'like', '%'.$term.'%'); break;
                case 3:
                    if (is_numeric($term)) $q->where('h.id', (int)$term);
                    else $q->where('h.name', 'like', '%'.$term.'%');
                    break;
            }
        }
        return $q;
    }

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

    private function existingBase()
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
                case 2: $q->where('u.name', 'like', '%'.$term.'%'); break;
                case 3:
                    if (is_numeric($term)) $q->where('h.id', (int)$term);
                    else $q->where('h.name', 'like', '%'.$term.'%');
                    break;
            }
        }
        return $q;
    }

    public function reportMonthly(){
        $route = route('departures.monthly');

        $this->dispatch('url-open',["url" => $route]);
    }

    public function reportRmp(){
        $route = route('departures.rmp' );

        $this->dispatch('url-open',["url" => $route]);
    }

    public function export(){
        $route = route('exports.departures',
            [   "searchType" => $this->searchType,
                "searchText" => $this->searchText,
                "fromDate" => $this->fromDate,
                "toDate" => $this->toDate,
                "groupMode" => $this->groupMode
            ]);

        $this->dispatch('url-open',["url" => $route]);
    }

    private function resolveVehicleByPlate(?string $rawPlate): array
    {
        $plate = strtoupper(trim((string)$rawPlate));              // normaliza
        // opcional: normaliza espacios múltiples o guiones
        $plate = preg_replace('/\s+/','',$plate);                  // quita espacios internos

        $vehicle = \App\Models\Vehicle::whereRaw('UPPER(TRIM(plate)) = ?', [$plate])->first();

        if ($vehicle) {
            return [
                'vehicle_id'   => $vehicle->id,
                'is_support'   => 0,
                'legacy_plate' => null,
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
