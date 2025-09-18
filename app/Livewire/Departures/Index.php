<?php

namespace App\Livewire\Departures;

use App\Models\Departure;
use App\Models\Headquarter;
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

    public function mount(): void
    {
        // Default: hoy (America/Lima)
        $today = now(config('app.timezone', 'America/Lima'))->toDateString();
        $this->fromDate ??= $today;
        $this->toDate   ??= $today;
        $this->headquarters = Headquarter::where('status', 'active')->get();
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

        // Pasa SIEMPRE todas las variables al Blade
        return view('livewire.departures.index', [
            'rows'           => $rows,
            'totals'         => $totals,
            'supportRows'    => $supportRows,
            'supportTotals'  => $supportTotals,
            'groupMode'      => $this->groupMode,
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

}
