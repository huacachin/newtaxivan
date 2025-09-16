<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigratePaymentsFromHauca extends Command
{
    protected $signature = 'taxivan:migrate-payments
        {--source=huaca_pagos : Tabla de origen (misma BD)}
        {--limit=0 : Límite de filas (0 = sin límite)}
        {--chunk=1000 : Tamaño del chunk}
        {--dry-run=0 : Simular sin escribir}
        {--col-plate=placa : Columna legacy con la placa}
        {--col-user=usuario : Columna legacy con el NOMBRE del usuario}
        {--col-hq=sucu : Columna legacy con el NOMBRE de la sede}
        {--default-hour=00:00:00 : Hora por defecto si la legacy viene vacía/invalid}
    ';

    protected $description = 'Copia/actualiza payments desde hauca_pagos con upsert por id, resolviendo FKs y marcando is_support.';

    public function handle(): int
    {
        $source      = (string) $this->option('source');
        $limit       = (int) $this->option('limit');
        $chunk       = max(100, (int) $this->option('chunk'));
        $dryRun      = (bool) $this->option('dry-run');
        $colPlate    = (string) $this->option('col-plate');
        $colUser     = (string) $this->option('col-user');
        $colHQ       = (string) $this->option('col-hq');
        $defaultHour = (string) $this->option('default-hour') ?: '00:00:00';

        if (!Schema::hasTable($source))        { $this->error("No existe la tabla origen: {$source}");     return self::FAILURE; }
        if (!Schema::hasTable('payments'))     { $this->error("No existe la tabla destino: payments");     return self::FAILURE; }
        if (!Schema::hasTable('vehicles'))     { $this->error("No existe la tabla vehicles");              return self::FAILURE; }
        if (!Schema::hasTable('users'))        { $this->error("No existe la tabla users");                 return self::FAILURE; }
        if (!Schema::hasTable('headquarters')) { $this->error("No existe la tabla headquarters");          return self::FAILURE; }

        $expected = ['id','serie','fecha','fechac','monto','tipo','hora','latitud','longitud', $colPlate, $colUser, $colHQ];
        $missing = array_filter($expected, fn($c) => !Schema::hasColumn($source, $c));
        if ($missing) {
            $this->warn("Columnas faltantes en {$source}: ".implode(', ', $missing).". Se pondrá NULL donde falten.");
        }

        $payCols = collect(Schema::getColumnListing('payments'))->flip();

        $updateable = collect([
            'serie','date_register','date_payment','vehicle_id','amount','type',
            'user_id','headquarter_id','hour','latitude','longitude',
            'legacy_plate','is_support',
            'updated_at',
        ])->filter(fn($c) => $payCols->has($c))->values()->all();

        $base = DB::table($source)->orderBy('id');
        if ($limit > 0) $base->limit($limit);

        $total = (clone $base)->count();
        $this->info("Procesando {$total} registros {$source} → payments ".($dryRun ? '(dry-run)' : ''));

        // Maps
        $vehicleMap = []; // key = plateKey, value = ['id'=>int, 'active'=>bool]
        DB::table('vehicles')->select('id','plate','status')->orderBy('id')->chunk(5000, function($vs) use (&$vehicleMap) {
            foreach ($vs as $v) {
                $vehicleMap[$this->normalizePlateKey($v->plate)] = [
                    'id'     => (int) $v->id,
                    'active' => strtolower((string) $v->status) === 'active',
                ];
            }
        });

        $userMap = [];
        DB::table('users')->select('id','name')->orderBy('id')->chunk(5000, function($us) use (&$userMap) {
            foreach ($us as $u) {
                $userMap[$this->normalizeName($u->name)] = (int) $u->id;
            }
        });

        $hqMap = [];
        DB::table('headquarters')->select('id','name')->orderBy('id')->chunk(2000, function($hs) use (&$hqMap) {
            foreach ($hs as $h) {
                $hqMap[$this->normalizeName($h->name)] = (int) $h->id;
            }
        });

        $created=0; $updated=0; $errors=0; $processed=0;
        $now = now();

        $bar = $this->output->createProgressBar(max(1, (int)ceil($total / $chunk)));
        $bar->start();

        (clone $base)->chunk($chunk, function ($rows) use (&$created,&$updated,&$errors,&$processed,$now,$payCols,$updateable,$dryRun,$bar,$colPlate,$colUser,$colHQ,$defaultHour,$vehicleMap,$userMap,$hqMap) {

            $batch = [];
            $idsInBatch = [];

            foreach ($rows as $r) {
                $processed++;

                $id = (int)($r->id ?? 0);
                if ($id <= 0) continue;

                $dateRegister = $this->parseDate($r->fecha  ?? null);
                $datePayment  = $this->parseDate($r->fechac ?? null);

                $parsedHour = $this->parseTime($r->hora ?? null);
                $hour       = $parsedHour ?? $defaultHour;

                $legacyPlateRaw  = $this->normalizePlate($r->{$colPlate} ?? null);
                $plateKey        = $this->normalizePlateKey($r->{$colPlate} ?? null);
                $vehInfo         = $plateKey && isset($vehicleMap[$plateKey]) ? $vehicleMap[$plateKey] : null;

                if ($vehInfo && $vehInfo['active']) {
                    $vehicleId = $vehInfo['id'];
                    $isSupport = 0;
                } else {
                    $vehicleId = null;
                    $isSupport = 1;
                }

                $userKey  = $this->normalizeName($r->{$colUser} ?? null);
                $hqKey    = $this->normalizeName($r->{$colHQ} ?? null);
                $userId   = $userKey && isset($userMap[$userKey]) ? $userMap[$userKey] : null;
                $hqId     = $hqKey   && isset($hqMap[$hqKey])     ? $hqMap[$hqKey]   : null;

                $amount    = $this->toMoney($r->monto ?? null);
                $latitude  = $this->toCoord($r->latitud ?? null);
                $longitude = $this->toCoord($r->longitud ?? null);

                $serie = $this->trimOrNull($r->serie ?? null);
                $type  = $this->trimOrNull($r->tipo  ?? null);

                $payload = [
                    'id'              => $id,
                    'serie'           => $serie,
                    'date_register'   => $dateRegister,
                    'date_payment'    => $datePayment,
                    'vehicle_id'      => $vehicleId,
                    'amount'          => $amount,
                    'type'            => $type,
                    'user_id'         => $userId,
                    'headquarter_id'  => $hqId,
                    'hour'            => $hour,
                    'latitude'        => $latitude,
                    'longitude'       => $longitude,
                    'legacy_plate'    => $legacyPlateRaw,
                    'is_support'      => $isSupport,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];

                $payload = array_intersect_key($payload, $payCols->toArray());

                $batch[] = $payload;
                $idsInBatch[] = $id;
            }

            if (empty($batch)) { $bar->advance(); return; }
            if ($dryRun)       { $bar->advance(); return; }

            $existingIds   = DB::table('payments')->whereIn('id', $idsInBatch)->pluck('id')->all();
            $existingSet   = array_fill_keys($existingIds, true);
            $existingCount = count($existingIds);

            try {
                DB::table('payments')->upsert($batch, ['id'], $updateable);
                $updated += $existingCount;
                $created += (count($batch) - $existingCount);
            } catch (\Throwable $e) {
                foreach ($batch as $row) {
                    try {
                        DB::table('payments')->upsert([$row], ['id'], $updateable);
                        if (isset($existingSet[$row['id']])) $updated++; else $created++;
                    } catch (\Throwable $ee) {
                        $errors++; $this->warn("Error con payment id={$row['id']}: ".$ee->getMessage());
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
        if (!empty($errors)) $this->error("Errores: {$errors}");

        return self::SUCCESS;
    }

    // -------- Helpers -------- //

    private function trimOrNull($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        return $s;
    }

    private function normalizePlate($v): ?string
    {
        $s = strtoupper(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = preg_replace('/[^A-Z0-9\- ]/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?: null;
    }

    private function normalizePlateKey($v): ?string
    {
        $s = strtoupper(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = preg_replace('/[^A-Z0-9]/', '', $s);
        return $s ?: null;
    }

    private function normalizeName($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = preg_replace('/\s+/', ' ', $s);
        $s = mb_strtolower($s, 'UTF-8');
        return $s;
    }

    private function parseDate($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;

        $zero = ['0000-00-00','00/00/0000','0000/00/00','00-00-0000','0000-00-00 00:00:00'];
        if (in_array($s, $zero, true)) return null;
        if (preg_match('/^0{2}[\/-]0{2}[\/-]0{4}(?:\s+0{2}:0{2}:0{2})?$/', $s)) return null;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            [$Y,$m,$d] = array_map('intval', explode('-', $s));
            if ($Y < 1900 || $Y > 2100 || $m===0 || $d===0) return null;
            return sprintf('%04d-%02d-%02d', $Y, $m, $d);
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m)) {
            $d=(int)$m[1]; $M=(int)$m[2]; $Y=(int)$m[3];
            if ($Y < 1900 || $Y > 2100 || $M===0 || $d===0) return null;
            return sprintf('%04d-%02d-%02d', $Y, $M, $d);
        }
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

    private function parseTime($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-' || $s === '000000') return null;

        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $s, $m)) {
            $H=(int)$m[1]; $i=(int)$m[2]; $S=(int)$m[3];
            if ($H>23 || $i>59 || $S>59) return null;
            return sprintf('%02d:%02d:%02d', $H,$i,$S);
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $s, $m)) {
            $H=(int)$m[1]; $i=(int)$m[2];
            if ($H>23 || $i>59) return null;
            return sprintf('%02d:%02d:%02d', $H,$i,0);
        }

        try {
            $t = Carbon::parse($s);
            return $t->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toMoney($v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = str_replace([' ', 'S/','s/','USD','$'], '', $s);
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (strpos($s, ',') !== false && strpos($s, '.') === false) {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? number_format((float)$s, 2, '.', '') : null;
    }

    private function toCoord($v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (string)$s : null;
    }
}
