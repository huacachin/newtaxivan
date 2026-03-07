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

        // Raw por sede/vehículo/día (SIN dividir por 2)
        if ($this->countColumn) {
            $selectRaw = "headquarter_id, vehicle_id, DAY({$dateCol}) as d, SUM({$this->countColumn}) as s";
        } else {
            $selectRaw = "headquarter_id, vehicle_id, DAY({$dateCol}) as d, COUNT(*) as s";
        }

        $aggregates = DB::table('departures')
            ->selectRaw($selectRaw)
            ->whereBetween($dateCol, [$start, $end])
            ->groupBy('headquarter_id', 'vehicle_id', 'd')
            ->get();

        // Indexar raw: [hq_id][vid][d] = raw_sum
        $byHQ = [];
        foreach ($aggregates as $r) {
            $hqId = (int)$r->headquarter_id;
            $vid  = (int)$r->vehicle_id;
            $d    = (int)$r->d;
            $s    = (float)$r->s;
            $byHQ[$hqId][$vid][$d] = ($byHQ[$hqId][$vid][$d] ?? 0) + $s;
        }

        // Raw global por vehículo/día (para calcular proporciones)
        $globalRaw = []; // [vid][d] = raw_sum
        foreach ($byHQ as $hqId => $vids) {
            foreach ($vids as $vid => $days) {
                foreach ($days as $d => $s) {
                    $globalRaw[$vid][$d] = ($globalRaw[$vid][$d] ?? 0) + $s;
                }
            }
        }

        // Mapa de vehículos
        $vehicleMap = [];
        foreach ($vehicles as $v) {
            $vehicleMap[(int)$v->id] = [
                'sort_order' => (string)($v->sort_order ?? ''),
                'plate'      => (string)$v->plate,
            ];
        }

        // Nombres de sedes (incluir todas las que aparecen en los datos)
        $hqIds = array_keys($byHQ);
        $hqNames = [];
        if (!empty($hqIds)) {
            $hqNames = DB::table('headquarters')
                ->whereIn('id', $hqIds)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        // Para cada sede, distribuir los valores ya divididos de la tabla principal
        foreach ($hqNames as $hqId => $hqName) {
            if (!isset($byHQ[$hqId])) continue;

            $hqRows = [];
            $totalPerDay = array_fill(1, $this->daysInMonth, 0);
            $vtPerDay    = array_fill(1, $this->daysInMonth, 0);

            foreach ($vehicleMap as $vid => $vInfo) {
                if (!isset($byHQ[$hqId][$vid])) continue;
                if (!isset($this->rows[$vid])) continue;

                $mainDaily = $this->rows[$vid]['daily']; // valores ya con ÷2
                $daily = array_fill(1, $this->daysInMonth, 0);

                foreach ($byHQ[$hqId][$vid] as $d => $rawHQ) {
                    if ($d < 1 || $d > $this->daysInMonth) continue;
                    $rawGlobal = $globalRaw[$vid][$d] ?? 0;
                    $mainVal   = $mainDaily[$d] ?? 0;

                    if ($rawGlobal > 0 && $mainVal > 0) {
                        // Proporción de esta sede sobre el total
                        $daily[$d] = (int) round($mainVal * ($rawHQ / $rawGlobal), 0, PHP_ROUND_HALF_UP);
                    }
                }

                // Ajuste: asegurar que la suma por sede = valor de la tabla principal
                // Corregir residuos de redondeo en el HQ con mayor contribución
                foreach ($mainDaily as $d => $mainVal) {
                    if ($mainVal <= 0) continue;
                    $rawGlobal = $globalRaw[$vid][$d] ?? 0;
                    if ($rawGlobal <= 0) continue;

                    // Sumar lo asignado a TODAS las sedes para este vid/d
                    $assignedTotal = 0;
                    foreach ($byHQ as $otherHqId => $otherVids) {
                        if (!isset($otherVids[$vid][$d])) continue;
                        $otherRaw = $otherVids[$vid][$d];
                        $assignedTotal += (int) round($mainVal * ($otherRaw / $rawGlobal), 0, PHP_ROUND_HALF_UP);
                    }

                    // Si hay diferencia, ajustar en esta sede si es la de mayor contribución
                    $diff = $mainVal - $assignedTotal;
                    if ($diff !== 0 && isset($byHQ[$hqId][$vid][$d])) {
                        $maxHq = $hqId;
                        $maxRaw = 0;
                        foreach ($byHQ as $otherHqId => $otherVids) {
                            if (isset($otherVids[$vid][$d]) && $otherVids[$vid][$d] > $maxRaw) {
                                $maxRaw = $otherVids[$vid][$d];
                                $maxHq  = $otherHqId;
                            }
                        }
                        if ($maxHq === $hqId) {
                            $daily[$d] += $diff;
                        }
                    }
                }

                $total = array_sum($daily);
                if ($total === 0) continue;

                $hqRows[] = [
                    'sort_order' => $vInfo['sort_order'],
                    'plate'      => $vInfo['plate'],
                    'daily'      => $daily,
                    'total'      => $total,
                ];

                foreach ($daily as $d => $val) {
                    $totalPerDay[$d] += $val;
                    if ($val > 0) $vtPerDay[$d]++;
                }
            }

            if (empty($hqRows)) continue;

            $this->hqTables[$hqId] = [
                'name'        => $hqName,
                'rows'        => $hqRows,
                'totalPerDay' => $totalPerDay,
                'vtPerDay'    => $vtPerDay,
            ];

            $totalVueltas = array_sum($totalPerDay);
            $totalVT      = array_sum($vtPerDay);

            $this->hqSummary[$hqId] = [
                'name'          => $hqName,
                'totalVueltas'  => $totalVueltas,
                'totalVT'       => $totalVT,
            ];

            $this->grandTotalVueltas += $totalVueltas;
        }

        // V.T general = el de la tabla principal (un vehículo cuenta 1 solo aunque opere en varias sedes)
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
