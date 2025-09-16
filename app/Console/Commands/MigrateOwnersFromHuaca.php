<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateOwnersFromHuaca extends Command
{
    protected $signature = 'taxivan:migrate-owners
        {--source=huaca_taxi_propietario : Tabla de origen}
        {--limit=0 : Límite de filas (0 = sin límite)}
        {--chunk=1000 : Tamaño del chunk para procesar}
        {--dry-run=0 : Solo contar, no escribir}
    ';

    protected $description = 'Copia/actualiza owners desde huaca_taxi_propietarios (misma BD) con upsert por id.';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $limit  = (int) $this->option('limit');
        $chunk  = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        // Preflight
        if (!Schema::hasTable($source)) {
            $this->error("No existe la tabla de origen: {$source}");
            return self::FAILURE;
        }
        if (!Schema::hasTable('owners')) {
            $this->error("No existe la tabla destino: owners");
            return self::FAILURE;
        }

        // Columnas destino disponibles (solo escribimos lo que exista)
        $ownersCols = collect(Schema::getColumnListing('owners'))->flip();

        // Campos mapeados (legacy -> owners)
        // codigop -> id
        // ruc -> document_number
        // dnifecha -> document_expiration_date
        // nombre -> name
        // aniversario -> birthdate
        // direccion -> address
        // distrito -> district
        // fijo -> phone
        // email -> email
        $updateable = collect([
            'document_number',
            'document_expiration_date',
            'name',
            'birthdate',
            'address',
            'district',
            'phone',
            'email',
            'updated_at',
        ])->filter(fn($c) => $ownersCols->has($c))->values()->all();

        // Validación suave de columnas de origen
        $requiredSource = ['codigop','ruc','dnifecha','nombre','aniversario','direccion','distrito','fijo','email'];
        $missing = array_filter($requiredSource, fn($c) => !Schema::hasColumn($source, $c));
        if (!empty($missing)) {
            $this->warn("Columnas faltantes en {$source}: ".implode(', ', $missing).". Se rellenarán como NULL si corresponde.");
        }

        $base = DB::table($source)->orderBy('codigop');
        if ($limit > 0) $base->limit($limit);

        $total = (clone $base)->count();
        $this->info("Procesando {$total} registros desde {$source} → owners ".($dryRun ? '(dry-run)' : ''));

        $created = 0; $updated = 0; $errors = 0; $processed = 0;
        $now = now();

        $bar = $this->output->createProgressBar(max(1, (int)ceil($total / $chunk)));
        $bar->start();

        (clone $base)->chunk($chunk, function ($rows) use (&$created, &$updated, &$errors, &$processed, $now, $ownersCols, $updateable, $dryRun, $bar) {
            $batch = [];

            foreach ($rows as $r) {
                $processed++;

                $id = (int)($r->codigop ?? 0);
                if ($id <= 0) { continue; }

                $docNumber = $this->digits($r->ruc ?? null);
                $docExp    = $this->parseDate($r->dnifecha ?? null);
                $birthdate = $this->parseDate($r->aniversario ?? null);
                $email     = $this->validEmail($r->email ?? null);
                $phone     = $this->digits($r->fijo ?? null);

                // Armar payload con solo columnas existentes en owners
                $payload = [
                    'id'                        => $id, // preserve id
                    'document_type'             => 'DNI',
                    'document_number'           => $docNumber,
                    'document_expiration_date'  => $docExp,
                    'name'                      => $this->trimOrNull($r->nombre ?? null),
                    'birthdate'                 => $birthdate,
                    'address'                   => $this->trimOrNull($r->direccion ?? null),
                    'district'                  => $this->trimOrNull($r->distrito ?? null),
                    'phone'                     => $phone,
                    'email'                     => $email,
                    'created_at'                => $now,
                    'updated_at'                => $now,
                ];

                // Filtrar por columnas reales en owners
                $payload = array_intersect_key($payload, $ownersCols->toArray());
                $batch[] = $payload;
            }

            if (empty($batch)) { $bar->advance(); return; }

            if ($dryRun) { $bar->advance(); return; }

            // Intento upsert por lote
            try {
                DB::table('owners')->upsert($batch, ['id'], $updateable);
                // upsert no devuelve filas afectadas; estimamos:
                // contamos cuántos IDs ya existen para aproximar updated/created
                $ids = array_column($batch, 'id');
                $existingCount = DB::table('owners')->whereIn('id', $ids)->count();
                // Después del upsert, todos existen. Aproximamos:
                // - updated ≈ existingCount
                // - created ≈ (batchCount - existingCount)
                $updated += $existingCount;
                $created += (count($batch) - $existingCount);
            } catch (\Throwable $e) {
                // Si hay violación de unique (p.e. email), reintentamos uno a uno
                foreach ($batch as $row) {
                    try {
                        DB::table('owners')->upsert([$row], ['id'], $updateable);
                        // Estimación de created/updated individual
                        $exists = DB::table('owners')->where('id', $row['id'])->exists();
                        if ($exists) $updated++; else $created++;
                    } catch (\Throwable $ee) {
                        // si choca por email único, reintenta sin email
                        if (str_contains(strtolower($ee->getMessage()), 'duplicate') && array_key_exists('email', $row) && !is_null($row['email'])) {
                            $row['email'] = null;
                            try {
                                DB::table('owners')->upsert([$row], ['id'], $updateable);
                                $updated++;
                                continue;
                            } catch (\Throwable $eee) {
                                $errors++; $this->warn("Error con owner id={$row['id']}: ".$eee->getMessage());
                            }
                        } else {
                            $errors++; $this->warn("Error con owner id={$row['id']}: ".$ee->getMessage());
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
        return $s !== '' ? $s : null;
    }

    private function validEmail($v): ?string
    {
        $s = strtolower(trim((string)$v));
        return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : null;
    }

    private function trimOrNull($v): ?string
    {
        $s = trim((string)$v);
        return $s !== '' ? $s : null;
    }

    private function parseDate($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '') return null;

        // --- ZERO DATES & valores inválidos comunes ---
        $zeroPatterns = [
            '0000-00-00', '00/00/0000', '0000/00/00', '00-00-0000',
            '0000-00-00 00:00:00', '0000/00/00 00:00:00', '0000-00', '0000'
        ];
        if (in_array($s, $zeroPatterns, true)) return null;
        if (preg_match('/^0{2}[\/-]0{2}[\/-]0{4}(?:\s+0{2}:0{2}:0{2})?$/', $s)) return null;

        // Normaliza separadores raros
        $s = str_replace(['.', '\\'], ['-','-'], $s);

        // ISO YYYY-mm-dd (pero no aceptes 0000-00-00)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            // filtro por rangos plausibles
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

        // Último intento con Carbon::parse
        try {
            $dt = \Carbon\Carbon::parse($s);
            if ($dt->year < 1900 || $dt->year > 2100) return null;
            return $dt->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

}
