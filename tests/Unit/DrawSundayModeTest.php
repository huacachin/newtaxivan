<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DrawService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * Sorteo de los domingos en prueba o apagado: se llega hasta la confirmación,
 * pero run lo rechaza antes de abrir la transacción (no toca la base).
 */
class DrawSundayModeTest extends TestCase
{
    private function runSunday(): void
    {
        (new DrawService)->run('2026-11-15', [1, 2, 3], new User);
    }

    public function test_a_sunday_in_test_mode_is_rejected_without_touching_the_database(): void
    {
        config(['draws.sundays' => DrawService::SUNDAYS_TEST]);
        DB::shouldReceive('transaction')->never();

        try {
            $this->runSunday();
            $this->fail('El domingo en prueba no debería sortearse.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('está en prueba', $e->errors()['date'][0]);
        }
    }

    public function test_a_sunday_is_rejected_when_the_mode_is_off_or_invalid(): void
    {
        DB::shouldReceive('transaction')->never();

        foreach ([DrawService::SUNDAYS_OFF, 'cualquier cosa', null] as $mode) {
            config(['draws.sundays' => $mode]);
            $this->assertSame(DrawService::SUNDAYS_OFF, DrawService::sundayMode());

            try {
                $this->runSunday();
                $this->fail('Con los domingos apagados no debería sortearse.');
            } catch (ValidationException $e) {
                $this->assertSame('Los domingos no se sortean.', $e->errors()['date'][0]);
            }
        }
    }

    public function test_a_sunday_goes_on_to_the_draw_when_the_mode_is_on(): void
    {
        config(['draws.sundays' => DrawService::SUNDAYS_ON]);
        DB::shouldReceive('transaction')->once()->andThrow(new RuntimeException('llegó al sorteo'));

        $this->expectExceptionMessage('llegó al sorteo');
        $this->runSunday();
    }

    public function test_the_calendar_shows_every_sunday_of_the_month_even_without_a_draw(): void
    {
        $working = [
            ['date' => '2026-11-07', 'n' => 7, 'weekday' => 'Sáb'],
            ['date' => '2026-11-09', 'n' => 9, 'weekday' => 'Lun'],
        ];

        $days = DrawService::calendarDays($working, '2026-11-01', '2026-11-30', ['2026-11-01']);

        $this->assertSame(['2026-11-07', '2026-11-08', '2026-11-09', '2026-11-15', '2026-11-22', '2026-11-29'], array_column($days, 'date'));
        $this->assertSame(['n' => 8, 'weekday' => 'Dom', 'sunday' => true], array_diff_key($days[1], ['date' => 1]));
        $this->assertArrayNotHasKey('sunday', $days[0]);

        // Un domingo feriado no sale, salvo que ya tenga sorteo
        $kept = DrawService::calendarDays([], '2026-11-01', '2026-11-08', ['2026-11-01'], ['2026-11-01']);
        $this->assertSame(['2026-11-01', '2026-11-08'], array_column($kept, 'date'));
    }
}
