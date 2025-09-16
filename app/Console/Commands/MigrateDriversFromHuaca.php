<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateDriversFromHuaca extends Command
{
    protected $signature = 'taxivan:migrate-drivers
        {--source=huaca_taxi_conductor : Tabla de origen (misma BD)}
        {--limit=0 : Límite de filas (0 = sin límite)}
        {--chunk=1000 : Tamaño del chunk}
        {--dry-run=0 : Simular sin escribir}';

    protected $description = 'Copia/actualiza drivers desde huaca_taxi_conductor (misma BD) con upsert por id.';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $limit  = (int) $this->option('limit');
        $chunk  = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if (!Schema::hasTable($source)) {
            $this->error("No existe la tabla de origen: {$source}");
            return self::FAILURE;
        }
        if (!Schema::hasTable('drivers')) {
            $this->error("No existe la tabla destino: drivers");
            return self::FAILURE;
        }

        // Columnas reales de drivers para no intentar escribir campos inexistentes
        $driverCols = collect(Schema::getColumnListing('drivers'))->flip();

        // Campos que intentaremos actualizar en conflicto
        $updateable = collect([
            'name',
            'document_number',
            'document_expiration_date',
            'birthdate',
            'email',
            'district',
            'address',
            'phone',
            'contract_start',
            'contract_end',
            'condition',
            'class',
            'score',
            'category',
            'license',
            'license_issue_date',
            'license_revalidation_date',
            'credential',
            'credential_expiration_date',
            'credential_municipality',
            'road_education',
            'road_education_expiration_date',
            'road_education_municipality',
            'updated_at',
        ])->filter(fn($c) => $driverCols->has($c))->values()->all();

        // Aviso de columnas faltantes en origen (no bloquea)
        $expectedSource = [
            'id','nombre','dni','dnifecha','fechan','email','distrito','direccion','telefono',
            'contratoi','contratof','condicion','clase','puntaje','categoria','licencia',
            'fexpedicion','frevalidacion','credencial1','credencial2','credencial4',
            'fechaexpiev','fechavencev','muniev',
        ];
        $missing = array_filter($expectedSource, fn($c) => !Schema::hasColumn($source, $c));
        if (!empty($missing)) {
            $this->warn("Columnas faltantes en {$source}: ".implode(', ', $missing).". Se pondrá NULL donde falten.");
        }

        $base = DB::table($source)->orderBy('id');
        if ($limit > 0) $base->limit($limit);

        $total = (clone $base)->count();
        $this->info("Procesando {$total} registros {$source} → drivers ".($dryRun ? '(dry-run)' : ''));

        $created = 0; $updated = 0; $errors = 0; $processed = 0;
        $now = now();

        $bar = $this->output->createProgressBar(max(1, (int)ceil($total / $chunk)));
        $bar->start();

        (clone $base)->chunk($chunk, function ($rows) use (&$created,&$updated,&$errors,&$processed,$now,$driverCols,$updateable,$dryRun,$bar) {
            $batch = [];
            $idsInBatch = [];

            foreach ($rows as $r) {
                $processed++;

                $id = (int)($r->id ?? 0);
                if ($id <= 0) continue;

                $payload = [
                    'id'                            => $id, // preserve id
                    'name'                          => $this->trimOrNull($r->nombre ?? null),
                    'document_number'               => $this->digits($r->dni ?? null),
                    'document_expiration_date'      => $this->parseDate($r->dnifecha ?? null),
                    'birthdate'                     => $this->parseDate($r->fechan ?? null),
                    'email'                         => $this->validEmail($r->email ?? null),
                    'district'                      => $this->trimOrNull($r->distrito ?? null),
                    'address'                       => $this->trimOrNull($r->direccion ?? null),
                    'phone'                         => $this->digits($r->telefono ?? null),
                    'contract_start'                => $this->parseDate($r->contratoi ?? null),
                    'contract_end'                  => $this->parseDate($r->contratof ?? null),
                    'condition'                     => $this->trimOrNull($r->condicion ?? null),
                    'class'                         => $this->trimOrNull($r->clase ?? null),
                    'score'                         => $this->toFloat($r->puntaje ?? null),
                    'category'                      => $this->trimOrNull($r->categoria ?? null),
                    'license'                       => $this->trimOrNull($r->licencia ?? null),
                    'license_issue_date'            => $this->parseDate($r->fexpedicion ?? null),
                    'license_revalidation_date'     => $this->parseDate($r->frevalidacion ?? null),
                    'credential'                    => $this->trimOrNull($r->credencial1 ?? null),
                    'credential_expiration_date'    => $this->parseDate($r->credencial2 ?? null),
                    'credential_municipality'       => $this->trimOrNull($r->credencial4 ?? null),
                    'road_education'                => $this->parseDate($r->fechaexpiev ?? null), // asumimos DATE
                    'road_education_expiration_date'=> $this->parseDate($r->fechavencev ?? null),
                    'road_education_municipality'   => $this->trimOrNull($r->muniev ?? null),
                    'status'                        => 'active',
                    'created_at'                    => $now,
                    'updated_at'                    => $now,
                ];

                // Quitar claves cuyo destino no existe en drivers
                $payload = array_intersect_key($payload, $driverCols->toArray());

                $batch[] = $payload;
                $idsInBatch[] = $id;
            }

            if (empty($batch)) { $bar->advance(); return; }
            if ($dryRun)       { $bar->advance(); return; }

            // Calcula existentes ANTES del upsert (conteo exacto)
            $existingIds = DB::table('drivers')->whereIn('id', $idsInBatch)->pluck('id')->all();
            $existingSet = array_fill_keys($existingIds, true);
            $existingCount = count($existingIds);

            try {
                DB::table('drivers')->upsert($batch, ['id'], $updateable);
                $updated += $existingCount;
                $created += (count($batch) - $existingCount);
            } catch (\Throwable $e) {
                // Reintento individual (p.ej. duplicate email)
                foreach ($batch as $row) {
                    try {
                        DB::table('drivers')->upsert([$row], ['id'], $updateable);
                        if (isset($existingSet[$row['id']])) $updated++; else $created++;
                    } catch (\Throwable $ee) {
                        // Si choca por email único, reintenta sin email
                        if (str_contains(strtolower($ee->getMessage()), 'duplicate') && array_key_exists('email', $row) && !is_null($row['email'])) {
                            $row['email'] = null;
                            try {
                                DB::table('drivers')->upsert([$row], ['id'], $updateable);
                                if (isset($existingSet[$row['id']])) $updated++; else $created++;
                                continue;
                            } catch (\Throwable $eee) {
                                $errors++; $this->warn("Error con driver id={$row['id']}: ".$eee->getMessage());
                            }
                        } else {
                            $errors++; $this->warn("Error con driver id={$row['id']}: ".$ee->getMessage());
                        }
                    }
                }
            }

            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);
        $this->line("Procesados: {$processed}");
        $this->info("Creados:    {$created}");
        $this->info("Actualizados: {$updated}");
        if ($errors) $this->error("Errores:    {$errors}");

        return self::SUCCESS;
    }

    // ---------- Helpers ---------- //

    private function digits($v): ?string
    {
        $s = preg_replace('/\D+/', '', (string)$v);
        // trata '?', '-' o vacíos como NULL
        return $s !== '' ? $s : null;
    }

    private function validEmail($v): ?string
    {
        $s = strtolower(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-' || $s === 'na' || $s === 'n/a' || $s === 'sin correo') return null;
        return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : null;
    }

    private function trimOrNull($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        return $s;
    }

    private function toFloat($v): ?float
    {
        if ($v === null) return null;
        $s = str_replace([',',' '], ['.',''], (string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        return is_numeric($s) ? (float)$s : null;
    }

    private function parseDate($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;

        // ZERO dates y variantes
        $zeroPatterns = [
            '0000-00-00','00/00/0000','0000/00/00','00-00-0000',
            '0000-00-00 00:00:00','0000/00/00 00:00:00'
        ];
        if (in_array($s, $zeroPatterns, true)) return null;
        if (preg_match('/^0{2}[\/-]0{2}[\/-]0{4}(?:\s+0{2}:0{2}:0{2})?$/', $s)) return null;

        $s = str_replace(['.', '\\'], ['-','-'], $s);

        // YYYY-mm-dd
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            [$Y,$m,$d] = array_map('intval', explode('-', $s));
            if ($Y < 1900 || $Y > 2100 || $m === 0 || $d === 0) return null;
            return sprintf('%04d-%02d-%02d', $Y, $m, $d);
        }

        // dd/mm/YYYY
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m)) {
            $d=(int)$m[1]; $M=(int)$m[2]; $Y=(int)$m[3];
            if ($Y < 1900 || $Y > 2100 || $M===0 || $d===0) return null;
            return sprintf('%04d-%02d-%02d', $Y, $M, $d);
        }

        // dd-mm-YYYY
        if (preg_match('#^(\d{2})-(\d{2})-(\d{4})$#', $s, $m)) {
            $d=(int)$m[1]; $M=(int)$m[2]; $Y=(int)$m[3];
            if ($Y < 1900 || $Y > 2100 || $M===0 || $d===0) return null;
            return sprintf('%04d-%02d-%02d', $Y, $M, $d);
        }

        try {
            $dt = Carbon::parse($s);
            if ($dt->year < 1900 || $dt->year > 2100) return null;
            return $dt->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
