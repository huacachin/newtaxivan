<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateIncomesFromLegacy extends Command
{
    protected $signature = 'taxivan:migrate-incomes
        {--source=huaca_ingreso : Tabla legacy}
        {--limit=0 : Límite de filas (0 = sin límite)}
        {--chunk=1000 : Tamaño de chunk}
        {--dry-run=0 : Simular sin escribir}
        {--since=2021-01-01 : Migrar sólo registros con fechaentrada > since}
        {--col-id=identrada : Columna legacy id}
        {--col-date=fechaentrada : Columna legacy fecha}
        {--col-reason=aa : Columna legacy motivo}
        {--col-detail=detalle : Columna legacy detalle}
        {--col-total=totalgeneral : Columna legacy total}
        {--col-user=usuario : Columna legacy con NOMBRE del usuario}
    ';

    protected $description = 'Importa incomes desde huaca_ingresos mapeando user por nombre. Upsert por id.';

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

        foreach ([$source, 'incomes', 'users'] as $tbl) {
            if (!Schema::hasTable($tbl)) {
                $this->error("Falta la tabla: {$tbl}");
                return self::FAILURE;
            }
        }

        $expected = [$colId, $colDate, $colReason, $colDetail, $colTotal, $colUser];
        $missing = array_filter($expected, fn($c) => !Schema::hasColumn($source, $c));
        if ($missing) {
            $this->warn("Columnas faltantes en {$source}: ".implode(', ', $missing).". Se pondrá NULL donde falten.");
        }

        $incCols = collect(Schema::getColumnListing('incomes'))->flip();
        $updateable = array_values(array_filter([
            'date','reason','detail','total','user_id','updated_at'
        ], fn($c) => $incCols->has($c)));

        $userMap = [];
        DB::table('users')->select('id','name')->orderBy('id')->chunk(5000, function($rs) use (&$userMap) {
            foreach ($rs as $u) {
                $key = $this->normalizeName($u->name);
                if ($key) $userMap[$key] = (int)$u->id;
            }
        });

        // FILTRO POR FECHA > $since
        $base = DB::table($source)
            ->where($colDate, '>', $since)
            ->orderBy($colId);

        if ($limit > 0) $base->limit($limit);

        $total = (clone $base)->count();
        $this->info("Importando {$total} registros desde {$source} con {$colDate} > {$since}".($dryRun?' (dry-run)':''));

        $created=0; $updated=0; $errors=0; $processed=0;
        $now = now();
        $bar = $this->output->createProgressBar(max(1, (int)ceil($total/$chunk)));
        $bar->start();

        (clone $base)->chunk($chunk, function($rows) use (
            &$created,&$updated,&$errors,&$processed,$now,$dryRun,$bar,
            $incCols,$updateable,$colId,$colDate,$colReason,$colDetail,$colTotal,$colUser,$userMap
        ) {
            $batch = [];
            $idsInBatch = [];

            foreach ($rows as $r) {
                $processed++;

                $id = (int)($r->{$colId} ?? 0);
                if ($id <= 0) continue;

                $date = $this->parseDate($r->{$colDate} ?? null);
                if (!$date) {
                    $this->warn("Saltando id={$id}: fecha inválida");
                    continue;
                }

                $userId = null;
                $nameKey = $this->normalizeName($r->{$colUser} ?? null);
                if ($nameKey && isset($userMap[$nameKey])) {
                    $userId = $userMap[$nameKey];
                }

                $payload = [
                    'id'        => $id,
                    'date'      => $date,
                    'reason'    => $this->trimOrNull($r->{$colReason} ?? null),
                    'detail'    => $this->trimOrNull($r->{$colDetail} ?? null),
                    'total'     => $this->toMoney($r->{$colTotal} ?? null) ?? 0,
                    'user_id'   => $userId,
                    'created_at'=> $now,
                    'updated_at'=> $now,
                ];

                $batch[] = array_intersect_key($payload, $incCols->toArray());
                $idsInBatch[] = $id;
            }

            if (empty($batch)) { $bar->advance(); return; }
            if ($dryRun) { $bar->advance(); return; }

            $existing = DB::table('incomes')->whereIn('id', $idsInBatch)->pluck('id')->all();
            $existingSet = array_fill_keys($existing, true);
            $existCount = count($existing);

            try {
                DB::table('incomes')->upsert($batch, ['id'], $updateable);
                $updated += $existCount;
                $created += (count($batch) - $existCount);
            } catch (\Throwable $e) {
                foreach ($batch as $row) {
                    try {
                        DB::table('incomes')->upsert([$row], ['id'], $updateable);
                        if (isset($existingSet[$row['id']])) $updated++; else $created++;
                    } catch (\Throwable $ee) {
                        $errors++;
                        $this->warn("Error con income id={$row['id']}: ".$ee->getMessage());
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
