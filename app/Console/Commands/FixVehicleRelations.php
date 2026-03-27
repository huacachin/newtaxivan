<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixVehicleRelations extends Command
{
    protected $signature = 'taxivan:fix-vehicle-relations
        {--source=huaca_taxi_vehiculos : Tabla legacy de origen}
        {--dry-run : Simular sin escribir}';

    protected $description = 'Corrige driver_id y owner_id en vehicles que quedaron null por orden de migración';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $dryRun = $this->option('dry-run');

        if (!Schema::hasTable($source)) {
            $this->error("No existe la tabla legacy: {$source}");
            return self::FAILURE;
        }

        // Vehículos que tienen driver_id o owner_id null
        $vehiclesWithNulls = DB::table('vehicles')
            ->where(function ($q) {
                $q->whereNull('driver_id')->orWhereNull('owner_id');
            })
            ->pluck('id')
            ->all();

        if (empty($vehiclesWithNulls)) {
            $this->info('No hay vehículos con driver_id/owner_id null. Nada que corregir.');
            return self::SUCCESS;
        }

        $this->info(count($vehiclesWithNulls) . ' vehículos con relaciones faltantes.' . ($dryRun ? ' (dry-run)' : ''));

        // Drivers y owners existentes
        $existingDrivers = DB::table('drivers')->pluck('id')->flip()->all();
        $existingOwners  = DB::table('owners')->pluck('id')->flip()->all();

        // Leer datos legacy solo de los vehículos afectados
        $legacyRows = DB::table($source)
            ->whereIn('id', $vehiclesWithNulls)
            ->get(['id', 'conductor', 'propietario']);

        $fixedDrivers = 0;
        $fixedOwners  = 0;
        $skipped      = 0;

        $bar = $this->output->createProgressBar($legacyRows->count());
        $bar->start();

        foreach ($legacyRows as $row) {
            $update = [];

            // Fix driver_id
            $legacyDriverId = $this->toInt($row->conductor ?? null);
            if ($legacyDriverId && isset($existingDrivers[$legacyDriverId])) {
                $current = DB::table('vehicles')->where('id', $row->id)->value('driver_id');
                if ($current === null) {
                    $update['driver_id'] = $legacyDriverId;
                    $fixedDrivers++;
                }
            }

            // Fix owner_id
            $legacyOwnerId = $this->toInt($row->propietario ?? null);
            if ($legacyOwnerId && isset($existingOwners[$legacyOwnerId])) {
                $current = DB::table('vehicles')->where('id', $row->id)->value('owner_id');
                if ($current === null) {
                    $update['owner_id'] = $legacyOwnerId;
                    $fixedOwners++;
                }
            }

            if (!empty($update) && !$dryRun) {
                $update['updated_at'] = now();
                DB::table('vehicles')->where('id', $row->id)->update($update);
            } elseif (empty($update)) {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Drivers corregidos:  {$fixedDrivers}");
        $this->info("Owners corregidos:   {$fixedOwners}");
        $this->line("Sin cambios:         {$skipped}");

        if ($dryRun) {
            $this->warn('(dry-run) No se escribió nada.');
        }

        return self::SUCCESS;
    }

    private function toInt($v): ?int
    {
        if ($v === null) return null;
        $s = preg_replace('/\D+/', '', (string) $v);
        return $s !== '' ? (int) $s : null;
    }
}
