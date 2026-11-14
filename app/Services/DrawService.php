<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\DrawBlock;
use App\Models\DrawEntry;
use App\Models\DrawSanction;
use App\Models\Holiday;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
 * El domingo es un sorteo aparte (opcional, con las placas que se elijan):
 * lleva su propia rotación y sus propios números del mes, comparándose solo
 * con otros domingos, y no cuenta para los de lunes a sábado (seriesEntries).
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

    /** Modos del sorteo de los domingos (config draws.sundays). */
    public const SUNDAYS_OFF = 'off';

    public const SUNDAYS_TEST = 'test';

    public const SUNDAYS_ON = 'on';

    /** Días que se miran hacia adelante para hallar el siguiente día hábil. */
    private const HOLIDAY_LOOKAHEAD_DAYS = 20;

    /** Hasta cuántos días adelante se buscan días libres para una suspensión. */
    private const SUSPENSION_LOOKAHEAD_DAYS = 90;

    private const WEEKDAY_LABELS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'];

    /** Partículas de apellidos compuestos ("De La Cruz", "Del Castillo"). */
    private const SURNAME_PARTICLES = ['de', 'del', 'la', 'las', 'los', 'san', 'santa'];

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
     * El domingo es opcional: se puede sortear el de hoy o el que cae antes del
     * siguiente día hábil (el sábado ya se ve el domingo y el lunes). Si no se
     * sortea, se salta como cualquier día pasado. Un domingo feriado no.
     *
     * Con $drawnDates (los días ya sorteados), ese domingo se puede sortear
     * aunque ya se haya sorteado el lunes (domingo tardío): como el domingo es
     * un sorteo aparte, ni el lunes ni el domingo cambian por eso.
     * Sin $drawnDates (bloqueos, firstOpenDate) solo cuentan los posteriores al último sorteo.
     *
     * @param  array<int, string>  $holidays
     * @param  array<int, string>|null  $drawnDates  fechas Y-m-d con sorteo vigente
     * @return array<int, string> fechas Y-m-d
     */
    public static function drawableDates(?string $lastDrawDate, Carbon $today, array $holidays, ?array $drawnDates = null): array
    {
        $today = $today->copy()->startOfDay();
        $next = self::nextWorkingDay($today, $holidays);

        $dates = [];
        if (self::isWorkingDay($today, $holidays) || self::isDrawableSunday($today, $holidays)) {
            $dates[] = $today->toDateString();
        }
        for ($day = $today->copy()->addDay(); $day->lt($next); $day->addDay()) {
            if (self::isDrawableSunday($day, $holidays)) {
                $dates[] = $day->toDateString();
            }
        }
        $dates[] = $next->toDateString();

        return array_values(array_filter($dates, fn (string $d) => $lastDrawDate === null || $d > $lastDrawDate
            || ($drawnDates !== null && Carbon::parse($d)->isSunday() && ! in_array($d, $drawnDates, true))));
    }

    /**
     * Domingo que se puede sortear (opcional, eligiendo quiénes participan): cualquiera que no sea feriado.
     *
     * @param  array<int, string>  $holidays
     */
    public static function isDrawableSunday(Carbon $date, array $holidays): bool
    {
        return $date->isSunday() && ! in_array($date->toDateString(), $holidays, true);
    }

    /** Modo del sorteo de los domingos (config draws.sundays); si no es válido, apagado. */
    public static function sundayMode(): string
    {
        $mode = config('draws.sundays');

        return in_array($mode, [self::SUNDAYS_OFF, self::SUNDAYS_TEST, self::SUNDAYS_ON], true) ? $mode : self::SUNDAYS_OFF;
    }

    /**
     * Días sorteables que se muestran en pantalla según el modo de los domingos:
     *  - on:   tal cual los da drawableDates
     *  - test: además el próximo domingo (hoy si es domingo), para probarlo
     *          cualquier día hasta la confirmación; run no lo deja sortear
     *  - off:  sin domingos
     *
     * @param  array<int, string>  $dates  de drawableDates
     * @param  array<int, string>  $holidays
     * @param  array<int, string>  $drawnDates  fechas Y-m-d con sorteo vigente
     * @return array<int, string> fechas Y-m-d
     */
    public static function withSundayMode(array $dates, string $mode, Carbon $today, array $holidays, array $drawnDates): array
    {
        if ($mode === self::SUNDAYS_OFF) {
            return array_values(array_filter($dates, fn (string $d) => ! Carbon::parse($d)->isSunday()));
        }

        if ($mode === self::SUNDAYS_TEST) {
            $sunday = $today->copy()->startOfDay();
            if (! $sunday->isSunday()) {
                $sunday = $sunday->next(Carbon::SUNDAY);
            }
            $date = $sunday->toDateString();
            if (self::isDrawableSunday($sunday, $holidays) && ! in_array($date, $dates, true) && ! in_array($date, $drawnDates, true)) {
                $dates[] = $date;
                sort($dates);
            }
        }

        return $dates;
    }

    /**
     * Primer día que todavía se puede sortear: desde ahí se puede bloquear o
     * desbloquear (lo ya sorteado no cambia).
     *
     * @param  array<int, string>  $holidays
     */
    public static function firstOpenDate(?string $lastDrawDate, Carbon $today, array $holidays): string
    {
        return self::drawableDates($lastDrawDate, $today, $holidays)[0]
            ?? self::nextWorkingDay(Carbon::parse($lastDrawDate), $holidays)->toDateString();
    }

    /**
     * Días de una suspensión por sanciones: los $count primeros días hábiles
     * (lunes a sábado sin feriados) después de hoy que todavía no se sortearon
     * y en los que la placa no está ya bloqueada ($alreadyBlocked): así la
     * suspensión se suma a sus bloqueos y a otras suspensiones en vez de
     * cumplirse a la vez. El domingo no cuenta como día de suspensión
     * (DrawBlock::coversDate dice cuándo queda bloqueado).
     *
     * Viernes 06 sin sortear el sábado: Sáb 07, Lun 09, Mar 10. Si el sábado ya
     * se sorteó: Lun 09, Mar 10, Mié 11. Si el lunes y el martes ya estaba
     * bloqueada: Sáb 07, Mié 11, Jue 12.
     *
     * Si no hay $count días libres en SUSPENSION_LOOKAHEAD_DAYS (bloqueada
     * hasta que la desbloqueen), no hay dónde sumarla: van los días de
     * siempre y se cumple junto con su bloqueo.
     *
     * @param  array<int, string>  $holidays
     * @param  (\Closure(string): bool)|null  $alreadyBlocked  ¿la placa ya está bloqueada ese día (Y-m-d)?
     * @return array<int, string> fechas Y-m-d
     */
    public static function suspensionDates(Carbon $today, ?string $lastDrawDate, array $holidays, int $count = DrawSanction::SUSPENSION_DAYS, ?\Closure $alreadyBlocked = null): array
    {
        $limit = $today->copy()->addDays(self::SUSPENSION_LOOKAHEAD_DAYS)->toDateString();

        $dates = [];
        for ($day = self::nextWorkingDay($today, $holidays); count($dates) < $count; $day = self::nextWorkingDay($day, $holidays)) {
            $date = $day->toDateString();
            if ($alreadyBlocked !== null && $date > $limit) {
                return self::suspensionDates($today, $lastDrawDate, $holidays, $count);
            }
            if (($lastDrawDate === null || $date > $lastDrawDate) && ! ($alreadyBlocked !== null && $alreadyBlocked($date))) {
                $dates[] = $date;
            }
        }

        return $dates;
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

    /**
     * Columnas del calendario: los días hábiles más todos los domingos del mes que
     * no son feriado, se hayan sorteado o no, y los domingos ya sorteados o que se
     * pueden sortear aunque sean feriado (que no desaparezcan).
     *
     * @param  array<int, array{date: string, n: int, weekday: string}>  $workingDays  de workingDaysOfMonth
     * @param  array<int, string>  $holidays
     * @param  array<int, string>  $keepSundays  fechas Y-m-d sorteadas o sorteables
     * @return array<int, array{date: string, n: int, weekday: string, sunday?: bool}>
     */
    public static function calendarDays(array $workingDays, string $from, string $to, array $holidays, array $keepSundays = []): array
    {
        $days = $workingDays;
        for ($d = Carbon::parse($from)->startOfDay(), $end = Carbon::parse($to)->startOfDay(); $d->lte($end); $d->addDay()) {
            if ($d->isSunday() && (self::isDrawableSunday($d, $holidays) || in_array($d->toDateString(), $keepSundays, true))) {
                $days[] = ['date' => $d->toDateString(), 'n' => $d->day, 'weekday' => self::weekdayLabel($d), 'sunday' => true];
            }
        }
        usort($days, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

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
            $target = self::targetBand($previous);
            $result[$vehicleId] = [
                'number' => $number,
                'band' => $bandOf[$number],
                'previous_band' => $previous,
                'deviated' => $target !== null && $bandOf[$number] !== $target,
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

        // Las placas bloqueadas tampoco entran a la simulación los días que cubre
        // su bloqueo (menos los días desbloqueados)
        $vehicles = Vehicle::active()->with('driver:id,name')
            ->orderBy('sort_order')->orderBy('plate')
            ->get(['id', 'plate', 'driver_id', 'not_working_since', 'termination_date'])->keyBy('id');
        $blocks = DrawBlock::active()->overlapping($dates[0], end($dates))->with('exceptions')->get()->groupBy('vehicle_id');

        $eligible = [];
        foreach ($dates as $date) {
            $eligible[$date] = $vehicles
                ->filter(fn (Vehicle $v) => self::entersDrawOn($v->not_working_since?->toDateString(), $date)
                    && ($v->termination_date === null || $v->termination_date->toDateString() > $date) // desde su baja ya no entra
                    && ! ($blocks[$v->id] ?? collect())->contains(fn (DrawBlock $b) => $b->appliesOn($date)))
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
            ->map(fn (object $e) => ['number' => (int) $e->number, 'plate' => (string) $e->plate, 'driver' => self::firstName($e->driver_name)])
            ->values()
            ->all();
    }

    /**
     * Solo el primer nombre, para el orden de salida. El conductor se guarda
     * como "Apellido paterno Apellido materno Nombres": se saltan los dos
     * apellidos con sus partículas ("De La Cruz Vidal Efrain Herson" → "Efrain").
     * Si no queda nada (nombre de una o dos palabras), la última palabra.
     */
    public static function firstName(?string $fullName): string
    {
        $words = preg_split('/\s+/', trim((string) $fullName), -1, PREG_SPLIT_NO_EMPTY);
        $index = 0;

        for ($surnames = 0; $surnames < 2 && $index < count($words); $surnames++) {
            while ($index < count($words) - 1 && in_array(mb_strtolower($words[$index]), self::SURNAME_PARTICLES, true)) {
                $index++;
            }
            $index++;
        }

        return (array_slice($words, $index) ?: array_slice($words, -1))[0] ?? '';
    }

    private function bandCost(?string $previous, string $band, int $pastDeviations): int
    {
        if ($band === $previous) {
            return self::REPEAT_COST;
        }
        $target = self::targetBand($previous);

        return $target === null || $band === $target ? 0 : self::DEVIATE_COST + $pastDeviations;
    }

    /**
     * Franja que le toca a un vehículo: la siguiente del ciclo a la que tuvo
     * en su sorteo anterior. Null (nunca participó) = cualquiera.
     */
    public static function targetBand(?string $previous): ?string
    {
        return $previous !== null ? DrawEntry::nextBand($previous) : null;
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
     * Vehículos de la grilla: los activos que trabajan ese día (sin baja o con
     * la baja después), menos los NT en rojo (NOT_WORKING_DROP_DAYS o
     * más sin trabajar a esa fecha). Los bloqueados siguen en la grilla, con
     * candado los días que no entran.
     */
    public static function gridVehicles(?string $date = null): Builder
    {
        $date ??= Carbon::today()->toDateString();
        $cutoff = self::notWorkingCutoff($date);

        return Vehicle::active()->worksOn($date)
            ->where(fn ($q) => $q->whereNull('not_working_since')->orWhere('not_working_since', '>', $cutoff))
            ->orderBy('sort_order')
            ->orderBy('plate');
    }

    /**
     * Vehículos que entran al sorteo de $date (hoy si es null): los de la
     * grilla menos los bloqueados ese día (sin contar los días desbloqueados).
     */
    public static function activeVehicles(?string $date = null): Builder
    {
        $date ??= Carbon::today()->toDateString();

        return self::gridVehicles($date)->whereNotIn('id', DrawBlock::active()->onDate($date)->select('vehicle_id'));
    }

    /**
     * Sorteos vigentes ya hechos para su fecha de baja ($from) o después en
     * los que la placa tiene número: al darla de baja igual sigue en ellos.
     *
     * @return array<int, array{date: string, number: int}>
     */
    public static function drawsFrom(int $vehicleId, string $from): array
    {
        return DrawEntry::where('vehicle_id', $vehicleId)->whereNotNull('number')
            ->whereHas('draw', fn (Builder $q) => $q->active()->where('date', '>=', $from))
            ->with('draw:id,date')->get()
            ->map(fn (DrawEntry $e) => ['date' => $e->draw->date->toDateString(), 'number' => (int) $e->number])
            ->sortBy('date')->values()->all();
    }

    /**
     * Aviso al dar de baja una placa que ya está en sorteos desde su fecha de
     * baja (drawsFrom), o null si no hay. Un sorteo hecho no cambia:
     * hay que anularlo (desde el último) y volver a sortear.
     *
     * @param  array<int, array{date: string, number: int}>  $draws
     */
    public static function retiredInDrawsWarning(array $draws): ?string
    {
        if ($draws === []) {
            return null;
        }

        $days = array_map(fn (array $d) => 'del '.DrawBlock::dayLabel($d['date']).' (nº '.$d['number'].')', $draws);
        $list = count($days) > 1 ? implode(', ', array_slice($days, 0, -1)).' y '.end($days) : $days[0];

        return count($draws) > 1
            ? "Ya está en los sorteos {$list}. Si no va a trabajar, el director tiene que anularlos (empezando por el último) y volver a sortear."
            : "Ya está en el sorteo {$list}. Si no va a trabajar, el director tiene que anularlo y volver a sortear.";
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

        // Domingo en prueba o apagado: se llega hasta la confirmación, pero no se sortea
        if (Carbon::parse($date)->isSunday() && self::sundayMode() !== self::SUNDAYS_ON) {
            throw ValidationException::withMessages(['date' => self::sundayMode() === self::SUNDAYS_TEST
                ? 'El sorteo de los domingos está en prueba: todavía no se sortea y no se guardó nada.'
                : 'Los domingos no se sortean.']);
        }

        return DB::transaction(function () use ($date, $included, $user) {
            // FOR UPDATE: dos sorteos simultáneos no calculan sobre el mismo historial
            $lastDate = Draw::active()->lockForUpdate()->max('date');
            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : null;

            $today = Carbon::today();
            $drawn = Draw::active()->where('date', '>=', $today->toDateString())->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
            if (! in_array($date, self::drawableDates($lastDate, $today, self::holidaysAround($today), $drawn), true)) {
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

            // Franjas, desvíos y números del mes salen de la misma serie que $date (el domingo va aparte)
            $assigned = $this->assign($previousBands, $this->pastDeviations($participantIds, $date), $this->usedNumbersInMonth($participantIds, $date));

            $draw = Draw::create([
                'code' => Draw::newCode(),
                'date' => $date,
                'active_date' => $date,
                'participants' => count($assigned),
                'deviations' => count(array_filter($assigned, fn (array $a) => $a['deviated'])),
                'repeated_numbers' => count(array_filter($assigned, fn (array $a) => $a['number_repeated'])),
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
     * Registra una sanción por placa (mismo motivo y foto). Cada vez que una
     * placa junta DrawSanction::SANCTIONS_TO_SUSPEND sanciones sin suspensión,
     * se suspende sola los días de suspensionDates() (un bloqueo de tipo
     * suspensión, como cualquier otro: candado, lista e historial) y esas
     * sanciones quedan ligadas a él; la cuenta vuelve a empezar.
     *
     * @param  array<int, int|string>  $vehicleIds
     * @return array{sanctions: Collection<int, DrawSanction>, suspensions: Collection<int, DrawBlock>}
     */
    public function sanction(array $vehicleIds, string $reason, ?string $photoPath, User $user): array
    {
        return DB::transaction(function () use ($vehicleIds, $reason, $photoPath, $user) {
            // FOR UPDATE: dos sanciones a la vez a la misma placa no generan dos suspensiones
            $vehicles = Vehicle::whereIn('id', array_map('intval', $vehicleIds))->orderBy('plate')->lockForUpdate()->get(['id', 'plate']);

            $lastDate = Draw::active()->max('date');
            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : null;
            $today = Carbon::today();
            $holidays = Holiday::datesBetween($today->toDateString(), $today->copy()->addDays(self::SUSPENSION_LOOKAHEAD_DAYS + self::HOLIDAY_LOOKAHEAD_DAYS)->toDateString());

            $sanctions = new Collection;
            $suspensions = new Collection;
            foreach ($vehicles as $vehicle) {
                $sanctions->push(DrawSanction::create([
                    'vehicle_id' => $vehicle->id,
                    'reason' => $reason,
                    'photo_path' => $photoPath,
                    'created_by' => $user->id,
                ]));

                $pending = DrawSanction::pending()->where('vehicle_id', $vehicle->id)->orderBy('id')->get();
                if ($pending->count() < DrawSanction::SANCTIONS_TO_SUSPEND) {
                    continue;
                }
                $grouped = $pending->take(DrawSanction::SANCTIONS_TO_SUSPEND);
                // Sus días libres: los que ya tiene bloqueados (o suspendidos) no cuentan, la suspensión se suma
                $blocks = DrawBlock::active()->where('vehicle_id', $vehicle->id)->with('exceptions')->get();
                $suspensionDates = self::suspensionDates($today, $lastDate, $holidays,
                    alreadyBlocked: fn (string $date) => $blocks->contains(fn (DrawBlock $b) => $b->appliesOn($date)));
                $block = DrawBlock::create([
                    'vehicle_id' => $vehicle->id,
                    'kind' => DrawBlock::KIND_SUSPENSION,
                    'reason' => Str::limit('Suspensión por '.$grouped->count().' sanciones: '.$grouped->pluck('reason')->implode(' / '), 252),
                    'starts_on' => $suspensionDates[0],
                    'ends_on' => end($suspensionDates),
                    'dates' => $suspensionDates,
                    'blocked_by' => $user->id,
                ]);
                $grouped->each(fn (DrawSanction $sanction) => $sanction->update(['draw_block_id' => $block->id]));
                $suspensions->push($block->setRelation('vehicle', $vehicle));
            }

            return ['sanctions' => $sanctions, 'suspensions' => $suspensions];
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
     * Franja del último sorteo vigente anterior a $date, de su misma serie
     * (seriesEntries), en que participó cada vehículo.
     *
     * @param  array<int, int>  $vehicleIds
     * @return array<int, string> vehicle_id => franja
     */
    public function previousBands(array $vehicleIds, string $date): array
    {
        $last = $this->seriesEntries($date)
            ->whereIn('e.vehicle_id', $vehicleIds)
            ->where('d.date', '<', $date)
            ->groupBy('e.vehicle_id')
            ->select('e.vehicle_id', DB::raw('MAX(d.date) as last_date'));

        return $this->seriesEntries($date)
            ->joinSub($last, 'l', fn ($j) => $j->on('l.vehicle_id', '=', 'e.vehicle_id')->on('l.last_date', '=', 'd.date'))
            ->pluck('e.band', 'e.vehicle_id')
            ->map(fn ($band) => (string) $band)
            ->all();
    }

    /**
     * Números entregados en sorteos vigentes de la misma serie que $date. Hay
     * dos series que no se mezclan: lunes a sábado y domingos. El domingo es un
     * sorteo aparte con pocas placas; si contara, casi nadie seguiría su ciclo
     * ni podría evitar repetir número, y jugar el domingo adelantaría el ciclo.
     */
    private function seriesEntries(string $date): QueryBuilder
    {
        return DB::table('draw_entries as e')
            ->join('draws as d', 'd.id', '=', 'e.draw_id')
            ->whereNull('d.annulled_at')
            ->whereNotNull('e.number')
            ->whereRaw(self::seriesSql($date)); // DAYOFWEEK de MySQL: 1 = domingo
    }

    /** Condición SQL sobre d.date: domingos si $date es domingo; si no, lunes a sábado. */
    private static function seriesSql(string $date): string
    {
        return 'DAYOFWEEK(d.date) '.(Carbon::parse($date)->isSunday() ? '=' : '<>').' 1';
    }

    /**
     * Números que cada vehículo ya recibió en otros sorteos vigentes del mismo
     * mes y de la misma serie (seriesEntries): no se le pueden repetir.
     *
     * @param  array<int, int>  $vehicleIds
     * @return array<int, array<int, int>> vehicle_id => números
     */
    public function usedNumbersInMonth(array $vehicleIds, string $date): array
    {
        $used = [];
        $this->seriesEntries($date)
            ->whereIn('e.vehicle_id', $vehicleIds)
            ->whereBetween('d.date', [Carbon::parse($date)->startOfMonth()->toDateString(), Carbon::parse($date)->endOfMonth()->toDateString()])
            ->where('d.date', '!=', $date)
            ->get(['e.vehicle_id', 'e.number'])
            ->each(function ($row) use (&$used) {
                $used[$row->vehicle_id][] = (int) $row->number;
            });

        return $used;
    }

    /**
     * Placas que en este sorteo repitieron un número que ya habían tenido en
     * el mes, en su misma serie (fuerza mayor: pocos participantes).
     *
     * @return array<int, string>
     */
    public function repeatedPlates(Draw $draw): array
    {
        $date = $draw->date->toDateString();

        return DB::table('draw_entries as e')
            ->join('draw_entries as p', fn ($j) => $j->on('p.vehicle_id', '=', 'e.vehicle_id')->on('p.number', '=', 'e.number'))
            ->join('draws as d', 'd.id', '=', 'p.draw_id')
            ->where('e.draw_id', $draw->id)
            ->whereNotNull('e.number')
            ->where('d.id', '!=', $draw->id)
            ->whereNull('d.annulled_at')
            ->whereRaw(self::seriesSql($date))
            ->whereBetween('d.date', [Carbon::parse($date)->startOfMonth()->toDateString(), Carbon::parse($date)->endOfMonth()->toDateString()])
            ->orderBy('e.plate')
            ->distinct()
            ->pluck('e.plate')
            ->all();
    }

    /**
     * Desvíos acumulados de cada vehículo en sorteos vigentes de la misma serie que $date.
     *
     * @param  array<int, int>  $vehicleIds
     * @return array<int, int>
     */
    public function pastDeviations(array $vehicleIds, string $date): array
    {
        return $this->seriesEntries($date)
            ->where('e.deviated', true)
            ->whereIn('e.vehicle_id', $vehicleIds)
            ->groupBy('e.vehicle_id')
            ->selectRaw('e.vehicle_id, COUNT(*) as total')
            ->pluck('total', 'e.vehicle_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
