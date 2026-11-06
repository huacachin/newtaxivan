<?php

namespace Tests\Unit;

use App\Models\DrawEntry;
use App\Models\Holiday;
use App\Services\DrawService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class DrawServiceTest extends TestCase
{
    private const LOW = DrawEntry::BAND_LOW;

    private const MID = DrawEntry::BAND_MID;

    private const HIGH = DrawEntry::BAND_HIGH;

    private function service(int $seed = 1): DrawService
    {
        return new DrawService(new Randomizer(new Mt19937($seed)));
    }

    /**
     * vehicle_id => franja anterior, numerando desde 1 en el orden dado.
     *
     * @param  array<string, int>  $groups  franja (o 'none') => cantidad
     * @return array<int, string|null>
     */
    private function previousBands(array $groups): array
    {
        $bands = [];
        $id = 1;
        foreach ($groups as $band => $count) {
            for ($i = 0; $i < $count; $i++) {
                $bands[$id++] = $band === 'none' ? null : $band;
            }
        }

        return $bands;
    }

    /**
     * @param  array<int, array{number: int, band: string, previous_band: string|null, deviated: bool}>  $result
     */
    private function assertValidDraw(array $result, int $total): void
    {
        $numbers = array_column($result, 'number');
        sort($numbers);
        $this->assertSame(range(1, $total), $numbers, 'Cada número de 1 a N sale una sola vez');

        foreach ($result as $entry) {
            [$from, $to] = DrawService::bandRanges($total)[$entry['band']];
            $this->assertGreaterThanOrEqual($from, $entry['number']);
            $this->assertLessThanOrEqual($to, $entry['number']);
        }
    }

    private function countDeviations(array $result): int
    {
        return count(array_filter($result, fn (array $e) => $e['deviated']));
    }

    private function countRepeats(array $result): int
    {
        return count(array_filter($result, fn (array $e) => $e['band'] === $e['previous_band']));
    }

    public function test_band_sizes_split_in_thirds_with_the_remainder_to_low_then_mid(): void
    {
        $this->assertSame([self::LOW => 15, self::MID => 15, self::HIGH => 15], DrawService::bandSizes(45));
        $this->assertSame([self::LOW => 15, self::MID => 15, self::HIGH => 14], DrawService::bandSizes(44));
        $this->assertSame([self::LOW => 15, self::MID => 14, self::HIGH => 14], DrawService::bandSizes(43));
        $this->assertSame([self::LOW => 1, self::MID => 16, self::HIGH => 31], array_map(fn ($r) => $r[0], DrawService::bandRanges(45)));
    }

    public function test_first_draw_without_history_assigns_every_number_once(): void
    {
        $result = $this->service()->assign($this->previousBands(['none' => 45]));

        $this->assertCount(45, $result);
        $this->assertValidDraw($result, 45);
        $this->assertSame(0, $this->countDeviations($result));
    }

    public function test_balanced_history_follows_the_cycle_low_mid_high(): void
    {
        $previous = $this->previousBands([self::LOW => 15, self::MID => 15, self::HIGH => 15]);

        $result = $this->service()->assign($previous);

        $this->assertValidDraw($result, 45);
        foreach ($result as $vehicleId => $entry) {
            $this->assertSame(DrawEntry::nextBand($previous[$vehicleId]), $entry['band']);
        }
        $this->assertSame(0, $this->countDeviations($result));
    }

    public function test_when_groups_do_not_fit_nobody_repeats_and_deviations_are_minimal(): void
    {
        // 45 vehículos, hoy se quitan 3 que ayer tuvieron número bajo: franjas de 14
        $previous = $this->previousBands([self::LOW => 12, self::MID => 15, self::HIGH => 15]);

        foreach ([1, 2, 3, 4, 5] as $seed) {
            $result = $this->service($seed)->assign($previous);

            $this->assertValidDraw($result, 42);
            $this->assertSame(0, $this->countRepeats($result));
            $this->assertSame(3, $this->countDeviations($result));
        }
    }

    public function test_total_not_divisible_by_three_deviates_a_single_vehicle(): void
    {
        $previous = $this->previousBands([self::LOW => 15, self::MID => 15, self::HIGH => 14]);

        $result = $this->service()->assign($previous);

        $this->assertValidDraw($result, 44);
        $this->assertSame(0, $this->countRepeats($result));
        $this->assertSame(1, $this->countDeviations($result));
    }

    public function test_vehicles_without_history_fill_the_gaps(): void
    {
        $previous = $this->previousBands([self::LOW => 10, self::MID => 12, self::HIGH => 8, 'none' => 15]);

        $result = $this->service()->assign($previous);

        $this->assertValidDraw($result, 45);
        $this->assertSame(0, $this->countDeviations($result));
    }

    public function test_the_deviation_falls_on_whoever_has_fewer_past_deviations(): void
    {
        // 15/15/14: exactamente uno de los que venían de "medio" no cabe en "alto"
        $previous = $this->previousBands([self::LOW => 15, self::MID => 15, self::HIGH => 14]);
        $midIds = array_keys(array_filter($previous, fn ($b) => $b === self::MID));
        $pastDeviations = array_fill_keys($midIds, 4);
        $freshId = $midIds[7];
        $pastDeviations[$freshId] = 0;

        foreach ([1, 2, 3, 4, 5] as $seed) {
            $result = $this->service($seed)->assign($previous, $pastDeviations);

            $deviated = array_keys(array_filter($result, fn (array $e) => $e['deviated']));
            $this->assertSame([$freshId], $deviated);
            $this->assertSame(self::LOW, $result[$freshId]['band']);
        }
    }

    public function test_repeats_only_when_there_is_no_other_way_and_marks_it(): void
    {
        // Los 3 venían de "bajo": franjas de 1 número, alguien tiene que repetir
        $result = $this->service()->assign($this->previousBands([self::LOW => 3]));

        $this->assertValidDraw($result, 3);
        $this->assertSame(1, $this->countRepeats($result));
        $this->assertSame(2, $this->countDeviations($result));
    }

    public function test_a_month_of_draws_with_random_exclusions_never_repeats_a_band(): void
    {
        $service = $this->service(7);
        $picker = new Randomizer(new Mt19937(99));
        $vehicleIds = range(1, 45);
        $lastBand = array_fill_keys($vehicleIds, null);
        $deviations = array_fill_keys($vehicleIds, 0);

        for ($day = 0; $day < 26; $day++) {
            $excluded = $picker->pickArrayKeys(array_flip($vehicleIds), $picker->getInt(1, 6));
            $participants = array_diff($vehicleIds, $excluded);
            $previous = array_intersect_key($lastBand, array_flip($participants));

            $result = $service->assign($previous, $deviations);

            $this->assertValidDraw($result, count($participants));
            $this->assertSame(0, $this->countRepeats($result), "Día {$day}: nadie repite franja");
            foreach ($result as $vehicleId => $entry) {
                $lastBand[$vehicleId] = $entry['band'];
                $deviations[$vehicleId] += $entry['deviated'] ? 1 : 0;
            }
        }
    }

    public function test_simulation_draws_between_min_and_max_and_unchecks_the_rest(): void
    {
        $vehicleIds = range(1, 43);
        $dates = [];
        for ($d = Carbon::parse('2026-10-01'); $d->lte(Carbon::parse('2026-10-31')); $d->addDay()) {
            if (DrawService::isWorkingDay($d, ['2026-10-08'])) {
                $dates[] = $d->toDateString();
            }
        }
        $eligible = array_fill_keys($dates, $vehicleIds);

        $result = $this->service(11)->simulate($dates, $eligible, [], 28, 38);

        $this->assertSame($dates, array_keys($result));
        $lastBand = [];
        $monthNumbers = [];
        foreach ($result as $date => $day) {
            $this->assertCount(43, $day, "{$date}: todos los habilitados quedan registrados");
            $drawn = array_filter($day, fn (array $e) => $e['number'] !== null);
            $this->assertGreaterThanOrEqual(28, count($drawn), $date);
            $this->assertLessThanOrEqual(38, count($drawn), $date);
            $this->assertValidDraw($drawn, count($drawn));
            foreach ($drawn as $vehicleId => $entry) {
                $this->assertSame($lastBand[$vehicleId] ?? null, $entry['previous_band'], "{$date}: parte de su último sorteo");
                $this->assertNotSame($entry['previous_band'], $entry['band'], "{$date}: nadie repite franja");
                $this->assertNotContains($entry['number'], $monthNumbers[$vehicleId] ?? [], "{$date}: nadie repite número en el mes");
                $this->assertFalse($entry['number_repeated']);
                $lastBand[$vehicleId] = $entry['band'];
                $monthNumbers[$vehicleId][] = $entry['number'];
            }
        }
    }

    public function test_a_number_already_used_this_month_is_not_repeated(): void
    {
        // Vehículo 1 ya tuvo 1..4 este mes y viene de "alto": solo le queda el 5 en "bajo"
        $previous = $this->previousBands([self::HIGH => 1, 'none' => 14]);
        $used = [1 => [1, 2, 3, 4]];

        foreach ([1, 2, 3, 4, 5] as $seed) {
            $result = $this->service($seed)->assign($previous, [], $used);

            $this->assertValidDraw($result, 15);
            $this->assertSame(5, $result[1]['number']);
            $this->assertFalse($result[1]['number_repeated']);
        }
    }

    public function test_red_not_working_vehicles_do_not_enter_the_draw(): void
    {
        // 60 días o más sin trabajar a la fecha del sorteo = NT en rojo
        $this->assertTrue(DrawService::entersDrawOn(null, '2026-10-30'));
        $this->assertTrue(DrawService::entersDrawOn('2026-09-01', '2026-10-30'));  // 59 días
        $this->assertFalse(DrawService::entersDrawOn('2026-08-31', '2026-10-30')); // 60 días
        $this->assertFalse(DrawService::entersDrawOn('2026-07-10', '2026-10-30'));
    }

    public function test_blocks_start_on_the_first_day_not_drawn_yet(): void
    {
        $friday = Carbon::parse('2026-11-06 10:00');

        $this->assertSame('2026-11-06', DrawService::firstOpenDate('2026-11-05', $friday, []));  // hoy sin sortear
        $this->assertSame('2026-11-07', DrawService::firstOpenDate('2026-11-06', $friday, []));  // hoy ya sorteado
        $this->assertSame('2026-11-09', DrawService::firstOpenDate('2026-11-07', $friday, []));  // sábado adelantado
        $this->assertSame('2026-11-09', DrawService::firstOpenDate('2026-11-06', $friday, ['2026-11-07'])); // sábado feriado
        $this->assertSame('2026-11-09', DrawService::firstOpenDate('2026-11-07', Carbon::parse('2026-11-08'), [])); // domingo
    }

    public function test_working_days_are_monday_to_saturday_except_holidays(): void
    {
        $holidays = ['2026-12-08'];

        $this->assertTrue(DrawService::isWorkingDay(Carbon::parse('2026-10-31'), $holidays));  // sábado
        $this->assertFalse(DrawService::isWorkingDay(Carbon::parse('2026-11-01'), $holidays)); // domingo
        $this->assertFalse(DrawService::isWorkingDay(Carbon::parse('2026-12-08'), $holidays)); // feriado
        $this->assertSame('2026-12-10', DrawService::nextWorkingDay(Carbon::parse('2026-12-07'), ['2026-12-08', '2026-12-09'])->toDateString());
    }

    public function test_drawable_dates_are_today_and_the_next_working_day_after_the_last_draw(): void
    {
        $saturday = Carbon::parse('2026-10-31');

        $this->assertSame(['2026-10-31', '2026-11-02'], DrawService::drawableDates('2026-10-30', $saturday, []));
        $this->assertSame(['2026-11-02'], DrawService::drawableDates('2026-10-31', $saturday, []));
        $this->assertSame([], DrawService::drawableDates('2026-11-02', $saturday, []));
        $this->assertSame(['2026-11-02'], DrawService::drawableDates(null, Carbon::parse('2026-11-01'), []));
        // Día pasado sin sorteo: se salta, solo cuentan hoy y el siguiente
        $this->assertSame(['2026-10-31', '2026-11-02'], DrawService::drawableDates('2026-10-20', $saturday, []));
        // Jueves y Viernes Santo seguidos
        $this->assertSame(['2026-04-04'], DrawService::drawableDates(null, Carbon::parse('2026-04-02'), ['2026-04-02', '2026-04-03']));
    }

    public function test_order_shows_only_the_drivers_first_name(): void
    {
        $this->assertSame('Juan', DrawService::firstName('Perez Lopez Juan Carlos'));
        $this->assertSame('Pedro', DrawService::firstName('Quispe Mamani Pedro'));
        $this->assertSame('Luis', DrawService::firstName('De La Cruz Vidal Luis Alberto'));
        $this->assertSame('Ana', DrawService::firstName('Del Castillo  Rojas  Ana Maria'));
        $this->assertSame('Maria', DrawService::firstName('Rojas Torres Maria Del Pilar'));
        $this->assertSame('Juan', DrawService::firstName('Perez Juan'));
        $this->assertSame('Juan', DrawService::firstName('Juan'));
        $this->assertSame('', DrawService::firstName(null));

        $items = DrawService::orderItems([['number' => 1, 'plate' => 'ABC123', 'driver_name' => 'Perez Lopez Juan Carlos']]);
        $this->assertSame('Juan', $items[0]['driver']);
    }

    public function test_national_holidays_include_holy_thursday_and_friday(): void
    {
        $this->assertSame('2026-04-05', Holiday::easterSunday(2026)->toDateString());
        $this->assertSame('2027-03-28', Holiday::easterSunday(2027)->toDateString());

        $holidays = Holiday::nationalFor(2026);
        $this->assertCount(16, $holidays);
        $this->assertSame('Jueves Santo', $holidays['2026-04-02']);
        $this->assertSame('Viernes Santo', $holidays['2026-04-03']);
        $this->assertSame('Fiestas Patrias', $holidays['2026-07-28']);
    }
}
