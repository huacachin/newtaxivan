<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\DrawEntry;
use App\Models\Holiday;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Random\Randomizer;

/**
 * Sorteos: reparte cada día hábil los números 1..N entre los vehículos que
 * participan, rotando su franja (bajo -> medio -> alto -> bajo).
 *
 * Las franjas son tercios de N; si N no es divisible entre 3 el sobrante va
 * a bajo y luego a medio (44 = 15/15/14). Regla dura: nadie repite la franja
 * de su sorteo anterior. Objetivo: que cada uno reciba la franja que le toca
 * en el ciclo. Cuando no cuadra (vehículos quitados, N no divisible) se
 * desvía a la menor cantidad posible hacia la otra franja permitida, eligiendo
 * entre quienes menos desvíos acumulan. Solo si ni así alcanza (casi todos
 * venían de la misma franja) alguien repite, y queda registrado igual.
 *
 * El reparto es un problema de transporte mínimo: grupos según la franja
 * anterior -> franjas de hoy, con costo 0 la que toca, 1 la otra permitida y
 * REPEAT_COST repetir. Lo resuelve un flujo de costo mínimo de 9 nodos.
 */
class DrawService
{
    /** Costo de ir a la franja permitida que no tocaba en el ciclo. */
    private const DEVIATE_COST = 1;

    /** Costo de repetir la franja anterior: solo cuando no hay otra salida. */
    private const REPEAT_COST = 1000;

    /** Grupo de los vehículos sin sorteos previos: entran en cualquier franja. */
    private const NO_HISTORY = 'none';

    /** Días que se miran hacia adelante para hallar el siguiente día hábil. */
    private const HOLIDAY_LOOKAHEAD_DAYS = 20;

    private const WEEKDAY_LABELS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'];

    public function __construct(private Randomizer $randomizer = new Randomizer) {}

    /* ---------- Calendario ---------- */

    /**
     * Lunes a sábado que no sea feriado.
     *
     * @param  array<int, string>  $holidays  fechas Y-m-d
     */
    public static function isWorkingDay(Carbon $date, array $holidays): bool
    {
        return ! $date->isSunday() && ! in_array($date->toDateString(), $holidays, true);
    }

    /**
     * Primer día hábil estrictamente posterior a $date.
     *
     * @param  array<int, string>  $holidays
     */
    public static function nextWorkingDay(Carbon $date, array $holidays): Carbon
    {
        $day = $date->copy()->startOfDay()->addDay();
        while (! self::isWorkingDay($day, $holidays)) {
            $day->addDay();
        }

        return $day;
    }

    /**
     * Días que se pueden sortear: hoy (si es hábil) y el siguiente día hábil,
     * para sortear el mismo día o la noche anterior, y siempre posteriores al
     * último sorteo vigente. Un día pasado sin sorteo se salta.
     *
     * @param  array<int, string>  $holidays
     * @return array<int, string> fechas Y-m-d
     */
    public static function drawableDates(?string $lastDrawDate, Carbon $today, array $holidays): array
    {
        $today = $today->copy()->startOfDay();
        $dates = [];
        if (self::isWorkingDay($today, $holidays)) {
            $dates[] = $today->toDateString();
        }
        $dates[] = self::nextWorkingDay($today, $holidays)->toDateString();

        return array_values(array_filter($dates, fn (string $d) => $lastDrawDate === null || $d > $lastDrawDate));
    }

    /**
     * Días hábiles de un mes, en orden, para las columnas de la grilla.
     *
     * @return array<int, array{date: string, n: int, weekday: string}>
     */
    public static function workingDaysOfMonth(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $holidays = Holiday::datesBetween($start->toDateString(), $end->toDateString());

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (self::isWorkingDay($d, $holidays)) {
                $days[] = ['date' => $d->toDateString(), 'n' => $d->day, 'weekday' => self::weekdayLabel($d)];
            }
        }

        return $days;
    }

    public static function weekdayLabel(Carbon $date): string
    {
        return self::WEEKDAY_LABELS[$date->dayOfWeek];
    }

    /* ---------- Franjas ---------- */

    /**
     * Cuántos números tiene cada franja para N participantes.
     *
     * @return array<string, int>
     */
    public static function bandSizes(int $total): array
    {
        $base = intdiv($total, 3);
        $rest = $total % 3;

        return [
            DrawEntry::BAND_LOW => $base + ($rest >= 1 ? 1 : 0),
            DrawEntry::BAND_MID => $base + ($rest >= 2 ? 1 : 0),
            DrawEntry::BAND_HIGH => $base,
        ];
    }

    /**
     * Rango de números [desde, hasta] de cada franja (vacía si desde > hasta).
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function bandRanges(int $total): array
    {
        $ranges = [];
        $start = 1;
        foreach (self::bandSizes($total) as $band => $size) {
            $ranges[$band] = [$start, $start + $size - 1];
            $start += $size;
        }

        return $ranges;
    }

    /* ---------- Reparto (sin base de datos) ---------- */

    /**
     * Reparte los números 1..N entre los participantes.
     *
     * @param  array<int, string|null>  $previousBands  vehicle_id => franja de su sorteo anterior (null = nunca participó)
     * @param  array<int, int>  $pastDeviations  vehicle_id => desvíos acumulados (para repartir los nuevos)
     * @return array<int, array{number: int, band: string, previous_band: string|null, deviated: bool}>
     */
    public function assign(array $previousBands, array $pastDeviations = []): array
    {
        $total = count($previousBands);
        if ($total === 0) {
            return [];
        }

        $capacity = self::bandSizes($total);

        // Grupos por franja anterior, barajados para desempatar al azar
        $groups = array_fill_keys([...DrawEntry::BANDS, self::NO_HISTORY], []);
        foreach ($previousBands as $vehicleId => $band) {
            $groups[$band ?? self::NO_HISTORY][] = $vehicleId;
        }
        foreach ($groups as $key => $ids) {
            $groups[$key] = $this->randomizer->shuffleArray($ids);
        }

        $flow = $this->solveTransport(
            array_map('count', $groups),
            $capacity,
            fn (string $group, string $band) => $this->bandCost($group, $band),
        );

        // Qué vehículos de cada grupo van a cada franja: primero la que toca
        // (a quien más desvíos acumula), luego la otra permitida y al final repetir.
        $byBand = array_fill_keys(DrawEntry::BANDS, []);
        foreach ($groups as $group => $ids) {
            if ($group !== self::NO_HISTORY) {
                usort($ids, fn ($a, $b) => ($pastDeviations[$b] ?? 0) <=> ($pastDeviations[$a] ?? 0));
            }
            $bands = DrawEntry::BANDS;
            usort($bands, fn ($a, $b) => $this->bandCost($group, $a) <=> $this->bandCost($group, $b));
            foreach ($bands as $band) {
                $take = $flow[$group][$band] ?? 0;
                array_push($byBand[$band], ...array_splice($ids, 0, $take));
            }
        }

        $result = [];
        foreach (self::bandRanges($total) as $band => [$from, $to]) {
            $numbers = $from <= $to ? $this->randomizer->shuffleArray(range($from, $to)) : [];
            foreach ($byBand[$band] as $i => $vehicleId) {
                $previous = $previousBands[$vehicleId];
                $result[$vehicleId] = [
                    'number' => $numbers[$i],
                    'band' => $band,
                    'previous_band' => $previous,
                    'deviated' => $previous !== null && $band !== DrawEntry::nextBand($previous),
                ];
            }
        }

        return $result;
    }

    /**
     * Simula sorteos seguidos sin guardar nada: cada día participan al azar
     * entre $min y $max de los habilitados y al resto se le quita el check.
     * Cada día parte de las franjas que dejó el anterior, como en la realidad.
     *
     * @param  array<int, string>  $dates  días en orden (Y-m-d)
     * @param  array<string, array<int, int>>  $eligibleByDate  fecha => vehicle_ids habilitados ese día
     * @param  array<int, string>  $previousBands  vehicle_id => franja real antes del primer día
     * @return array<string, array<int, array{number: int|null, band: string|null, previous_band: string|null, deviated: bool}>>
     */
    public function simulate(array $dates, array $eligibleByDate, array $previousBands, int $min, int $max): array
    {
        $lastBand = $previousBands;
        $deviations = [];
        $result = [];

        foreach ($dates as $date) {
            $eligible = array_values($eligibleByDate[$date] ?? []);
            $result[$date] = [];
            if (count($eligible) === 0) {
                continue;
            }

            $count = $this->randomizer->getInt(min($min, count($eligible)), min($max, count($eligible)));
            $participants = (array) $this->randomizer->pickArrayKeys(array_flip($eligible), $count);

            $previous = [];
            foreach ($participants as $vehicleId) {
                $previous[$vehicleId] = $lastBand[$vehicleId] ?? null;
            }
            $assigned = $this->assign($previous, $deviations);

            foreach ($eligible as $vehicleId) {
                $result[$date][$vehicleId] = $assigned[$vehicleId]
                    ?? ['number' => null, 'band' => null, 'previous_band' => null, 'deviated' => false];
            }
            foreach ($assigned as $vehicleId => $entry) {
                $lastBand[$vehicleId] = $entry['band'];
                $deviations[$vehicleId] = ($deviations[$vehicleId] ?? 0) + ($entry['deviated'] ? 1 : 0);
            }
        }

        return $result;
    }

    /**
     * Simulación de los días dados con los vehículos y el historial reales:
     * cada día entran los habilitados a esa fecha y parte de las franjas
     * reales anteriores al primer día. Con la misma semilla en el Randomizer
     * da siempre el mismo resultado (la grilla y la página del día coinciden).
     *
     * @param  array<int, string>  $dates
     * @return array<string, array<int, array{number: int|null, band: string|null, previous_band: string|null, deviated: bool, plate: string, driver_name: string|null}>>
     */
    public function simulateMonth(array $dates, int $min, int $max): array
    {
        if (count($dates) === 0) {
            return [];
        }

        $vehicles = Vehicle::active()->with('driver:id,name')->orderBy('sort_order')->orderBy('plate')
            ->get(['id', 'plate', 'driver_id', 'not_working_since'])->keyBy('id');

        $eligible = [];
        foreach ($dates as $date) {
            $eligible[$date] = $vehicles
                ->filter(fn (Vehicle $v) => self::entersDrawOn($v->not_working_since?->toDateString(), $date))
                ->keys()->all();
        }

        $simulated = $this->simulate($dates, $eligible, $this->previousBands($vehicles->keys()->all(), $dates[0]), $min, $max);

        foreach ($simulated as $date => $day) {
            foreach ($day as $vehicleId => $entry) {
                $simulated[$date][$vehicleId] += [
                    'plate' => $vehicles[$vehicleId]->plate,
                    'driver_name' => $vehicles[$vehicleId]->driver->name,
                ];
            }
        }

        return $simulated;
    }

    /**
     * Lista del orden de salida (1..N) a partir de los resultados de un día.
     *
     * @param  iterable<object|array>  $entries  filas con number, plate y driver_name
     * @return array<int, array{number: int, plate: string, driver: string}>
     */
    public static function orderItems(iterable $entries): array
    {
        return collect($entries)
            ->map(fn ($e) => (object) (is_array($e) ? $e : ['number' => $e->number, 'plate' => $e->plate, 'driver_name' => $e->driver_name]))
            ->filter(fn (object $e) => $e->number !== null)
            ->sortBy('number')
            ->map(fn (object $e) => ['number' => (int) $e->number, 'plate' => (string) $e->plate, 'driver' => (string) $e->driver_name])
            ->values()
            ->all();
    }

    private function bandCost(string $group, string $band): int
    {
        if ($group === self::NO_HISTORY || $band === DrawEntry::nextBand($group)) {
            return 0;
        }

        return $band === $group ? self::REPEAT_COST : self::DEVIATE_COST;
    }

    /**
     * Transporte de costo mínimo (caminos más cortos sucesivos con
     * Bellman-Ford): cuántos de cada grupo van a cada franja.
     *
     * @param  array<string, int>  $supply  grupo => cantidad de vehículos
     * @param  array<string, int>  $capacity  franja => cantidad de números
     * @param  callable(string, string): int  $cost
     * @return array<string, array<string, int>>
     */
    private function solveTransport(array $supply, array $capacity, callable $cost): array
    {
        $edges = [];
        $addEdge = function (int $from, int $to, int $cap, int $edgeCost) use (&$edges): int {
            $edges[] = ['from' => $from, 'to' => $to, 'cap' => $cap, 'cost' => $edgeCost];
            $edges[] = ['from' => $to, 'to' => $from, 'cap' => 0, 'cost' => -$edgeCost];

            return count($edges) - 2;
        };

        $source = 0;
        $node = 1;
        $groupNodes = [];
        foreach (array_keys($supply) as $group) {
            $groupNodes[$group] = $node++;
        }
        $bandNodes = [];
        foreach (array_keys($capacity) as $band) {
            $bandNodes[$band] = $node++;
        }
        $sink = $node++;
        $nodes = $node;

        $total = array_sum($supply);
        $pairEdges = [];
        foreach ($supply as $group => $count) {
            $addEdge($source, $groupNodes[$group], $count, 0);
            foreach ($capacity as $band => $_) {
                $pairEdges[$group][$band] = $addEdge($groupNodes[$group], $bandNodes[$band], $total, $cost($group, $band));
            }
        }
        foreach ($capacity as $band => $cap) {
            $addEdge($bandNodes[$band], $sink, $cap, 0);
        }

        while (true) {
            $dist = array_fill(0, $nodes, PHP_INT_MAX);
            $via = array_fill(0, $nodes, -1);
            $dist[$source] = 0;
            for ($round = 1; $round < $nodes; $round++) {
                $changed = false;
                foreach ($edges as $id => $e) {
                    if ($e['cap'] > 0 && $dist[$e['from']] !== PHP_INT_MAX && $dist[$e['to']] > $dist[$e['from']] + $e['cost']) {
                        $dist[$e['to']] = $dist[$e['from']] + $e['cost'];
                        $via[$e['to']] = $id;
                        $changed = true;
                    }
                }
                if (! $changed) {
                    break;
                }
            }
            if ($dist[$sink] === PHP_INT_MAX) {
                break;
            }

            $push = PHP_INT_MAX;
            for ($v = $sink; $v !== $source; $v = $edges[$via[$v]]['from']) {
                $push = min($push, $edges[$via[$v]]['cap']);
            }
            for ($v = $sink; $v !== $source; $v = $edges[$via[$v]]['from']) {
                $edges[$via[$v]]['cap'] -= $push;
                $edges[$via[$v] ^ 1]['cap'] += $push;
            }
        }

        $flow = [];
        foreach ($pairEdges as $group => $bands) {
            foreach ($bands as $band => $id) {
                $flow[$group][$band] = $edges[$id ^ 1]['cap'];
            }
        }

        return $flow;
    }

    /* ---------- Base de datos ---------- */

    /**
     * Vehículos que entran a la grilla y al sorteo de $date (hoy si es null):
     * los activos, menos los NT en rojo (NOT_WORKING_DROP_DAYS o más sin trabajar a esa fecha).
     */
    public static function activeVehicles(?string $date = null): Builder
    {
        $cutoff = self::notWorkingCutoff($date ?? Carbon::today()->toDateString());

        return Vehicle::active()
            ->where(fn ($q) => $q->whereNull('not_working_since')->orWhere('not_working_since', '>', $cutoff))
            ->orderBy('sort_order')
            ->orderBy('plate');
    }

    /** Un NT marcado en esta fecha o antes ya está en rojo el $date y no entra al sorteo. */
    public static function notWorkingCutoff(string $date): string
    {
        return Carbon::parse($date)->subDays(Vehicle::NOT_WORKING_DROP_DAYS)->toDateString();
    }

    /** Si un NT marcado el $notWorkingSince puede entrar al sorteo del $date. */
    public static function entersDrawOn(?string $notWorkingSince, string $date): bool
    {
        return $notWorkingSince === null || $notWorkingSince > self::notWorkingCutoff($date);
    }

    /**
     * Feriados (Y-m-d) desde $from hasta lo necesario para hallar el siguiente día hábil.
     *
     * @return array<int, string>
     */
    public static function holidaysAround(Carbon $from): array
    {
        return Holiday::datesBetween($from->toDateString(), $from->copy()->addDays(self::HOLIDAY_LOOKAHEAD_DAYS)->toDateString());
    }

    /**
     * Sortea un día. $includedVehicleIds son los marcados; el resto de los
     * activos queda registrado como excluido.
     *
     * @param  array<int, int|string>  $includedVehicleIds
     *
     * @throws ValidationException si el día no se puede sortear o no hay participantes
     */
    public function run(string $date, array $includedVehicleIds, User $user): Draw
    {
        $included = array_map('intval', $includedVehicleIds);

        return DB::transaction(function () use ($date, $included, $user) {
            // FOR UPDATE: dos sorteos simultáneos no calculan sobre el mismo historial
            $lastDate = Draw::active()->lockForUpdate()->max('date');
            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : null;

            $today = Carbon::today();
            if (! in_array($date, self::drawableDates($lastDate, $today, self::holidaysAround($today)), true)) {
                throw ValidationException::withMessages(['date' => 'Ese día ya no se puede sortear. Actualiza la página.']);
            }

            $vehicles = self::activeVehicles($date)->with('driver:id,name')->get(['id', 'plate', 'driver_id']);
            $participantIds = $vehicles->pluck('id')->intersect($included)->values()->all();
            if (count($participantIds) === 0) {
                throw ValidationException::withMessages(['date' => 'Marca al menos un vehículo para sortear.']);
            }

            $previousBands = array_fill_keys($participantIds, null);
            foreach ($this->previousBands($participantIds, $date) as $vehicleId => $band) {
                $previousBands[$vehicleId] = $band;
            }

            $assigned = $this->assign($previousBands, $this->pastDeviations($participantIds));

            $draw = Draw::create([
                'code' => Draw::newCode(),
                'date' => $date,
                'active_date' => $date,
                'participants' => count($assigned),
                'deviations' => count(array_filter($assigned, fn (array $a) => $a['deviated'])),
                'drawn_by' => $user->id,
            ]);

            $now = now();
            $rows = $vehicles->map(fn (Vehicle $v) => [
                'draw_id' => $draw->id,
                'vehicle_id' => $v->id,
                'plate' => $v->plate,
                'driver_name' => $v->driver->name,
                'number' => $assigned[$v->id]['number'] ?? null,
                'band' => $assigned[$v->id]['band'] ?? null,
                'previous_band' => $assigned[$v->id]['previous_band'] ?? null,
                'deviated' => $assigned[$v->id]['deviated'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DrawEntry::insert($rows);

            return $draw;
        }, 3);
    }

    /**
     * Anula el último sorteo vigente; el día vuelve a quedar libre.
     *
     * @throws ValidationException si no es el último sorteo vigente
     */
    public function annul(int $drawId, string $reason, User $user): Draw
    {
        return DB::transaction(function () use ($drawId, $reason, $user) {
            $draw = Draw::lockForUpdate()->findOrFail($drawId);
            $lastDate = Draw::active()->lockForUpdate()->max('date');

            if ($draw->isAnnulled() || $lastDate === null || $draw->date->toDateString() !== Carbon::parse($lastDate)->toDateString()) {
                throw ValidationException::withMessages(['annul' => 'Solo se puede anular el último sorteo vigente. Actualiza la página.']);
            }

            $draw->update([
                'active_date' => null,
                'annulled_at' => now(),
                'annulled_by' => $user->id,
                'annul_reason' => $reason,
            ]);

            return $draw;
        });
    }

    /**
     * Franja del último sorteo vigente anterior a $date en que participó cada vehículo.
     *
     * @param  array<int, int>  $vehicleIds
     * @return array<int, string> vehicle_id => franja
     */
    public function previousBands(array $vehicleIds, string $date): array
    {
        $latest = DB::table('draw_entries as e')
            ->join('draws as d', 'd.id', '=', 'e.draw_id')
            ->whereNull('d.annulled_at')
            ->whereNotNull('e.number')
            ->whereIn('e.vehicle_id', $vehicleIds)
            ->where('d.date', '<', $date)
            ->groupBy('e.vehicle_id')
            ->select('e.vehicle_id', DB::raw('MAX(d.date) as last_date'));

        return DB::table('draw_entries as e')
            ->join('draws as d', 'd.id', '=', 'e.draw_id')
            ->joinSub($latest, 'l', fn ($j) => $j->on('l.vehicle_id', '=', 'e.vehicle_id')->on('l.last_date', '=', 'd.date'))
            ->whereNull('d.annulled_at')
            ->whereNotNull('e.number')
            ->pluck('e.band', 'e.vehicle_id')
            ->map(fn ($band) => (string) $band)
            ->all();
    }

    /**
     * Desvíos acumulados de cada vehículo en sorteos vigentes.
     *
     * @param  array<int, int>  $vehicleIds
     * @return array<int, int>
     */
    public function pastDeviations(array $vehicleIds): array
    {
        return DB::table('draw_entries as e')
            ->join('draws as d', 'd.id', '=', 'e.draw_id')
            ->whereNull('d.annulled_at')
            ->where('e.deviated', true)
            ->whereIn('e.vehicle_id', $vehicleIds)
            ->groupBy('e.vehicle_id')
            ->selectRaw('e.vehicle_id, COUNT(*) as total')
            ->pluck('total', 'e.vehicle_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
