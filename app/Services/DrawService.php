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
 * a bajo y luego a medio (44 = 15/15/14). Prioridades, de mayor a menor:
 *  1. nadie repite la franja de su sorteo anterior;
 *  2. nadie repite en el mismo mes un número que ya tuvo;
 *  3. cada uno recibe la franja que le toca en el ciclo; si no cuadra
 *     (vehículos quitados, N no divisible) se desvía a la menor cantidad
 *     posible, eligiendo a quienes menos desvíos acumulan.
 * Las reglas 1 y 2 solo se rompen si no hay forma de cumplirlas, y queda
 * registrado igual.
 *
 * El reparto es una asignación de costo mínimo vehículo -> número (método
 * húngaro, N x N): cada prioridad pesa mucho más que la siguiente. Vehículos
 * y números se barajan antes, así entre repartos de igual costo decide la suerte.
 */
class DrawService
{
    /** Costo de ir a la franja permitida que no tocaba en el ciclo (+1 por cada desvío previo). */
    private const DEVIATE_COST = 100;

    /** Costo de repetir en el mes un número que el vehículo ya tuvo. */
    private const NUMBER_REPEAT_COST = 10_000;

    /** Costo de repetir la franja anterior: solo cuando no hay otra salida. */
    private const REPEAT_COST = 1_000_000;

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
     * @param  array<int, array<int, int>>  $usedNumbers  vehicle_id => números que ya recibió este mes (no se repiten)
     * @return array<int, array{number: int, band: string, previous_band: string|null, deviated: bool, number_repeated: bool}>
     */
    public function assign(array $previousBands, array $pastDeviations = [], array $usedNumbers = []): array
    {
        $total = count($previousBands);
        if ($total === 0) {
            return [];
        }

        $bandOf = [];
        foreach (self::bandRanges($total) as $band => [$from, $to]) {
            for ($number = $from; $number <= $to; $number++) {
                $bandOf[$number] = $band;
            }
        }

        $vehicleIds = $this->randomizer->shuffleArray(array_keys($previousBands));
        $numbers = $this->randomizer->shuffleArray(range(1, $total));

        $cost = [];
        foreach ($vehicleIds as $i => $vehicleId) {
            $used = array_flip($usedNumbers[$vehicleId] ?? []);
            foreach ($numbers as $j => $number) {
                $cost[$i][$j] = $this->bandCost($previousBands[$vehicleId], $bandOf[$number], $pastDeviations[$vehicleId] ?? 0)
                    + (isset($used[$number]) ? self::NUMBER_REPEAT_COST : 0);
            }
        }

        $result = [];
        foreach ($this->solveAssignment($cost) as $i => $j) {
            $vehicleId = $vehicleIds[$i];
            $number = $numbers[$j];
            $previous = $previousBands[$vehicleId];
            $result[$vehicleId] = [
                'number' => $number,
                'band' => $bandOf[$number],
                'previous_band' => $previous,
                'deviated' => $previous !== null && $bandOf[$number] !== DrawEntry::nextBand($previous),
                'number_repeated' => in_array($number, $usedNumbers[$vehicleId] ?? [], true),
            ];
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
     * @return array<string, array<int, array{number: int|null, band: string|null, previous_band: string|null, deviated: bool, number_repeated: bool}>>
     */
    public function simulate(array $dates, array $eligibleByDate, array $previousBands, int $min, int $max): array
    {
        $lastBand = $previousBands;
        $deviations = [];
        $usedNumbers = [];
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
            $month = substr($date, 0, 7);
            $assigned = $this->assign($previous, $deviations, $usedNumbers[$month] ?? []);

            foreach ($eligible as $vehicleId) {
                $result[$date][$vehicleId] = $assigned[$vehicleId]
                    ?? ['number' => null, 'band' => null, 'previous_band' => null, 'deviated' => false, 'number_repeated' => false];
            }
            foreach ($assigned as $vehicleId => $entry) {
                $lastBand[$vehicleId] = $entry['band'];
                $deviations[$vehicleId] = ($deviations[$vehicleId] ?? 0) + ($entry['deviated'] ? 1 : 0);
                $usedNumbers[$month][$vehicleId][] = $entry['number'];
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
     * Totales de una simulación (salida de simulate o simulateMonth), para el
     * aviso de la grilla y la hoja Excel.
     *
     * @param  array<string, array<int, array{number: int|null, band: string|null, previous_band: string|null, deviated: bool}>>  $simulated
     * @return array{days: int, minParticipants: int, maxParticipants: int, numbers: int, deviations: int, repeats: int, repeatedNumbers: int, even: int, vehicles: int, maxGap: int}
     */
    public static function summarize(array $simulated): array
    {
        $participants = [];
        $deviations = 0;
        $repeats = 0;
        $numbers = [];
        $bands = [];
        foreach ($simulated as $day) {
            $count = 0;
            foreach ($day as $vehicleId => $entry) {
                if ($entry['number'] === null) {
                    continue;
                }
                $count++;
                $deviations += $entry['deviated'] ? 1 : 0;
                $repeats += $entry['band'] === $entry['previous_band'] ? 1 : 0;
                $numbers[$vehicleId][] = $entry['number'];
                $bands[$vehicleId][$entry['band']] = ($bands[$vehicleId][$entry['band']] ?? 0) + 1;
            }
            $participants[] = $count;
        }

        $zero = array_fill_keys(DrawEntry::BANDS, 0);
        $gaps = array_map(fn (array $b) => max($b + $zero) - min($b + $zero), $bands);

        return [
            'days' => count($simulated),
            'minParticipants' => $participants ? min($participants) : 0,
            'maxParticipants' => $participants ? max($participants) : 0,
            'numbers' => array_sum($participants),
            'deviations' => $deviations,
            'repeats' => $repeats,
            'repeatedNumbers' => array_sum(array_map(fn (array $n) => count($n) - count(array_unique($n)), $numbers)),
            'even' => count(array_filter($gaps, fn (int $g) => $g <= 1)),
            'vehicles' => count($gaps),
            'maxGap' => $gaps ? max($gaps) : 0,
        ];
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

    private function bandCost(?string $previous, string $band, int $pastDeviations): int
    {
        if ($previous === null || $band === DrawEntry::nextBand($previous)) {
            return 0;
        }

        return $band === $previous ? self::REPEAT_COST : self::DEVIATE_COST + $pastDeviations;
    }

    /**
     * Asignación de costo mínimo en una matriz cuadrada (método húngaro con
     * potenciales, O(n³); con 45 vehículos tarda milisegundos).
     *
     * @param  array<int, array<int, int>>  $cost  fila (vehículo) => columna (número) => costo
     * @return array<int, int> fila => columna
     */
    private function solveAssignment(array $cost): array
    {
        $n = count($cost);
        $inf = PHP_INT_MAX >> 2;
        $u = array_fill(0, $n + 1, 0);
        $v = array_fill(0, $n + 1, 0);
        $match = array_fill(0, $n + 1, 0); // columna => fila (1-indexado)
        $way = array_fill(0, $n + 1, 0);

        for ($row = 1; $row <= $n; $row++) {
            $match[0] = $row;
            $col0 = 0;
            $minv = array_fill(0, $n + 1, $inf);
            $used = array_fill(0, $n + 1, false);
            do {
                $used[$col0] = true;
                $row0 = $match[$col0];
                $delta = $inf;
                $col1 = 0;
                for ($col = 1; $col <= $n; $col++) {
                    if ($used[$col]) {
                        continue;
                    }
                    $current = $cost[$row0 - 1][$col - 1] - $u[$row0] - $v[$col];
                    if ($current < $minv[$col]) {
                        $minv[$col] = $current;
                        $way[$col] = $col0;
                    }
                    if ($minv[$col] < $delta) {
                        $delta = $minv[$col];
                        $col1 = $col;
                    }
                }
                for ($col = 0; $col <= $n; $col++) {
                    if ($used[$col]) {
                        $u[$match[$col]] += $delta;
                        $v[$col] -= $delta;
                    } else {
                        $minv[$col] -= $delta;
                    }
                }
                $col0 = $col1;
            } while ($match[$col0] !== 0);

            do {
                $col1 = $way[$col0];
                $match[$col0] = $match[$col1];
                $col0 = $col1;
            } while ($col0 !== 0);
        }

        $assignment = [];
        for ($col = 1; $col <= $n; $col++) {
            $assignment[$match[$col] - 1] = $col - 1;
        }

        return $assignment;
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

            $assigned = $this->assign($previousBands, $this->pastDeviations($participantIds), $this->usedNumbersInMonth($participantIds, $date));

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
     * Números que cada vehículo ya recibió en sorteos vigentes del mismo mes,
     * antes de $date: no se le pueden repetir.
     *
     * @param  array<int, int>  $vehicleIds
     * @return array<int, array<int, int>> vehicle_id => números
     */
    public function usedNumbersInMonth(array $vehicleIds, string $date): array
    {
        $used = [];
        DB::table('draw_entries as e')
            ->join('draws as d', 'd.id', '=', 'e.draw_id')
            ->whereNull('d.annulled_at')
            ->whereNotNull('e.number')
            ->whereIn('e.vehicle_id', $vehicleIds)
            ->whereBetween('d.date', [Carbon::parse($date)->startOfMonth()->toDateString(), Carbon::parse($date)->subDay()->toDateString()])
            ->get(['e.vehicle_id', 'e.number'])
            ->each(function ($row) use (&$used) {
                $used[$row->vehicle_id][] = (int) $row->number;
            });

        return $used;
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
