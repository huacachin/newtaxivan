<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateExpensesFromLegacy extends Command
{
    protected $signature = 'taxivan:migrate-expenses
        {--source=huaca_entrada : Tabla legacy}
        {--limit=0 : Límite de filas (0 = sin límite)}
        {--chunk=1000 : Tamaño del chunk}
        {--dry-run=0 : Simular sin escribir}
        {--since=2021-01-01 : Sólo migrar registros con fechaentrada > since}
        {--col-id=identrada : Columna legacy id}
        {--col-date=fechaentrada : Columna legacy fecha}
        {--col-reason=aa : Columna legacy motivo}
        {--col-detail=detalle : Columna legacy detalle}
        {--col-total=totalgeneral : Columna legacy total}
        {--col-user=usuario : Columna legacy con NOMBRE del usuario}
        {--col-hq=sucursal : Columna legacy de sucursal (id numérico > 0 o nombre)}
        {--col-doc=tipcom : Columna legacy tipo de documento}
        {--col-charge=respons : Columna legacy responsable}
    ';

    protected $description = 'Importa expenses desde huaca_entrada. Filtra por fechaentrada > --since. Resuelve user por nombre y headquarter por id>0 o nombre. Upsert por id.';

    public function handle(): int
    {
        $source   = (string)$this->option('source');
        $limit    = (int)$this->option('limit');
        $chunk    = max(100, (int)$this->option('chunk'));
        $dryRun   = (bool)$this->option('dry-run');
        $sinceOpt = (string)$this->option('since') ?: '2021-01-01';
        $since    = $this->parseDate($sinceOpt) ?: '2021-01-01';

        $colId     = (string)$this->option('col-id');
        $colDate   = (string)$this->option('col-date');
        $colReason = (string)$this->option('col-reason');
        $colDetail = (string)$this->option('col-detail');
        $colTotal  = (string)$this->option('col-total');
        $colUser   = (string)$this->option('col-user');
        $colHQ     = (string)$this->option('col-hq');
        $colDoc    = (string)$this->option('col-doc');
        $colCharge = (string)$this->option('col-charge');

        foreach ([$source, 'expenses', 'users', 'headquarters'] as $tbl) {
            if (!Schema::hasTable($tbl)) {
                $this->error("Falta la tabla: {$tbl}");
                return self::FAILURE;
            }
        }

        $expCols   = collect(Schema::getColumnListing('expenses'))->flip();
        $updateable = array_values(array_filter([
            'date','reason','detail','total','user_id','headquarter_id','document_type','in_charge','updated_at'
        ], fn($c) => $expCols->has($c)));

        // Map usuarios por nombre normalizado
        $userMap = [];
        DB::table('users')->select('id','name')->orderBy('id')->chunk(5000, function($rs) use (&$userMap) {
            foreach ($rs as $u) {
                $k = $this->normalizeName($u->name);
                if ($k) $userMap[$k] = (int)$u->id;
            }
        });

        // Map sedes por id y por nombre
        $hqIdMap   = [];
        $hqNameMap = [];
        DB::table('headquarters')->select('id','name')->orderBy('id')->chunk(2000, function($rs) use (&$hqIdMap,&$hqNameMap) {
            foreach ($rs as $h) {
                $hqIdMap[(int)$h->id] = (int)$h->id;
                $k = $this->normalizeName($h->name);
                if ($k) $hqNameMap[$k] = (int)$h->id;
            }
        });

        // Filtro por fecha > since
        $base = DB::table($source)
            ->where($colDate, '>', $since)
            ->orderBy($colId);

        if ($limit > 0) $base->limit($limit);

        $total = (clone $base)->count();
        $this->info("Importando {$total} expenses desde {$source} con {$colDate} > {$since}".($dryRun?' (dry-run)':''));

        $created=0; $updated=0; $errors=0; $processed=0; $skipped=0;
        $now = now();
        $bar = $this->output->createProgressBar(max(1, (int)ceil($total / $chunk)));
        $bar->start();

        (clone $base)->chunk($chunk, function($rows) use (
            &$created,&$updated,&$errors,&$processed,&$skipped,$now,$dryRun,$bar,
            $expCols,$updateable,$colId,$colDate,$colReason,$colDetail,$colTotal,$colUser,$colHQ,$colDoc,$colCharge,
            $userMap,$hqIdMap,$hqNameMap
        ) {
            $batch = []; $idsInBatch = [];

            foreach ($rows as $r) {
                $processed++;

                $id = (int)($r->{$colId} ?? 0);
                if ($id <= 0) continue;

                $date = $this->parseDate($r->{$colDate} ?? null);
                if (!$date) {
                    $skipped++;
                    $this->warn("Saltando id={$id}: fecha inválida");
                    continue;
                }

                // user_id por nombre
                $userId = null;
                $userKey = $this->normalizeName($r->{$colUser} ?? null);
                if ($userKey && isset($userMap[$userKey])) $userId = $userMap[$userKey];

                // headquarter_id: si es numérico > 0, usarlo; si es texto, buscar por nombre; si es 0 -> null
                $headquarterId = null;
                $rawHQ = $r->{$colHQ} ?? null;
                if ($rawHQ !== null && trim((string)$rawHQ) !== '' ) {
                    if (is_numeric($rawHQ)) {
                        $n = (int)$rawHQ;
                        if ($n > 0 && isset($hqIdMap[$n])) $headquarterId = $hqIdMap[$n];
                    } else {
                        $hk = $this->normalizeName($rawHQ);
                        if ($hk && isset($hqNameMap[$hk])) $headquarterId = $hqNameMap[$hk];
                    }
                }

                $payload = [
                    'id'             => $id,
                    'date'           => $date,
                    'reason'         => $this->trimOrNull($r->{$colReason} ?? null),
                    'detail'         => $this->trimOrNull($r->{$colDetail} ?? null),
                    'total'          => $this->toMoney($r->{$colTotal} ?? null) ?? 0,
                    'user_id'        => $userId,
                    'headquarter_id' => $headquarterId,
                    'document_type'  => $this->trimOrNull($r->{$colDoc} ?? null),
                    'in_charge'      => $this->trimOrNull($r->{$colCharge} ?? null),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                $batch[] = array_intersect_key($payload, $expCols->toArray());
                $idsInBatch[] = $id;
            }

            if (empty($batch)) { $bar->advance(); return; }
            if ($dryRun)       { $bar->advance(); return; }

            // métricas created/updated
            $existing   = DB::table('expenses')->whereIn('id', $idsInBatch)->pluck('id')->all();
            $existingSet = array_fill_keys($existing, true);
            $existCount  = count($existing);

            try {
                DB::table('expenses')->upsert($batch, ['id'], $updateable);
                $updated += $existCount;
                $created += (count($batch) - $existCount);
            } catch (\Throwable $e) {
                foreach ($batch as $row) {
                    try {
                        DB::table('expenses')->upsert([$row], ['id'], $updateable);
                        if (isset($existingSet[$row['id']])) $updated++; else $created++;
                    } catch (\Throwable $ee) {
                        $errors++;
                        $this->warn("Error con expense id={$row['id']}: ".$ee->getMessage());
                    }
                }
            }

            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);
        $this->line("Procesados:   {$processed}");
        $this->line("Saltados:     {$skipped}");
        $this->info("Creados:      {$created}");
        $this->info("Actualizados: {$updated}");
        if ($errors) $this->error("Errores:      {$errors}");

        return self::SUCCESS;
    }

    // ===== Helpers =====
    private function trimOrNull($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        return $s;
    }

    private function normalizeName($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = preg_replace('/\s+/', ' ', $s);
        return mb_strtolower($s, 'UTF-8');
    }

    private function parseDate($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s==='?' || $s==='-' || $s==='0000-00-00' || $s==='0000-00-00 00:00:00') return null;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        if (preg_match('#^(\d{2})-(\d{2})-(\d{4})$#', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);

        try { return Carbon::parse($s)->toDateString(); } catch (\Throwable $e) { return null; }
    }

    private function toMoney($v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '' || $s==='?' || $s==='-') return null;
        $s = str_replace([' ', 'S/','s/','USD','$'], '', $s);
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (strpos($s, ',') !== false && strpos($s, '.') === false) {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? number_format((float)$s, 2, '.', '') : null;
    }
}
