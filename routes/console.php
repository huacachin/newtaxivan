<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('migrate:costos-legacy {--dry}', function () {
    $dry = (bool)$this->option('dry');

    // Refuerzo: crea vehículos faltantes en bloque (idempotente)
    DB::statement("
    INSERT INTO vehicles (plate, status, created_at, updated_at)
    SELECT DISTINCT TRIM(lp.placa), UPPER(TRIM(lp.placa)), 'placeholder', 'legacy_costs', NOW(), NOW()
    FROM huaca_costo_pla lp
    LEFT JOIN vehiculos v
      ON v.placa_norm = UPPER(TRIM(lp.placa))
    WHERE v.id IS NULL
      AND lp.placa IS NOT NULL
      AND TRIM(lp.placa) <> '';
    ");

    $total = DB::table('huaca_costo_pla')->count();
    $this->info("Legacy rows: {$total}");

    $inserted = 0; $skipped = 0;

    DB::table('huaca_costo_pla')
        ->orderBy('id')
        ->chunk(200, function ($chunk) use (&$inserted, &$skipped, $dry) {
            foreach ($chunk as $legacy) {
                $placa = strtoupper(trim($legacy->placa ?? ''));
                if ($placa === '') { $skipped++; continue; }

                $year  = (int)($legacy->ano ?? 0);
                $month = (int)($legacy->mes ?? 0);
                if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) { $skipped++; continue; }

                $amount = is_null($legacy->monto) ? null : (float)$legacy->monto;
                if ($amount === null) { $skipped++; continue; }

                $vehiculoId = DB::table('vehiculos')
                    ->where('placa_norm', $placa)
                    ->value('id');

                if (!$vehiculoId) { $skipped++; continue; }

                if ($dry) {
                    $inserted++; // simulamos
                    continue;
                }

                // Evita duplicados si tienes unique (vehicle_id,year,month)
                DB::table('cost_per_plates')->updateOrInsert(
                    [
                        'vehicle_id' => $vehiculoId,
                        'year'       => $year,
                        'month'      => $month,
                    ],
                    [
                        'amount'     => $amount,
                        'order_index'=> isset($legacy->orden) ? (int)$legacy->orden : null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $inserted++;
            }
        });

    $this->info("Insertados/actualizados: {$inserted} | Saltados: {$skipped} | Dry-run: ".($dry ? 'sí' : 'no'));
});
