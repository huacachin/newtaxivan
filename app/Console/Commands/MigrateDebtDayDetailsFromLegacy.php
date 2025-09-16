<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateDebtDayDetailsFromLegacy extends Command
{
    protected $signature = 'taxivan:migrate-debt-days-detail
        {--source=huaca_det_deuda : Tabla legacy detalle}
        {--parent=huaca_deuda_dias : Tabla legacy padre (para backfill)}
        {--limit=0 : Límite de filas (0=sin límite)}
        {--chunk=1000 : Tamaño de chunk}
        {--dry-run=0 : Simular sin escribir}
        {--col-user=usuario : Columna legacy con el NOMBRE del usuario}
        {--df=YMD : Formato de fecha legacy (YMD|DMY)}
        {--anchor-day=01 : Día para fechas tipo YYYY-MM}
        {--fallback-date=1970-01-01 : Fecha fallback para el padre si no se puede derivar}
        {--skip-on-null-date=0 : 1=omitir padre/detalle si no hay fecha ni fallback}
    ';

    protected $description = 'Importa debt_days_detail desde huaca_det_deuda. Hace backfill de debt_days si falta, con fecha derivada o fallback.';

    public function handle(): int
    {
        $source       = (string) $this->option('source');
        $parent       = (string) $this->option('parent');
        $limit        = (int) $this->option('limit');
        $chunk        = max(100, (int) $this->option('chunk'));
        $dryRun       = (bool) $this->option('dry-run');
        $colUser      = (string) $this->option('col-user');
        $df           = strtoupper((string)$this->option('df')) === 'DMY' ? 'DMY' : 'YMD';
        $anchorDay    = str_pad((string)$this->option('anchor-day'), 2, '0', STR_PAD_LEFT);
        $fallbackDate = trim((string)$this->option('fallback-date')) ?: null;
        $skipOnNull   = (bool) $this->option('skip-on-null-date');

        foreach ([$source, $parent, 'debt_days_detail', 'debt_days', 'vehicles', 'users'] as $tbl) {
            if (!Schema::hasTable($tbl)) {
                $this->error("Falta la tabla: {$tbl}");
                return self::FAILURE;
            }
        }

        $detailCols = collect(Schema::getColumnListing('debt_days_detail'))->flip();
        $debtCols   = collect(Schema::getColumnListing('debt_days'))->flip();

        $detailUpdateable = array_values(array_filter([
            'debt_days_id','exonerated','amortized','detail','user_id','date','updated_at'
        ], fn($c) => $detailCols->has($c)));

        $debtUpdateable = array_values(array_filter(array_merge([
            'vehicle_id','legacy_plate','is_support','days','total','date',
            'exonerated','detail_exonerated','amortized','condition','days_late',
            'updated_at',
        ], array_map(fn($i) => "d{$i}", range(1,31))), fn($c) => $debtCols->has($c)));

        // Maps
        $vehicleMap = [];
        DB::table('vehicles')->select('id','plate','status')->orderBy('id')->chunk(5000, function($rs) use (&$vehicleMap) {
            foreach ($rs as $v) {
                $vehicleMap[$this->normalizePlateKey($v->plate)] = [
                    'id'     => (int)$v->id,
                    'status' => (string)$v->status, // active/inactive
                ];
            }
        });

        $userMap = [];
        DB::table('users')->select('id','name')->orderBy('id')->chunk(5000, function($rs) use (&$userMap) {
            foreach ($rs as $u) {
                $userMap[$this->normalizeName($u->name)] = (int)$u->id;
            }
        });

        $base = DB::table($source)->orderBy('id');
        if ($limit > 0) $base->limit($limit);

        $total = (clone $base)->count();
        $this->info("Importando {$total} detalles desde {$source} ".($dryRun ? '(dry-run)' : ''));

        $created=0; $updated=0; $errors=0; $processed=0; $skipped=0; $parentsBackfilled=0; $placeholders=0;
        $now = now();
        $bar = $this->output->createProgressBar(max(1, (int)ceil($total/$chunk)));
        $bar->start();

        (clone $base)->chunk($chunk, function ($rows) use (
            &$created,&$updated,&$errors,&$processed,&$skipped,&$parentsBackfilled,&$placeholders,
            $now,$detailCols,$debtCols,$detailUpdateable,$debtUpdateable,$dryRun,$bar,
            $userMap,$vehicleMap,$source,$parent,$colUser,$df,$anchorDay,$fallbackDate,$skipOnNull
        ) {
            // 1) reunir id2 y backfill padre que falte
            $id2s = [];
            foreach ($rows as $r) { if (!empty($r->id2)) $id2s[] = (int)$r->id2; }
            $id2s = array_values(array_unique($id2s));

            if (!empty($id2s)) {
                $exists = DB::table('debt_days')->whereIn('id', $id2s)->pluck('id')->all();
                $missing = array_values(array_diff($id2s, $exists));

                if (!empty($missing)) {
                    DB::table($parent)->whereIn('id', $missing)->orderBy('id')->chunk(1000, function($ps) use (
                        &$parentsBackfilled,$now,$debtCols,$debtUpdateable,$dryRun,$vehicleMap,$df,$anchorDay,$fallbackDate,$skipOnNull
                    ) {
                        $batch = [];

                        foreach ($ps as $p) {
                            // Derivar fecha del padre legacy (fecha2 -> fecha -> YYYY-MM -> fallback)
                            $derivedDate =
                                $this->parseLegacyDate($p->fecha2 ?? null, $df) ?:
                                    $this->parseLegacyDate($p->fecha  ?? null, $df) ?:
                                        $this->parseMonthLike ($p->fecha2 ?? null, $anchorDay) ?:
                                            $this->parseMonthLike ($p->fecha  ?? null, $anchorDay);

                            if (!$derivedDate) {
                                if ($fallbackDate) {
                                    $derivedDate = $fallbackDate;
                                } elseif ($skipOnNull) {
                                    // sin fecha y sin fallback: no creamos este padre
                                    continue;
                                } else {
                                    // como tu columna no acepta NULL, usamos última salvada: '1970-01-01'
                                    $derivedDate = '1970-01-01';
                                }
                            }

                            $legacyPlate = $this->normalizePlate($p->placa ?? null);
                            $vk          = $this->normalizePlateKey($p->placa ?? null);
                            $vehicleId   = null; $isSupport = 1;

                            if ($vk && isset($vehicleMap[$vk])) {
                                $info = $vehicleMap[$vk];
                                if (strtolower($info['status']) === 'active') {
                                    $vehicleId = $info['id'];
                                    $isSupport = 0;
                                }
                            }

                            $payload = [
                                'id'               => (int)$p->id,
                                'vehicle_id'       => $vehicleId,
                                'legacy_plate'     => $legacyPlate,
                                'is_support'       => $vehicleId ? 0 : 1,
                                'days'             => $this->toSmallInt($p->dias ?? null, 0),
                                'total'            => $this->toMoneyNonNeg($p->total ?? null),
                                'date'             => $derivedDate,
                                'exonerated'       => $this->toMoneyNonNeg($p->exonera ?? null),
                                'detail_exonerated'=> $this->trimOrNull($p->detalleexo ?? null),
                                'amortized'        => $this->toMoneyNonNeg($p->amortiza ?? null),
                                'condition'        => $this->trimOrNull($p->condicion ?? null),
                                'days_late'        => $this->toSmallInt($p->diasretra ?? null, 0),
                                'created_at'       => $now,
                                'updated_at'       => $now,
                            ];

                            // d1..d31
                            for ($i=1;$i<=31;$i++) {
                                $col = 'd'.$i;
                                if (property_exists($p,$col) && $debtCols->has($col)) {
                                    $val = trim((string)$p->{$col});
                                    if ($val === '' || $val === '?') $val = null;
                                    $payload[$col] = $val;
                                }
                            }

                            $batch[] = array_intersect_key($payload, $debtCols->toArray());
                        }

                        if (!empty($batch) && !$dryRun) {
                            DB::table('debt_days')->upsert($batch, ['id'], $debtUpdateable);
                            $parentsBackfilled += count($batch);
                        }
                    });

                    // segunda verificación
                    $exists2 = DB::table('debt_days')->whereIn('id', $missing)->pluck('id')->all();
                    $stillMissing = array_values(array_diff($missing, $exists2));

                    // 1.b) placeholders mínimos (con fecha fallback, no NULL)
                    if (!empty($stillMissing) && !$dryRun) {
                        $dateForPH = $fallbackDate ?: '1970-01-01';
                        $batchPH = [];
                        foreach ($stillMissing as $mid) {
                            $batchPH[] = array_intersect_key([
                                'id'           => (int)$mid,
                                'vehicle_id'   => null,
                                'legacy_plate' => null,
                                'is_support'   => 1,
                                'days'         => 0,
                                'total'        => '0.00',
                                'date'         => $dateForPH,
                                'exonerated'   => '0.00',
                                'detail_exonerated' => null,
                                'amortized'    => '0.00',
                                'condition'    => null,
                                'days_late'    => 0,
                                'created_at'   => $now,
                                'updated_at'   => $now,
                            ], $debtCols->toArray());
                        }
                        if (!empty($batchPH)) {
                            DB::table('debt_days')->upsert($batchPH, ['id'], $debtUpdateable);
                            $placeholders += count($batchPH);
                            $this->warn("Se crearon {$placeholders} placeholders de debt_days (sin fuente en legacy padre).");
                        }
                    }
                }
            }

            // 2) insertar detalles
            $batch = []; $idsInBatch=[];
            foreach ($rows as $r) {
                $processed++;
                $id = (int)($r->id ?? 0);
                $parentId = (int)($r->id2 ?? 0);
                if ($id<=0 || $parentId<=0) { $skipped++; continue; }

                // Si aún no existe el padre (caso skip-on-null-date), no insertamos el detalle
                $hasParent = DB::table('debt_days')->where('id', $parentId)->exists();
                if (!$hasParent) { $skipped++; continue; }

                $userId  = null;
                $nameKey = $this->normalizeName($r->{$this->option('col-user')} ?? null);
                if ($nameKey && isset($userMap[$nameKey])) $userId = $userMap[$nameKey];

                $payload = [
                    'id'           => $id,
                    'debt_days_id' => $parentId,
                    'exonerated'   => $this->toMoney($r->montoexo ?? null) ?? '0.00',
                    'amortized'    => $this->toMoney($r->amortiza ?? null) ?? '0.00',
                    'detail'       => $this->trimOrNull($r->detalle ?? null),
                    'user_id'      => $userId,
                    'date'         => $this->parseLegacyDate($r->fecha ?? null, $df), // ✅
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
                $batch[] = array_intersect_key($payload, $detailCols->toArray());
                $idsInBatch[] = $id;
            }

            if (empty($batch)) { $bar->advance(); return; }
            if ($dryRun)       { $bar->advance(); return; }

            $existing = DB::table('debt_days_detail')->whereIn('id', $idsInBatch)->pluck('id')->all();
            $existingSet = array_fill_keys($existing, true);
            $existCount = count($existing);

            try {
                DB::table('debt_days_detail')->upsert($batch, ['id'], $detailUpdateable);
                $updated += $existCount;
                $created += (count($batch) - $existCount);
            } catch (\Throwable $e) {
                foreach ($batch as $row) {
                    try {
                        DB::table('debt_days_detail')->upsert([$row], ['id'], $detailUpdateable);
                        if (isset($existingSet[$row['id']])) $updated++; else $created++;
                    } catch (\Throwable $ee) {
                        $errors++;
                        $this->warn("Error con detail id={$row['id']} (debt_days_id={$row['debt_days_id']}): ".$ee->getMessage());
                    }
                }
            }

            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Padres backfilleados: {$parentsBackfilled}");
        $this->info("Placeholders creados: {$placeholders}");
        $this->line("Procesados:           {$processed}");
        $this->line("Saltados:             {$skipped}");
        $this->info("Creados:              {$created}");
        $this->info("Actualizados:         {$updated}");
        if ($errors) $this->error("Errores:              {$errors}");

        return self::SUCCESS;
    }

    /* ================== HELPERS ================== */

    private function trimOrNull($v): ?string {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        return $s;
    }
    private function normalizeName($v): ?string {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        return mb_strtolower(preg_replace('/\s+/', ' ', $s), 'UTF-8');
    }
    private function normalizePlate($v): ?string {
        $s = strtoupper(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = preg_replace('/[^A-Z0-9\- ]/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?: null;
    }
    private function normalizePlateKey($v): ?string {
        $s = strtoupper(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-') return null;
        return preg_replace('/[^A-Z0-9]/', '', $s) ?: null;
    }

    // === Parseos de fecha (igual que en debt_days) ===
    private function parseLegacyDate($v, string $df): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || preg_match('/^(0000-00-00|00\/00\/0000|0000\/00\/00|00-00-0000)$/', $s)) return null;

        // ISO
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            [$Y,$m,$d] = array_map('intval', explode('-', $s));
            if ($Y<1900 || $Y>2100 || $m<1 || $m>12 || $d<1 || $d>31) return null;
            return sprintf('%04d-%02d-%02d', $Y,$m,$d);
        }

        // DMY
        if ($df === 'DMY' && preg_match('#^(\d{2})[/-](\d{2})[/-](\d{4})$#', $s, $m)) {
            $d=(int)$m[1]; $M=(int)$m[2]; $Y=(int)$m[3];
            if ($Y<1900 || $Y>2100 || $M<1 || $M>12 || $d<1 || $d>31) return null;
            return sprintf('%04d-%02d-%02d', $Y,$M,$d);
        }

        // YMD con / o -
        if ($df === 'YMD' && preg_match('#^(\d{4})[/-](\d{2})[/-](\d{2})$#', $s, $m)) {
            $Y=(int)$m[1]; $M=(int)$m[2]; $d=(int)$m[3];
            if ($Y<1900 || $Y>2100 || $M<1 || $M>12 || $d<1 || $d>31) return null;
            return sprintf('%04d-%02d-%02d', $Y,$M,$d);
        }

        try {
            $dt = Carbon::parse($s);
            if ($dt->year < 1900 || $dt->year > 2100) return null;
            return $dt->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // YYYY-MM o YYYY/MM o YYYY-MM-00 -> anchor day
    private function parseMonthLike($v, string $anchorDay='01'): ?string
    {
        $s = trim((string)$v);
        if ($s === '') return null;

        if (preg_match('#^(\d{4})[/-](\d{2})$#', $s, $m)) {
            $Y=(int)$m[1]; $M=(int)$m[2];
            if ($Y<1900 || $Y>2100 || $M<1 || $M>12) return null;
            return sprintf('%04d-%02d-%s', $Y, $M, $anchorDay);
        }
        if (preg_match('#^(\d{4})-(\d{2})-00$#', $s, $m)) {
            $Y=(int)$m[1]; $M=(int)$m[2];
            if ($Y<1900 || $Y>2100 || $M<1 || $M>12) return null;
            return sprintf('%04d-%02d-%s', $Y, $M, $anchorDay);
        }
        return null;
    }

    private function toMoneyNonNeg($v): string
    {
        if ($v === null) return '0.00';
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return '0.00';
        $s = str_replace([' ', 'S/','s/','USD','$'], '', $s);
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (strpos($s, ',') !== false && strpos($s, '.') === false) {
            $s = str_replace(',', '.', $s);
        }
        $f = is_numeric($s) ? (float)$s : 0.0;
        if ($f < 0) $f = 0.0;
        return number_format($f, 2, '.', '');
    }

    private function toMoney($v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = str_replace([' ', 'S/','s/','USD','$'], '', $s);
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/',$s)) { $s=str_replace('.','',$s); $s=str_replace(',', '.', $s); }
        elseif (strpos($s, ',')!==false && strpos($s,'.')===false) { $s=str_replace(',', '.', $s); }
        return is_numeric($s) ? number_format((float)$s, 2, '.', '') : null;
    }

    private function toSmallInt($v, ?int $default=null): ?int
    {
        if ($v===null) return $default;
        $s = preg_replace('/[^0-9\-]/', '', (string)$v);
        if ($s==='' || $s==='-') return $default;
        $n = (int)$s;
        if ($n<0) $n=0; if ($n>65535) $n=65535;
        return $n;
    }
}
