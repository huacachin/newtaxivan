<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Services\DrawService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Fecha de baja = último día que trabaja: ese día sigue activo y desde el
 * siguiente queda de baja (sin tocar la base).
 */
class VehicleTerminationTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_status_follows_the_termination_date_as_the_last_working_day(): void
    {
        $this->assertSame('active', Vehicle::statusForTermination(null, '2026-11-13'));
        $this->assertSame('active', Vehicle::statusForTermination('2026-11-13', '2026-11-13')); // hoy es su último día
        $this->assertSame('active', Vehicle::statusForTermination('2026-11-20', '2026-11-13')); // baja programada
        $this->assertSame('inactive', Vehicle::statusForTermination('2026-11-12', '2026-11-13'));
    }

    public function test_a_vehicle_is_retired_when_inactive_or_after_its_last_day(): void
    {
        Carbon::setTestNow('2026-11-13 10:00');

        $this->assertFalse((new Vehicle(['status' => 'active']))->isRetired());
        $this->assertFalse((new Vehicle(['status' => 'active', 'termination_date' => '2026-11-13']))->isRetired());
        $this->assertFalse((new Vehicle(['status' => 'active', 'termination_date' => '2026-11-20']))->isRetired());
        $this->assertTrue((new Vehicle(['status' => 'active', 'termination_date' => '2026-11-12']))->isRetired()); // antes de la tarea nocturna
        $this->assertTrue((new Vehicle(['status' => 'inactive']))->isRetired());
    }

    public function test_the_warning_lists_the_draws_already_made_after_the_last_day(): void
    {
        $this->assertNull(DrawService::retiredInDrawsWarning([]));

        $this->assertSame(
            'Ya está en el sorteo del Sáb 14/11 (nº 12). Si no va a trabajar, el director tiene que anularlo y volver a sortear.',
            DrawService::retiredInDrawsWarning([['date' => '2026-11-14', 'number' => 12]]),
        );
        $this->assertSame(
            'Ya está en los sorteos del Sáb 14/11 (nº 12) y del Lun 16/11 (nº 3). Si no va a trabajar, el director tiene que anularlos (empezando por el último) y volver a sortear.',
            DrawService::retiredInDrawsWarning([['date' => '2026-11-14', 'number' => 12], ['date' => '2026-11-16', 'number' => 3]]),
        );
    }
}
