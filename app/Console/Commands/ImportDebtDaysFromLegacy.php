<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportDebtDaysFromLegacy extends Command
{
    protected $signature = 'taxivan:migrate-debt-days
        {--source=huaca_deuda_dias : Tabla legacy en la misma BD}
        {--limit=0 : Límite de filas (0 = sin límite)}
        {--chunk=1000 : Tamaño de chunk para procesar}
        {--dry-run=0 : Simular sin escribir}
        {--col-plate=placa : Columna legacy con la placa}
    ';

    protected $description = 'Importa/actualiza debt_days desde huaca_deuda_dias, resolviendo vehicle_id, guardando legacy_plate, marcando is_support y recalculando days/days_late.';

    public function handle(): int
    {
        $source   = (string) $this->option('source');
        $limit    = (int) $this->option('limit');
        $chunk    = max(100, (int) $this->option('chunk'));
        $dryRun   = (bool) $this->option('dry-run');
        $colPlate = (string) $this->option('col-plate');

        // Validaciones
        if (!Schema::hasTable($source))        { $this->error("No existe la tabla origen: {$source}"); return self::FAILURE; }
        if (!Schema::hasTable('debt_days'))    { $this->error("No existe la tabla destino: debt_days"); return self::FAILURE; }
        if (!Schema::hasTable('vehicles'))     { $this->error("No existe la tabla vehicles");           return self::FAILURE; }

        // Columnas destino (para intersectar payload)
        $destCols = collect(Schema::getColumnListing('debt_days'))->flip();

        // Expected cols en legacy (no aborta si faltan, sólo avisa)
        $expected = array_merge(
            ['id','fecha','fecha2','dias','total','exonera','detalleexo','amortiza','condicion','diasretra'],
            array_map(fn($i) => "d{$i}", range(1,31)),
            [$colPlate]
        );
        $missing = array_values(array_filter($expected, fn($c) => !Schema::hasColumn($source, $c)));
        if ($missing) {
            $this->warn("Columnas faltantes en {$source}: ".implode(', ', $missing).". Se pondrá NULL/'' donde falten.");
        }

        // Campos actualizables en upsert (sólo los que existan)
        $updateable = collect([
            'vehicle_id','legacy_plate','is_support',
            'days','total','date','exonerated','detail_exonerated','amortized','condition','days_late',
        ])->merge(array_map(fn($i) => "d{$i}", range(1,31)))
            ->filter(fn($c) => $destCols->has($c))
            ->values()->all();

        // Clave de upsert: preferimos ['date','legacy_plate'] si están, sino ['id']
        $uniqueBy = $destCols->has('date') && $destCols->has('legacy_plate') ? ['date','legacy_plate'] : ['id'];

        // Cache de vehículos (plateKey => [id,status])
        $vehicleMap = [];
        DB::table('vehicles')->select('id','plate','status')->orderBy('id')->chunk(5000, function($rows) use (&$vehicleMap) {
            foreach ($rows as $v) {
                $vehicleMap[$this->normalizePlateKey($v->plate)] = ['id' => (int)$v->id, 'status' => (string)$v->status];
            }
        });

        // Base query
        $base = DB::table($source)->orderBy('id');
        if ($limit > 0) $base->limit($limit);

        $total = (clone $base)->count();
        $this->info("Importando {$total} filas desde {$source} → debt_days ".($dryRun ? '(dry-run)' : ''));

        $created=0; $updated=0; $errors=0; $processed=0;
        $now = now();

        $bar = $this->output->createProgressBar(max(1, (int)ceil($total / $chunk)));
        $bar->start();

        (clone $base)->chunk($chunk, function($rows) use (
            &$created,&$updated,&$errors,&$processed,$now,$destCols,$updateable,$uniqueBy,$dryRun,$bar,$vehicleMap,$colPlate,$source
        ) {
            $batch = [];
            $keysForExisting = [];

            foreach ($rows as $r) {
                $processed++;

                // Fecha: usamos `fecha` (si viene inválida → null; en ese caso, saltamos fila)
                $date = $this->parseDate($r->fecha ?? null);
                if (!$date) {
                    $this->warn("Saltando (id={$r->id}): fecha inválida");
                    continue;
                }

                // Placa legacy
                $legacyPlateVisible = $this->normalizePlateVisible($r->{$colPlate} ?? null); // para guardar
                $plateKey           = $this->normalizePlateKey($r->{$colPlate} ?? null);     // para matching
                $vehicleId          = null;
                $isSupport          = 1; // default apoyo

                if ($plateKey && isset($vehicleMap[$plateKey])) {
                    $veh = $vehicleMap[$plateKey];
                    if (isset($veh['status']) && strtolower((string)$veh['status']) === 'active') {
                        $vehicleId = (int)$veh['id'];
                        $isSupport = 0;
                    } else {
                        // existe en vehicles pero inactivo → apoyo
                        $vehicleId = null;
                        $isSupport = 1;
                    }
                }

                // d1..d31 (guardamos tal cual, saneando texto y a mayúsculas)
                $d = [];
                for ($i = 1; $i <= 31; $i++) {
                    $col = "d{$i}";
                    $d[$col] = $this->cleanCell($r->$col ?? null);
                }

                // Recalcular days (P) y days_late (deuda)
                [$paidDays, $debtDays] = $this->computeDayCounters($d);

                // Montos (no negativos)
                $total  = $this->toMoneyNonNegative($r->total   ?? 0);
                $amort  = $this->toMoneyNonNegative($r->amortiza ?? 0);
                $exonera  = $this->toMoneyNonNegative($r->exonera ?? 0);

                // Otros campos
                $payload = [
                    'id'                => (int)($r->id ?? 0) ?: null, // por si quieres preservar id cuando uniqueBy sea ['id']
                    'vehicle_id'        => $vehicleId,
                    'legacy_plate'      => $legacyPlateVisible,
                    'is_support'        => $isSupport,
                    'date'              => $date,
                    'days'              => $this->toUInt16($paidDays),
                    'days_late'         => $this->toUInt16($debtDays),
                    'total'             => $total,
                    'amortized'         => $amort,
                    'exonerated'        => $exonera,
                    'detail_exonerated' => $this->trimOrNull($r->detalleexo ?? null),
                    'condition'         => $this->cleanCondition($r->condicion ?? null),
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];

                // Añadir d1..d31
                $payload = array_merge($payload, $d);

                // Intersectar con columnas reales
                $payload = array_intersect_key($payload, $destCols->toArray());

                // Guardar para batch
                $batch[] = $payload;

                // Para métricas de created/updated
                if ($uniqueBy === ['date','legacy_plate']) {
                    $keysForExisting[] = ['date' => $payload['date'], 'legacy_plate' => $payload['legacy_plate']];
                } else {
                    $keysForExisting[] = $payload['id'] ?? null;
                }
            }

            if (empty($batch)) { $bar->advance(); return; }
            if ($dryRun)       { $bar->advance(); return; }

            // Detectar existentes (para contabilidad de created/updated)
            $existingSet = [];
            if ($uniqueBy === ['date','legacy_plate']) {
                // Buscar existentes por (date, legacy_plate)
                $dates = array_unique(array_column($batch, 'date'));
                // Recortamos por fechas del batch y luego armamos set llave compuesta
                DB::table('debt_days')
                    ->whereIn('date', $dates)
                    ->select('date','legacy_plate')
                    ->orderBy('date')
                    ->chunk(5000, function($rows) use (&$existingSet) {
                        foreach ($rows as $x) {
                            $existingSet[$x->date.'|'.$x->legacy_plate] = true;
                        }
                    });
            } else {
                $ids = array_values(array_filter($keysForExisting, fn($v) => !is_null($v)));
                if ($ids) {
                    DB::table('debt_days')->whereIn('id', $ids)->pluck('id')->each(function($id) use (&$existingSet){
                        $existingSet[(int)$id] = true;
                    });
                }
            }

            try {
                DB::table('debt_days')->upsert($batch, $uniqueBy, $updateable);

                // Contabilidad
                foreach ($batch as $row) {
                    $key = $uniqueBy === ['date','legacy_plate']
                        ? ($row['date'].'|'.($row['legacy_plate'] ?? ''))
                        : ($row['id'] ?? null);

                    if ($key && isset($existingSet[$key])) {
                        $updated++;
                    } else {
                        $created++;
                    }
                }

            } catch (\Throwable $e) {
                // Fallback fila por fila (para localizar errores)
                foreach ($batch as $row) {
                    try {
                        DB::table('debt_days')->upsert([$row], $uniqueBy, $updateable);

                        $key = $uniqueBy === ['date','legacy_plate']
                            ? ($row['date'].'|'.($row['legacy_plate'] ?? ''))
                            : ($row['id'] ?? null);

                        if ($key && isset($existingSet[$key])) $updated++; else $created++;

                    } catch (\Throwable $ee) {
                        $errors++;
                        $this->warn(sprintf(
                            "Error en upsert (date=%s plate=%s): %s",
                            $row['date'] ?? 'null',
                            $row['legacy_plate'] ?? 'null',
                            $ee->getMessage()
                        ));
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

    // ================= Helpers =================

    private function trimOrNull($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;
        return $s;
    }

    private function parseDate($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') return null;

        $zero = ['0000-00-00','00/00/0000','0000/00/00','00-00-0000','0000-00-00 00:00:00'];
        if (in_array($s, $zero, true)) return null;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            [$Y,$m,$d] = array_map('intval', explode('-', $s));
            if ($Y < 1900 || $Y > 2100 || $m === 0 || $d === 0) return null;
            return sprintf('%04d-%02d-%02d', $Y,$m,$d);
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m)) {
            $d=(int)$m[1]; $M=(int)$m[2]; $Y=(int)$m[3];
            if ($Y < 1900 || $Y > 2100 || $M===0 || $d===0) return null;
            return sprintf('%04d-%02d-%02d', $Y,$M,$d);
        }
        if (preg_match('#^(\d{2})-(\d{2})-(\d{4})$#', $s, $m)) {
            $d=(int)$m[1]; $M=(int)$m[2]; $Y=(int)$m[3];
            if ($Y < 1900 || $Y > 2100 || $M===0 || $d===0) return null;
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

    // Placa visible (para guardar): mayúsculas, conserva guiones y espacios
    private function normalizePlateVisible($v): ?string
    {
        $s = strtoupper(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = preg_replace('/[^A-Z0-9\- ]/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?: null;
    }

    // Key de placa para matching: sólo A-Z0-9, sin guiones ni espacios
    private function normalizePlateKey($v): ?string
    {
        $s = strtoupper(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-') return null;
        $s = preg_replace('/[^A-Z0-9]/', '', $s);
        return $s ?: null;
    }

    // Sanea celda dN: mayúsculas, sin espacios extremos, limita longitud (por si viene "X1", números, etc.)
    private function cleanCell($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?') return null;
        $s = strtoupper($s);
        // permite P, NT, X, X1, 1..n, etc. (máx 4 chars)
        return mb_substr($s, 0, 4, 'UTF-8');
    }

    // Cuenta P y deuda a partir de d1..d31
    private function computeDayCounters(array $dCols): array
    {
        $paid = 0; $debt = 0;
        for ($i = 1; $i <= 31; $i++) {
            $val = strtoupper(trim((string)($dCols["d{$i}"] ?? '')));
            if ($val === '') continue;
            if ($val === 'P') { $paid++; continue; }
            if ($val === 'NT') { continue; } // no deuda
            // cualquier otro valor (X, X1, 1, 2, etc.) cuenta como 1 día de deuda
            $debt++;
        }
        return [$paid, $debt];
    }

    private function toUInt16($v): int
    {
        $n = (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT);
        if ($n < 0) $n = 0;
        if ($n > 65535) $n = 65535;
        return $n;
    }

    private function toMoneyNonNegative($v): string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '?' || $s === '-') $s = '0';
        $s = str_replace([' ', 'S/','s/','USD','$'], '', $s);
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (strpos($s, ',') !== false && strpos($s, '.') === false) {
            $s = str_replace(',', '.', $s);
        }
        $n = is_numeric($s) ? (float)$s : 0.0;
        if ($n < 0) $n = 0.0;
        return number_format($n, 2, '.', '');
    }

    private function toTinyBool($v): int
    {
        $s = strtolower(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-') return 0;
        return in_array($s, ['1','true','si','sí','yes','y','t','on'], true) ? 1 : 0;
    }

    private function cleanCondition($v): ?string
    {
        $s = strtoupper(trim((string)$v));
        if ($s === '' || $s === '?' || $s === '-') return null;
        // Deja letras/números y reduce longitud
        $s = preg_replace('/[^A-Z0-9]/', '', $s);
        return mb_substr($s, 0, 4, 'UTF-8') ?: null; // ej: DT, GN, EX, EX5
    }
}
