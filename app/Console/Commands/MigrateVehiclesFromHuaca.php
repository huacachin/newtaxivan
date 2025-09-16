<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateVehiclesFromHuaca extends Command
{
    protected $signature = 'taxivan:migrate-vehicles
        {--source=huaca_taxi_vehiculos : Tabla de origen en la misma BD}
        {--limit=0 : Límite de filas (0 = sin límite)}
        {--chunk=1000 : Tamaño del chunk}
        {--dry-run=0 : Simular sin escribir}
        {--zero-date-active=1 : 1=fechap 0000-00-00 => status active; 0=lo contrario}';

    protected $description = 'Copia/actualiza vehicles desde huaca_taxi_vehiculos con upsert por id y normalizaciones.';

    public function handle(): int
    {
        $source   = (string)$this->option('source');
        $limit    = (int)$this->option('limit');
        $chunk    = max(100, (int)$this->option('chunk'));
        $dryRun   = (bool)$this->option('dry-run');
        $zeroDateMeansActive = (bool)$this->option('zero-date-active');

        if (!Schema::hasTable($source))  { $this->error("No existe la tabla origen: {$source}");   return self::FAILURE; }
        if (!Schema::hasTable('vehicles')) { $this->error("No existe la tabla destino: vehicles"); return self::FAILURE; }

        // Columnas reales de vehicles para filtrar payload
        $vehCols = collect(Schema::getColumnListing('vehicles'))->flip();

        // Campos actualizables en conflicto (solo los que existan)
        $updateable = collect([
            'sort_order','plate','headquarters','headquarter_id',
            'entry_date','termination_date','class','brand','year','model','bodywork','color',
            'driver_id','owner_id','type','affiliated_company','condition','fuel',
            'soat_date','certificate_date','technical_review',
            'detail','std','status','updated_at',
        ])->filter(fn($c) => $vehCols->has($c))->values()->all();

        // Chequeo suave de columnas de origen (no bloquea)
        $expected = [
            'id','order','placa','sede','fechai','fechap','clase','marca','ano','modelo',
            'carroceria','color','conductor','propietario','condicion','condiciond','movil',
            'chipn','soat','certificado','revisiontecnica','obs','validity_status',
        ];
        $missing = array_filter($expected, fn($c) => !Schema::hasColumn($source, $c));
        if ($missing) $this->warn("Faltan en {$source}: ".implode(', ', $missing).". Se pondrá NULL donde falten.");

        $base = DB::table($source)->orderBy('id');
        if ($limit > 0) $base->limit($limit);

        $total = (clone $base)->count();
        $this->info("Procesando {$total} registros {$source} → vehicles ".($dryRun ? '(dry-run)' : ''));

        $created=0; $updated=0; $errors=0; $processed=0; $now=now();

        $bar = $this->output->createProgressBar(max(1, (int)ceil($total/$chunk)));
        $bar->start();

        // ¿vehicles usa FK a headquarters?
        $usesHQId   = $vehCols->has('headquarter_id');
        $hasHQTable = Schema::hasTable('headquarters');

        (clone $base)->chunk($chunk, function ($rows) use (&$created,&$updated,&$errors,&$processed,$now,$vehCols,$updateable,$dryRun,$bar,$usesHQId,$hasHQTable,$zeroDateMeansActive) {

            // 1) Recolecta candidatos de FKs del chunk
            $driverCandidates = [];
            $ownerCandidates  = [];
            foreach ($rows as $r) {
                $d = $this->toInt($r->conductor ?? null);
                $o = $this->toInt($r->propietario ?? null);
                if ($d) $driverCandidates[] = $d;
                if ($o) $ownerCandidates[]  = $o;
            }
            $driverCandidates = array_values(array_unique($driverCandidates));
            $ownerCandidates  = array_values(array_unique($ownerCandidates));

            // 2) Consulta existentes reales
            $existingDrivers = $driverCandidates
                ? DB::table('drivers')->whereIn('id', $driverCandidates)->pluck('id')->all()
                : [];
            $existingOwners = $ownerCandidates
                ? DB::table('owners')->whereIn('id', $ownerCandidates)->pluck('id')->all()
                : [];

            $driverExists = array_fill_keys($existingDrivers, true);
            $ownerExists  = array_fill_keys($existingOwners,  true);

            // (Opcional) loguea faltantes para audit
            $missingDrivers = array_diff($driverCandidates, $existingDrivers);
            $missingOwners  = array_diff($ownerCandidates,  $existingOwners);
            if ($missingDrivers) $this->warn('Drivers inexistentes en chunk: '.implode(',', $missingDrivers));
            if ($missingOwners)  $this->warn('Owners inexistentes en chunk: '.implode(',', $missingOwners));

            $batch = [];
            $idsInBatch = [];

            foreach ($rows as $r) {
                $processed++;

                $id = (int)($r->id ?? 0);
                if ($id <= 0) continue;

                // sede → headquarter_id o texto
                $hqName = $this->trimOrNull($r->sede ?? null);
                $headquarterId = null;
                if ($usesHQId && $hasHQTable && $hqName) {
                    $hq = DB::table('headquarters')->where('name', $hqName)->first();
                    if (!$hq) {
                        $headquarterId = DB::table('headquarters')->insertGetId([
                            'name' => $hqName,
                            'created_at' => $now, 'updated_at' => $now
                        ]);
                    } else {
                        $headquarterId = $hq->id;
                    }
                }

                $entry = $this->parseDate($r->fechai ?? null);
                $term  = $this->parseDate($r->fechap ?? null);

                // Regla de status
                $status = $this->computeStatusFromTermination($r->fechap ?? null, $zeroDateMeansActive);

                // Sanea FKs: si no existe el padre, setea NULL
                $driverId = $this->toInt($r->conductor ?? null);
                if ($driverId && !isset($driverExists[$driverId])) $driverId = null;

                $ownerId  = $this->toInt($r->propietario ?? null);
                if ($ownerId && !isset($ownerExists[$ownerId]))   $ownerId = null;

                $payload = [
                    'id'               => $id, // preserve id
                    'sort_order'       => $this->toInt($r->order ?? null),
                    'plate'            => $this->normalizePlate($r->placa ?? null),

                    'headquarters'     => $usesHQId ? null : $hqName,
                    'headquarter_id'   => $usesHQId ? $headquarterId : null,

                    'entry_date'       => $entry,
                    'termination_date' => $term,

                    'class'            => $this->trimOrNull($r->clase ?? null),
                    'brand'            => $this->trimOrNull($r->marca ?? null),
                    'year'             => $this->toYear($r->ano ?? null),
                    'model'            => $this->trimOrNull($r->modelo ?? null),
                    'bodywork'         => $this->trimOrNull($r->carroceria ?? null),
                    'color'            => $this->trimOrNull($r->color ?? null),

                    'driver_id'        => $driverId,
                    'owner_id'         => $ownerId,

                    'type'             => $this->trimOrNull($r->condicion ?? null),
                    'affiliated_company'=> $this->trimOrNull($r->condiciond ?? null),
                    'condition'        => $this->trimOrNull($r->movil ?? null),
                    'fuel'             => $this->trimOrNull($r->chipn ?? null),

                    'soat_date'        => $this->parseDate($r->soat ?? null),
                    'certificate_date' => $this->parseDate($r->certificado ?? null),
                    'technical_review' => $this->parseDate($r->revisiontecnica ?? null),

                    'detail'           => $this->trimOrNull($r->obs ?? null),
                    'std'              => $this->trimOrNull($r->validity_status ?? null),

                    'status'           => $status,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];

                // Filtra a columnas reales
                $payload = array_intersect_key($payload, $vehCols->toArray());

                $batch[] = $payload;
                $idsInBatch[] = $id;
            }

            if (empty($batch)) { $bar->advance(); return; }
            if ($dryRun)       { $bar->advance(); return; }

            // IDs existentes antes del upsert (conteo exacto)
            $existingIds   = DB::table('vehicles')->whereIn('id', $idsInBatch)->pluck('id')->all();
            $existingSet   = array_fill_keys($existingIds, true);
            $existingCount = count($existingIds);

            try {
                DB::table('vehicles')->upsert($batch, ['id'], $updateable);
                $updated += $existingCount;
                $created += (count($batch) - $existingCount);
            } catch (\Throwable $e) {
                // Reintento fila a fila (p.e. unique plate)
                foreach ($batch as $row) {
                    try {
                        DB::table('vehicles')->upsert([$row], ['id'], $updateable);
                        if (isset($existingSet[$row['id']])) $updated++; else $created++;
                    } catch (\Throwable $ee) {
                        $errors++; $this->warn("Error con vehicle id={$row['id']}: ".$ee->getMessage());
                    }
                }
            }

            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);
        $this->line("Procesados:   {$processed}");
        $this->info("Creados:      {$created}");
        $this->info("Actualizados: {$updated}");
        if ($errors) $this->error("Errores:      {$errors}");

        return self::SUCCESS;
    }

    // ---------- Helpers ---------- //

    private function trimOrNull($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        return $s;
    }

    private function toInt($v): ?int
    {
        if ($v === null) return null;
        $s = preg_replace('/\D+/', '', (string)$v);
        return $s !== '' ? (int)$s : null;
    }

    private function toYear($v): ?int
    {
        $n = $this->toInt($v);
        if ($n === null) return null;
        if ($n < 1950 || $n > 2100) return null;
        return $n;
    }

    private function normalizePlate($v): ?string
    {
        $s = strtoupper(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = preg_replace('/[^A-Z0-9\- ]/', '', $s);   // solo letras/números/guión/espacio
        $s = preg_replace('/\s+/', ' ', $s);          // compacta espacios
        return $s ?: null;
    }

    private function parseDate($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;

        // ZERO dates
        $zero = [
            '0000-00-00','00/00/0000','0000/00/00','00-00-0000',
            '0000-00-00 00:00:00','0000/00/00 00:00:00'
        ];
        if (in_array($s, $zero, true)) return null;
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

    private function isZeroDate($v): bool
    {
        $s = trim((string)$v);
        if ($s === '') return true;
        return in_array($s, [
            '0000-00-00','00/00/0000','0000/00/00','00-00-0000','0000-00-00 00:00:00'
        ], true);
    }

    private function computeStatusFromTermination($legacyFechap, bool $zeroDateMeansActive): string
    {
        $zero = $this->isZeroDate($legacyFechap);
        if ($zeroDateMeansActive) {
            return $zero ? 'active' : 'inactive';
        } else {
            return $zero ? 'inactive' : 'active';
        }
    }
}
