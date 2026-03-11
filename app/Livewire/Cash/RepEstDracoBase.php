<?php

namespace App\Livewire\Cash;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepEstDracoBase extends Component
{
    public int   $year = 0;
    public array $months = [];

    // BASE (Oficina)
    public array $baseMonthly = [];
    public float $grandTotalBase  = 0.0;

    // DRACO (Solo controllers activos)
    public array $groups = [];           // user -> [hq_rows]
    public array $totalsByMonth = [];    // solo DRACO (controllers)
    public float $grandTotalDraco = 0.0;

    // COMBINADO (DRACO controllers + BASE)
    public array $totalsCombinedByMonth = [];
    public float $grandTotalCombined    = 0.0;

    // Resumen HQ (controllers) + Sucursal Vacía (admins)
    public array $byHeadquarter = [];

    protected array $userMap = []; // id => name
    protected array $hqMap   = []; // id => name

    /** Roles & filtros */
    protected array $controllerIds = [];
    protected array $adminIds = [];
    protected string $userModelClass = \App\Models\User::class;

    public function mount(): void
    {
        $this->year   = (int) now()->year;
        $this->months = $this->monthNames();
        $this->buildMaps();
        $this->recalc();
        $this->loadActiveUsersByRole($this->year);

    }

    public function updatedYear(): void {
        $this->loadActiveUsersByRole($this->year);
        $this->recalc(); }
    public function prevYear(): void    { $this->year--;
        $this->loadActiveUsersByRole($this->year);
        $this->recalc(); }
    public function nextYear(): void    { $this->year++;
        $this->loadActiveUsersByRole($this->year);
        $this->recalc(); }

    public function render() { return view('livewire.cash.rep-est-draco-base'); }

    /* ================== Cálculo ================== */
    protected function recalc(): void
    {

        // Asegura mapas frescos en cada cálculo
        $this->buildMaps();

        if (empty($this->controllerIds) && empty($this->adminIds)) {
            $this->loadActiveUsersByRole($this->year);
        }

        $this->baseMonthly           = array_fill(1, 12, 0.0);
        $this->totalsByMonth         = array_fill(1, 12, 0.0);
        $this->totalsCombinedByMonth = array_fill(1, 12, 0.0);

        $this->grandTotalBase     = 0.0;
        $this->grandTotalDraco    = 0.0;
        $this->grandTotalCombined = 0.0;

        $this->groups        = [];
        $this->byHeadquarter = [];

        if (!Schema::hasTable('expenses')) return;

        // --- BASE (Oficina) ---
        $base = DB::table('expenses')
            ->whereYear('date', $this->year)
            ->where('reason', 'like', '%BASE%')
            ->selectRaw('MONTH(date) m, SUM(total) s')
            ->groupBy('m')
            ->pluck('s', 'm');

        foreach ($base as $m => $s) {
            $mi = (int)$m;
            if ($mi >= 1 && $mi <= 12) {
                $val = (float)$s;
                $this->baseMonthly[$mi] = $val;
                $this->grandTotalBase  += $val;
            }
        }

        $initMonths = fn() => array_fill(1, 12, 0.0);

        // Pre-seed: todos los controllers activos con TODAS sus sedes asignadas
        if (!empty($this->controllerIds)) {
            $assignedHqs = DB::table('headquarter_user')
                ->whereIn('user_id', $this->controllerIds)
                ->get()
                ->groupBy('user_id');

            foreach ($this->controllerIds as $uid) {
                $this->groups[$uid] = [
                    'user'    => $this->userMap[$uid] ?? ('User#'.$uid),
                    'hq_rows' => [],
                ];
                $userHqs = $assignedHqs->get($uid, collect());
                foreach ($userHqs as $pivot) {
                    $hid = (int) $pivot->headquarter_id;
                    $this->groups[$uid]['hq_rows'][$hid] = [
                        'hq'    => $this->hqMap[$hid] ?? ($hid ? ('HQ#'.$hid) : '-'),
                        'm'     => $initMonths(),
                        'total' => 0.0,
                    ];
                }
                // Si no tiene sedes asignadas, fila vacía
                if (empty($this->groups[$uid]['hq_rows'])) {
                    $this->groups[$uid]['hq_rows'][0] = [
                        'hq'    => '–',
                        'm'     => $initMonths(),
                        'total' => 0.0,
                    ];
                }
            }
        }

        // --- DRACO por usuario x HQ x mes (SOLO controllers activos) ---
        if (!empty($this->controllerIds)) {
            $draco = DB::table('expenses as e')
                ->whereYear('e.date', $this->year)
                ->where('e.reason', 'like', '%DRACO%')
                ->whereIn('e.user_id', $this->controllerIds)   // solo controllers activos
                ->selectRaw('e.user_id, e.headquarter_id, MONTH(e.date) m, SUM(e.total) s')
                ->groupBy('e.user_id', 'e.headquarter_id', 'm')
                ->get();

            foreach ($draco as $r) {
                $uid = (int)($r->user_id ?? 0);
                $hid = (int)($r->headquarter_id ?? 0);
                $mi  = max(1, min(12, (int)$r->m));
                $val = (float)$r->s;

                if (!isset($this->groups[$uid])) {
                    $this->groups[$uid] = ['user' => $this->userMap[$uid] ?? '-', 'hq_rows' => []];
                }
                if (!isset($this->groups[$uid]['hq_rows'][$hid])) {
                    $this->groups[$uid]['hq_rows'][$hid] = [
                        'hq'    => $this->hqMap[$hid] ?? ($hid ? ('HQ#'.$hid) : '-'),
                        'm'     => $initMonths(),
                        'total' => 0.0,
                    ];
                }

                $this->groups[$uid]['hq_rows'][$hid]['m'][$mi] += $val;
                $this->groups[$uid]['hq_rows'][$hid]['total']  += $val;

                $this->totalsByMonth[$mi] += $val;
                $this->grandTotalDraco    += $val;
            }
        }

        // Orden: usuarios y HQs por nombre
        uasort($this->groups, fn($a,$b)=>strcmp($a['user'],$b['user']));
        foreach ($this->groups as &$g) {
            uasort($g['hq_rows'], fn($a,$b)=>strcmp($a['hq'],$b['hq']));
        }
        unset($g);

        // --- Resumen por HQ (controllers activos) ---
        $controllersByHQ = [];
        if (!empty($this->controllerIds)) {
            $byHQ = DB::table('expenses as e')
                ->whereYear('e.date', $this->year)
                ->where('e.reason', 'like', '%DRACO%')
                ->whereIn('e.user_id', $this->controllerIds)
                ->selectRaw('e.headquarter_id, SUM(e.total) s')
                ->groupBy('e.headquarter_id')
                ->get();

            foreach ($byHQ as $h) {
                $hid  = (int)($h->headquarter_id ?? 0);
                $name = $this->hqMap[$hid] ?? ($hid ? ('HQ#'.$hid) : '–');
                $controllersByHQ[] = ['hq' => $name, 'total' => (float)$h->s];
            }
        }

        // --- Total DRACO de usuarios admin activos como "Sucursal Vacía" ---
        $adminVacuumTotal = 0.0;
        if (!empty($this->adminIds)) {
            $adminVacuumTotal = (float) DB::table('expenses as e')
                ->whereYear('e.date', $this->year)
                ->where('e.reason', 'like', '%DRACO%')
                ->whereIn('e.user_id', $this->adminIds)
                ->sum('e.total');
        }

        // Construye resumen final (controllers por HQ + fila "Sucursal vacía")
        $this->byHeadquarter = $controllersByHQ;
        if ($adminVacuumTotal > 0) {
            $this->byHeadquarter[] = ['hq' => '-', 'total' => $adminVacuumTotal];
        }

        // --- COMBINADO (DRACO controllers + BASE) ---
        for ($i=1; $i<=12; $i++) {
            $this->totalsCombinedByMonth[$i] =
                ($this->totalsByMonth[$i] ?? 0) + ($this->baseMonthly[$i] ?? 0);
        }
        $this->grandTotalCombined = $this->grandTotalDraco + $this->grandTotalBase;
    }

    /* ================== Helpers ================== */
    protected function buildMaps(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')->select('id','name')->orderBy('name')->chunk(1000, function($rows) {
                foreach ($rows as $r) $this->userMap[(int)$r->id] = (string)$r->name;
            });
        }
        if (Schema::hasTable('headquarters')) {
            DB::table('headquarters')->select('id','name')->orderBy('name')->chunk(1000, function($rows) {
                foreach ($rows as $r) $this->hqMap[(int)$r->id] = (string)$r->name;
            });
        }
    }

    /** Carga IDs de usuarios activos por rol usando Spatie */
    protected function loadActiveUsersByRole(int $year): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('model_has_roles') || !Schema::hasTable('users')) {
            $this->controllerIds = $this->adminIds = [];
            return;
        }

        $onlyActive = ($year === (int) now()->year);

        $getIds = function (string $roleName) use ($onlyActive) : array {
            $q = DB::table('model_has_roles as mr')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')
                ->join('users as u', 'u.id', '=', 'mr.model_id')
                ->where('mr.model_type', $this->userModelClass)
                ->where('r.name', $roleName);

            if ($onlyActive) {
                $q->where('u.status', 'active');
            }

            return $q->pluck('u.id')->map(fn($v)=>(int)$v)->unique()->values()->all();
        };

        $this->controllerIds = $getIds('controller');
        $this->adminIds      = $getIds('admin');
    }

    protected function monthNames(): array
    {
        return [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];
    }

    public function export(): void {
        $url = route('exports.cash-draco-report', ['year' => $this->year]);
        $this->dispatch('url-open', ['url' => $url]);
    }
}
