<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateLegacyCostPla extends Command
{
    protected $signature = 'migrate:costpla
        {--dry : Simulación, no escribe}
        {--chunk=1000 : Tamaño del chunk}
        {--limit=0 : Limitar total de filas (0=todas)}
        {--source=huaca_costpla : Tabla legacy origen}
        {--target=cost_per_plates : Tabla destino nueva}
        {--vehicles=vehicles : Tabla vehicles}';

    protected $description = 'Migra huaca_costpla => cost_per_plates en la misma BD, creando vehicles inactivos si no existen.';

    public function handle(): int
    {
        $source   = $this->option('source');
        $target   = $this->option('target');
        $vehicles = $this->option('vehicles');
        $chunk    = (int)$this->option('chunk');
        $limit    = (int)$this->option('limit');
        $dry      = (bool)$this->option('dry');

        // Conteo
        $countQ = DB::table($source);
        $total = (clone $countQ)->count();
        if ($limit > 0 && $limit < $total) $total = $limit;

        $this->info("Origen: {$source}  ->  Destino: {$target}  (vehicles: {$vehicles})");
        $this->info("Total estimado a procesar: {$total}" . ($dry ? " (DRY RUN)" : ""));

        $processed = $inserted = $updated = $skipped = $createdVehicles = 0;
        $missingPlates = [];

        $base = DB::table($source)->orderBy('id');
        if ($limit > 0) $base->limit($limit);

        $base->chunk($chunk, function ($rows) use (
            $vehicles, $target, $dry,
            &$processed, &$inserted, &$updated, &$skipped, &$createdVehicles, &$missingPlates
        ) {
            // Un pequeño índice de placas ya resueltas en este chunk para menos queries
            $vehicleCache = [];

            // Transacción por chunk (seguro + rápido)
            DB::beginTransaction();
            try {
                foreach ($rows as $r) {
                    $processed++;

                    $plate = strtoupper(trim((string)($r->placa ?? '')));
                    if ($plate === '') {
                        $skipped++;
                        Log::warning('Fila saltada: placa vacía', ['legacy_id' => $r->id ?? null]);
                        continue;
                    }

                    // Parseos/validaciones
                    $year  = self::toIntOrNull($r->ano ?? null);
                    $month = self::toIntOrNull($r->mes ?? null);
                    $amount = self::parseAmount($r->monto ?? 0);
                    $order  = self::toIntOrNull($r->orden ?? null);

                    if (!$year || $month === null || $month < 1 || $month > 12) {
                        $skipped++;
                        Log::warning('Fila inválida (año/mes)', [
                            'legacy_id' => $r->id ?? null, 'plate' => $plate, 'ano' => $r->ano ?? null, 'mes' => $r->mes ?? null
                        ]);
                        continue;
                    }

                    // Resolver vehicle_id con cache por placa
                    $vehicleId = $vehicleCache[$plate] ?? null;

                    if (!$vehicleId) {
                        $vehicleId = DB::table($vehicles)->where('plate', $plate)->value('id');
                        if (!$vehicleId) {
                            $missingPlates[$plate] = true;
                            if ($dry) {
                                // En dry-run no insertamos el vehicle; saltamos este registro porque no hay id
                                $skipped++;
                                continue;
                            }
                            // Crear vehicle inactivo
                            $vehicleId = DB::table($vehicles)->insertGetId([
                                'plate'      => $plate,
                                'status'     => 'inactive',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $createdVehicles++;
                        }
                        $vehicleCache[$plate] = $vehicleId;
                    }

                    // Clave compuesta
                    $key = [
                        'vehicle_id' => $vehicleId,
                        'year'       => (int)$year,
                        'month'      => (int)$month,
                        'order'      => $order !== null ? (int)$order : null,
                    ];

                    if ($dry) {
                        $exists = DB::table($target)->where($key)->exists();
                        $exists ? $updated++ : $inserted++;
                        continue;
                    }

                    // Insert/Update manual para controlar created_at/updated_at
                    $exists = DB::table($target)->where($key)->exists();
                    if ($exists) {
                        DB::table($target)->where($key)->update([
                            'amount'     => $amount,
                            'updated_at' => now(),
                        ]);
                        $updated++;
                    } else {
                        DB::table($target)->insert(array_merge($key, [
                            'amount'     => $amount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]));
                        $inserted++;
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $this->line("Progreso: {$processed} filas...");
        });

        // Resumen
        $this->newLine();
        $this->info("==== Resumen ====");
        $this->info("Procesadas    : {$processed}");
        $this->info("Insertadas    : {$inserted}");
        $this->info("Actualizadas  : {$updated}");
        $this->info("Saltadas      : {$skipped}");
        $this->info("Veh. creados  : {$createdVehicles}");

        if (!empty($missingPlates)) {
            $sample = array_slice(array_keys($missingPlates), 0, 20);
            $this->warn("Placas no encontradas (muestra): " . implode(', ', $sample));
        }

        return self::SUCCESS;
    }

    private static function toIntOrNull($v): ?int
    {
        if (is_numeric($v) && preg_match('/^-?\d+$/', (string)$v)) {
            return (int)$v;
        }
        return null;
    }

    private static function parseAmount($value): float
    {
        $s = trim((string)$value);
        if ($s === '') return 0.0;
        // "1.234,56" -> "1234.56"
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            if (substr_count($s, '.') > 1) $s = str_replace('.', '', $s);
            if (substr_count($s, ',') > 1) $s = str_replace(',', '', $s);
            $s = str_replace(',', '.', $s);
        }
        return (float)$s;
    }
}
