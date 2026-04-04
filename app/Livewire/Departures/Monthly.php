<?php

namespace App\Livewire\Departures;

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Monthly extends Component
{
    public int $year = 0;
    public int $month = 0;
    public int $daysInMonth = 0;

    public array $days = [];
    public array $rows = [];
    public array $totalPerDay = [];
    public array $vehiclesWorkedPerDay = [];

    // Por sede
    public array $hqTables = [];        // [hq_id => ['name'=>..., 'rows'=>[], 'totalPerDay'=>[], 'vtPerDay'=>[]]]
    public array $hqSummary = [];       // [hq_id => ['name'=>..., 'totalVueltas'=>int, 'totalVT'=>int]]
    public int $grandTotalVueltas = 0;
    public int $grandTotalVT = 0;

    /** Si es null, se usa COUNT(*) */
    protected ?string $countColumn = null;

    /** Cambia si tu campo de fecha en departures tiene otro nombre (p. ej. 'fecha') */
    protected string $dateColumn  = 'date';

    public function mount(): void
    {
        $now = Carbon::now();
        $this->year  = (int) $now->year;
        $this->month = (int) $now->month;

        $this->setupDays();
        $this->detectCountColumn();
        $this->recalc();
    }

    /** Al cambiar año o mes, recalcula automáticamente */
    public function updated($prop): void
    {
        if (in_array($prop, ['year','month'], true)) {
            $this->setupDays();
            $this->recalc();
        }
    }

    public function render()
    {
        return view('livewire.departures.monthly');
    }

    /* ===================== Core ===================== */

    protected function setupDays(): void
    {
        $d = Carbon::create($this->year, $this->month, 1);
        $this->daysInMonth = (int) $d->daysInMonth;
        $this->days        = range(1, $this->daysInMonth);
    }

    protected function detectCountColumn(): void
    {
        if (!Schema::hasTable('departures')) {
            $this->countColumn = null;
            return;
        }

        if (!Schema::hasColumn('departures', $this->dateColumn)) {
            $this->dateColumn = 'date'; // ajusta aquí si tu columna real es 'fecha'
        }

        // Preferencias conocidas; si ninguna existe, contaremos filas (COUNT(*))
        $candidates = ['num', 'quantity', 'laps', 'vueltas', 'count', 'total_turns'];
        foreach ($candidates as $c) {
            if (Schema::hasColumn('departures', $c)) {
                $this->countColumn = $c;
                return;
            }
        }
        $this->countColumn = null;
    }

    protected function recalc(): void
    {
        $this->rows = [];
        $this->totalPerDay = array_fill(1, $this->daysInMonth, 0);
        $this->vehiclesWorkedPerDay = array_fill(1, $this->daysInMonth, 0);

        if (!Schema::hasTable('vehicles') || !Schema::hasTable('departures')) {
            return;
        }

        $start = Carbon::create($this->year, $this->month, 1)->toDateString();
        $end   = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();

        // Vehículos
        if (Schema::hasColumn('vehicles', 'sort_order')) {
            $vehicles = DB::table('vehicles')
                ->select('id', 'plate','sort_order')
                ->where('status','active')
                ->orderByRaw('sort_order IS NULL, sort_order ASC')
                ->orderBy('plate') // desempate
                ->get();
        } else {
            // Fallbacks legacy si no existiera sort_order
            $orderCol = Schema::hasColumn('vehicles', 'order')
                ? 'order'
                : (Schema::hasColumn('vehicles', 'orden') ? 'orden' : 'plate');

            $vehicles = DB::table('vehicles')
                ->select('id', 'plate','sort_order')
                ->orderBy($orderCol)
                ->orderBy('plate') // desempate
                ->get();
        }

        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'sort_order' => (string)($v->sort_order ?? ''),
                'plate' => (string)$v->plate,
                'daily' => array_fill(1, $this->daysInMonth, 0),
                'total' => 0,
            ];
        }

        $dateCol = $this->dateColumn;

        // Agregados por día/vehículo
        if ($this->countColumn) {
            // SUM(columna)
            $selectRaw = "vehicle_id, DAY($dateCol) as d, SUM({$this->countColumn}) as s";
        } else {
            // COUNT(*)
            $selectRaw = "vehicle_id, DAY($dateCol) as d, COUNT(*) as s";
        }

        $aggregates = DB::table('departures')
            ->selectRaw($selectRaw)
            ->whereBetween($dateCol, [$start, $end])
            ->groupBy('vehicle_id', 'd')
            ->get();

        foreach ($aggregates as $r) {
            $vid = (int) $r->vehicle_id;
            $d   = (int) $r->d;
            $s   = (float) $r->s;

            if (!isset($this->rows[$vid])) continue;

            // ÷2 + redondeo (half-up). El valor mostrado/guardado es ya dividido.
            $halvedRounded = (int) round($s / 2, 0, PHP_ROUND_HALF_UP);

            $this->rows[$vid]['daily'][$d] = $halvedRounded;
        }

        // Totales por fila y por día (sobre los valores ya divididos)
        foreach ($this->rows as &$row) {
            $row['total'] = array_sum($row['daily']);
            foreach ($row['daily'] as $d => $val) {
                $this->totalPerDay[$d] += $val;
            }
        }
        unset($row);

        // "Vehículos trabajados" por día: cuenta cuántos tienen valor > 0 (ya dividido)
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $worked = 0;
            foreach ($this->rows as $row) {
                if (($row['daily'][$d] ?? 0) > 0) $worked++;
            }
            $this->vehiclesWorkedPerDay[$d] = $worked;
        }

        $this->recalcByHQ($start, $end, $vehicles);
    }

    protected function recalcByHQ(string $start, string $end, $vehicles): void
    {
        $this->hqTables = [];
        $this->hqSummary = [];
        $this->grandTotalVueltas = 0;
        $this->grandTotalVT = 0;

        $dateCol = $this->dateColumn;

        // Apoyo: departures con is_support=1, agrupados por sede/placa/día
        $supportAggs = DB::table('departures as d')
            ->join('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->selectRaw("d.headquarter_id, h.name as hq_name, d.legacy_plate as plate, DAY(d.{$dateCol}) as day_num, SUM(d.times) as raw_sum")
            ->where('d.is_support', 1)
            ->whereBetween("d.{$dateCol}", [$start, $end])
            ->groupBy('d.headquarter_id', 'h.name', 'd.legacy_plate', 'day_num')
            ->get();

        // Indexar: [hq_id => ['name'=>..., 'plates'=>[plate => [d => raw]]]]
        $byHQ = [];
        foreach ($supportAggs as $r) {
            $hqId = (int)$r->headquarter_id;
            $byHQ[$hqId]['name'] = $r->hq_name;
            $byHQ[$hqId]['plates'][$r->plate][(int)$r->day_num] =
                ($byHQ[$hqId]['plates'][$r->plate][(int)$r->day_num] ?? 0) + (int)$r->raw_sum;
        }

        foreach ($byHQ as $hqId => $hqData) {
            $hqRows = [];
            $totalPerDay = array_fill(1, $this->daysInMonth, 0);
            $vtPerDay    = array_fill(1, $this->daysInMonth, 0);
            $item = 0;

            foreach ($hqData['plates'] as $plate => $days) {
                $item++;
                $daily = array_fill(1, $this->daysInMonth, 0);

                foreach ($days as $d => $raw) {
                    if ($d < 1 || $d > $this->daysInMonth) continue;
                    $daily[$d] = (int)$raw;
                }

                $total = array_sum($daily);
                if ($total === 0) continue;

                $hqRows[] = [
                    'sort_order' => '',
                    'plate'      => (string)$plate,
                    'daily'      => $daily,
                    'total'      => $total,
                ];

                foreach ($daily as $d => $val) {
                    $totalPerDay[$d] += $val;
                    if ($val > 0) $vtPerDay[$d]++;
                }
            }

            if (empty($hqRows)) continue;

            // Ordenar por total de salidas descendente
            usort($hqRows, fn($a, $b) => $b['total'] <=> $a['total']);

            $this->hqTables[$hqId] = [
                'name'        => $hqData['name'],
                'rows'        => $hqRows,
                'totalPerDay' => $totalPerDay,
                'vtPerDay'    => $vtPerDay,
            ];

            $totalVueltas = array_sum($totalPerDay);
            $totalVT      = array_sum($vtPerDay);

            $this->hqSummary[$hqId] = [
                'name'          => $hqData['name'],
                'totalVueltas'  => $totalVueltas,
                'totalVT'       => $totalVT,
            ];

            $this->grandTotalVueltas += $totalVueltas;
        }

        $this->grandTotalVT = array_sum($this->vehiclesWorkedPerDay);
    }

    public function export(){
        $route = route('exports.departures-monthly-export',
            [   "year" => $this->year,
                "month" => $this->month,
            ]);

        $this->dispatch('url-open',["url" => $route]);
    }
}
