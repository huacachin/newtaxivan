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

    // DRACO (Sucursales)
    public array $groups = [];           // user -> [hq_rows]
    public array $totalsByMonth = [];    // solo DRACO
    public float $grandTotalDraco = 0.0;

    // COMBINADO (DRACO + BASE)
    public array $totalsCombinedByMonth = [];
    public float $grandTotalCombined    = 0.0;

    // Resumen HQ
    public array $byHeadquarter = [];

    protected array $userMap = []; // id => name
    protected array $hqMap   = []; // id => name

    public function mount(): void
    {
        $this->year   = (int) now()->year;
        $this->months = $this->monthNames();
        $this->buildMaps();
        $this->recalc();
    }

    public function updatedYear(): void { $this->recalc(); }
    public function prevYear(): void    { $this->year--; $this->recalc(); }
    public function nextYear(): void    { $this->year++; $this->recalc(); }

    public function render() { return view('livewire.cash.rep-est-draco-base'); }

    /* ================== Cálculo ================== */
    protected function recalc(): void
    {
        $this->baseMonthly           = array_fill(1, 12, 0.0);
        $this->totalsByMonth         = array_fill(1, 12, 0.0);
        $this->totalsCombinedByMonth = array_fill(1, 12, 0.0);

        $this->grandTotalBase   = 0.0;
        $this->grandTotalDraco  = 0.0;
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

        // --- DRACO por usuario x HQ x mes ---
        $draco = DB::table('expenses as e')
            ->whereYear('e.date', $this->year)
            ->where('e.reason', 'like', '%DRACO%')
            ->selectRaw('e.user_id, e.headquarter_id, MONTH(e.date) m, SUM(e.total) s')
            ->groupBy('e.user_id', 'e.headquarter_id', 'm')
            ->get();

        $initMonths = fn() => array_fill(1, 12, 0.0);

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

        // Orden: usuarios y HQs por nombre
        uasort($this->groups, fn($a,$b)=>strcmp($a['user'],$b['user']));
        foreach ($this->groups as &$g) {
            uasort($g['hq_rows'], fn($a,$b)=>strcmp($a['hq'],$b['hq']));
        }
        unset($g);

        // --- Resumen por HQ (DRACO) ---
        $byHQ = DB::table('expenses as e')
            ->whereYear('e.date', $this->year)
            ->where('e.reason', 'like', '%DRACO%')
            ->selectRaw('e.headquarter_id, SUM(e.total) s')
            ->groupBy('e.headquarter_id')
            ->get();

        foreach ($byHQ as $h) {
            $hid  = (int)($h->headquarter_id ?? 0);
            $name = $this->hqMap[$hid] ?? ($hid ? ('HQ#'.$hid) : '-');
            $this->byHeadquarter[] = ['hq' => $name, 'total' => (float)$h->s];
        }

        // --- COMBINADO (DRACO + BASE) ---
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

    protected function monthNames(): array
    {
        return [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];
    }
}
