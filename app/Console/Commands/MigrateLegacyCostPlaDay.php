<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MigrateLegacyCostPlaDay extends Command
{
    protected $signature = 'migrate:costpla-day
        {--dry : Simulación, no escribe}
        {--chunk=1000 : Tamaño del chunk}
        {--limit=0 : Límite total (0=todos)}
        {--source=huaca_costpla_dia : Tabla legacy origen}
        {--target=cost_per_plate_days : Tabla destino}
        {--vehicles=vehicles : Tabla de vehículos}
        {--insert-mode=upsert : upsert|insert (upsert actualiza si (vehicle_id,date) existe)}';

    protected $description = 'Migra huaca_costpla_dia => cost_per_plate_day en la misma BD, omitiendo SOLO filas con fecha inválida y creando vehicles inactivos si faltan.';

    public function handle(): int
    {
        $source     = $this->option('source');
        $target     = $this->option('target');     // usa --target=cost_per_plate_days si tu tabla es plural
        $vehicles   = $this->option('vehicles');
        $dry        = (bool)$this->option('dry');
        $chunk      = (int)$this->option('chunk');
        $limit      = (int)$this->option('limit');
        $insertMode = $this->option('insert-mode'); // upsert|insert

        // Conteo total
        $countQ = DB::table($source);
        $total = (clone $countQ)->count();
        if ($limit > 0 && $limit < $total) $total = $limit;

        $this->info("Origen: {$source} -> Destino: {$target}  (vehicles: {$vehicles})");
        $this->info("Total estimado: {$total}" . ($dry ? " (DRY RUN)" : ""));
        $this->info("insert-mode={$insertMode}");

        $processed = $inserted = $updated = $skipped = $createdVehicles = 0;

        $base = DB::table($source)->orderBy('id');
        if ($limit > 0) $base->limit($limit);

        $base->chunk($chunk, function ($rows) use (
            $vehicles, $target, $dry, $insertMode,
            &$processed, &$inserted, &$updated, &$skipped, &$createdVehicles
        ) {
            // Cache de vehicle_id por placa dentro del chunk
            $vehicleCache = [];

            DB::beginTransaction();
            try {
                foreach ($rows as $r) {
                    $processed++;

                    // 1) Normalizar placa (requerida para mapear vehicle_id)
                    $plate = strtoupper(trim((string)($r->placa ?? '')));
                    if ($plate === '') {
                        $skipped++;
                        Log::warning('Saltado: placa vacía', ['legacy_id' => $r->id ?? null]);
                        continue;
                    }

                    // 2) Fecha: omitir SOLO si es inválida
                    $dateRaw = (string)($r->fecha ?? '');
                    $date = self::toDate($dateRaw); // YYYY-MM-DD o null

                    // Casos inválidos explícitos
                    if ($date === '0000-00-00' || !$date) {
                        $skipped++;
                        Log::warning('Saltado: fecha inválida', ['legacy_id' => $r->id ?? null, 'placa' => $plate, 'fecha' => $r->fecha ?? null]);
                        continue;
                    }

                    // Validar con Carbon y rango de año/mes
                    try {
                        $dt = Carbon::parse($date);
                    } catch (\Throwable $e) {
                        $skipped++;
                        Log::warning('Saltado: Carbon parse falló', ['legacy_id' => $r->id ?? null, 'placa' => $plate, 'fecha' => $dateRaw]);
                        continue;
                    }

                    if ($dt->year <= 0 || $dt->year < 1980 || $dt->year > 2100) {
                        $skipped++;
                        Log::warning('Saltado: año fuera de rango', ['legacy_id' => $r->id ?? null, 'placa' => $plate, 'fecha' => $date]);
                        continue;
                    }

                    $year  = (int)$dt->year;
                    $month = (int)$dt->month;
                    if ($month < 1 || $month > 12) {
                        $skipped++;
                        Log::warning('Saltado: mes fuera de rango', ['legacy_id' => $r->id ?? null, 'placa' => $plate, 'fecha' => $date]);
                        continue;
                    }

                    // 3) Monto (normaliza "1.234,56" o "1234,56")
                    $amount = self::parseAmount($r->monto ?? 0);
                    if (!is_finite($amount)) $amount = 0.0;
                    if ($amount < 0) {
                        // Si necesitas permitir negativos, quita este clamp o el CHECK del esquema
                        Log::warning('Monto negativo ajustado a 0', ['legacy_id' => $r->id ?? null, 'placa' => $plate, 'monto' => $r->monto ?? null]);
                        $amount = 0.0;
                    }

                    // 4) Resolver/crear vehicle_id
                    $vehicleId = $vehicleCache[$plate] ?? null;
                    if (!$vehicleId) {
                        $vehicleId = DB::table($vehicles)->where('plate', $plate)->value('id');
                        if (!$vehicleId) {
                            if ($dry) {
                                // Solo contamos que crearíamos
                                $createdVehicles++;
                                // id temporal negativo solo para simulación de flujo
                                $vehicleId = -crc32($plate);
                            } else {
                                $vehicleId = DB::table($vehicles)->insertGetId([
                                    'plate'      => $plate,
                                    'status'     => 'inactive',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                                $createdVehicles++;
                            }
                        }
                        $vehicleCache[$plate] = $vehicleId;
                    }

                    // 5) Insertar
                    if ($dry) {
                        if ($insertMode === 'upsert' && $vehicleId > 0) {
                            $exists = DB::table($target)->where([
                                ['vehicle_id', '=', $vehicleId],
                                ['date', '=', $date],
                            ])->exists();
                            $exists ? $updated++ : $inserted++;
                        } else {
                            // insert directo (permite duplicados por día)
                            $inserted++;
                        }
                        continue;
                    }

                    if ($insertMode === 'insert') {
                        // Permite múltiples filas por (vehicle_id, date)
                        DB::table($target)->insert([
                            'vehicle_id' => $vehicleId,
                            'year'       => $year,
                            'month'      => $month,
                            'date'       => $date,
                            'amount'     => $amount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $inserted++;
                    } else {
                        // upsert: si existe (vehicle_id,date) actualiza; si no, inserta
                        $exists = DB::table($target)->where([
                            ['vehicle_id', '=', $vehicleId],
                            ['date', '=', $date],
                        ])->exists();

                        if ($exists) {
                            DB::table($target)->where([
                                ['vehicle_id', '=', $vehicleId],
                                ['date', '=', $date],
                            ])->update([
                                'year'       => $year,
                                'month'      => $month,
                                'amount'     => $amount,
                                'updated_at' => now(),
                            ]);
                            $updated++;
                        } else {
                            DB::table($target)->insert([
                                'vehicle_id' => $vehicleId,
                                'year'       => $year,
                                'month'      => $month,
                                'date'       => $date,
                                'amount'     => $amount,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $inserted++;
                        }
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $this->line("Progreso: {$processed} filas | +{$inserted} ins | ~{$updated} upd | veh+{$createdVehicles} | skip={$skipped}");
        });

        $this->newLine();
        $this->info("==== Resumen ====");
        $this->info("Procesadas    : {$processed}");
        $this->info("Insertadas    : {$inserted}");
        $this->info("Actualizadas  : {$updated}");
        $this->info("Saltadas      : {$skipped}");
        $this->info("Veh. creados  : {$createdVehicles}");

        return self::SUCCESS;
    }

    /** Normaliza un monto que puede venir con separadores locales a float. */
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

    /** Intenta parsear fecha; devuelve YYYY-MM-DD o null si no se pudo. */
    private static function toDate(?string $s): ?string
    {
        $s = trim((string)$s);
        if ($s === '' || $s === '0000-00-00') return null;

        // Intentos comunes
        $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y', 'd.m.Y', 'Y.m.d'];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $s);
                if ($dt !== false) return $dt->toDateString();
            } catch (\Throwable $e) {}
        }

        // Parse liberal
        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
