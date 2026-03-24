<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeactivateCeasedVehicles extends Command
{
    protected $signature = 'vehicles:deactivate-ceased';

    protected $description = 'Marca como inactive los vehículos cuya fecha de cese (termination_date) ya pasó';

    public function handle(): int
    {
        $today = now(config('app.timezone', 'America/Lima'))->toDateString();

        $affected = DB::table('vehicles')
            ->where('status', 'active')
            ->whereNotNull('termination_date')
            ->where('termination_date', '<', $today)
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        Log::info("Vehículos desactivados: {$affected}");
        $this->info("Vehículos desactivados: {$affected}");

        return self::SUCCESS;
    }
}
