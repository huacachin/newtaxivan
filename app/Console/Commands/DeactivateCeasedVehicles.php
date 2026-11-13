<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeactivateCeasedVehicles extends Command
{
    protected $signature = 'vehicles:deactivate-ceased';

    protected $description = 'Marca como inactive los vehículos cuya fecha de cese (termination_date) es hoy o ya pasó';

    public function handle(): int
    {
        $today = now(config('app.timezone', 'America/Lima'))->toDateString();

        // Desde su fecha de baja (ese día incluido) ya no está activo: una baja
        // programada aplica a las 00:05 de ese día (Vehicle::statusForTermination)
        $affected = DB::table('vehicles')
            ->where('status', 'active')
            ->whereNotNull('termination_date')
            ->where('termination_date', '<=', $today)
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        Log::info("Vehículos desactivados: {$affected}");
        $this->info("Vehículos desactivados: {$affected}");

        return self::SUCCESS;
    }
}
