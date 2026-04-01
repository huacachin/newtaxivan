<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateDebtsDays extends Command
{
    protected $signature = 'debt-days:generate {--year=} {--month=}';
    protected $description = 'Genera deudas por días sin pago (homologado con legacy). Crea respaldo antes de ejecutar.';

    public function handle(): int
    {
        $year  = (int) ($this->option('year') ?: now()->year);
        $month = (int) ($this->option('month') ?: now()->month);

        if ($month < 1 || $month > 12) {
            $this->error('Mes inválido. Usa --month=1..12');
            return self::INVALID;
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth();
        $daysInMonth = (int) $end->day;

        $this->info("Generando debt_days para {$year}-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT));

        // ===== RESPALDO antes de borrar =====
        $this->line('- Creando respaldo...');
        $existing = DB::table('debt_days')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();

        $backupFile = "debt-days-backup/{$year}-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.json';
        Storage::put($backupFile, json_encode($existing));
        $this->line("  Respaldo: {$backupFile} (" . count($existing) . " registros)");

        // ===== 1) Vehículos activos (legacy: fechap='0000-00-00') =====
        $this->line('- Cargando vehículos activos...');
        $vehicles = DB::table('vehicles')
            ->where('status', 'active')
            ->select(['id', 'plate', 'sort_order', 'condition', 'entry_date'])
            ->orderBy('sort_order')
            ->get();

        if ($vehicles->isEmpty()) {
            $this->warn('No hay vehículos activos.');
            return self::SUCCESS;
        }

        // ===== 2) Pagos del mes por placa y fecha (tipo <> DEUDA) =====
        $this->line('- Cargando pagos del mes...');
        $pays = DB::table('payments')
            ->select(['legacy_plate as plate', DB::raw('DATE(date_payment) as d'), DB::raw('SUM(amount) as monto')])
            ->whereBetween('date_payment', [$start->toDateString(), $end->toDateString()])
            ->where(DB::raw('UPPER(type)'), '<>', 'DEUDA')
            ->groupBy('plate', 'd')
            ->get();

        $paidByPlateDate = [];
        foreach ($pays as $r) {
            if (!$r->plate) continue;
            $paidByPlateDate[$r->plate][$r->d] = (float)$r->monto;
        }

        // ===== 3) Salidas del mes por placa y fecha (para DT y EX5) =====
        $this->line('- Cargando salidas del mes...');
        $departures = DB::table('departures')
            ->select([DB::raw('COALESCE(vehicle_id, 0) as vid'), 'legacy_plate', DB::raw('DATE(date) as d')])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $departuresByVidDate = [];
        $departuresByPlateDate = [];
        foreach ($departures as $r) {
            $vid = (int)$r->vid;
            if ($vid > 0) {
                $departuresByVidDate[$vid][$r->d] = true;
            }
            if ($r->legacy_plate) {
                $departuresByPlateDate[$r->legacy_plate][$r->d] = true;
            }
        }

        // ===== 4) Costos diarios del mes =====
        $this->line('- Cargando costos diarios...');
        $costs = DB::table('cost_per_plate_days')
            ->select(['vehicle_id', 'date', 'amount'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $costByVidDate = [];
        foreach ($costs as $r) {
            $costByVidDate[(int)$r->vehicle_id][$r->date] = (float)$r->amount;
        }

        // ===== 5) Borrar previos =====
        $this->line('- Eliminando registros previos...');
        DB::table('debt_days')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->delete();

        // ===== 6) Generar por vehículo (lógica legacy) =====
        $this->line('- Generando deuda...');
        $now = now();
        $payload = [];

        foreach ($vehicles as $v) {
            $vid   = (int)$v->id;
            $plate = (string)$v->plate;
            $cond  = strtoupper(trim($v->condition ?? ''));

            // EX: se excluye completamente (legacy no lo inserta)
            if ($cond === 'EX') continue;

            // Fecha de inicio: si entry_date está en el mismo mes/año, empezar desde ese día
            $startDay = 1;
            if ($v->entry_date) {
                $entryDate = Carbon::parse($v->entry_date);
                if ($entryDate->year === $year && $entryDate->month === $month) {
                    $startDay = (int)$entryDate->day;
                }
            }

            // Tabla temporal (simula huaca_temdeuda del legacy)
            $tempDeuda = []; // [{date, sal}]

            for ($day = $startDay; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($year, $month, $day);
                $dstr = $date->toDateString();

                if ($date->isSunday()) continue;

                // ¿Tiene pago ese día?
                $hasPaid = isset($paidByPlateDate[$plate][$dstr]) && $paidByPlateDate[$plate][$dstr] > 0;

                if (!$hasPaid) {
                    // Sin pago: aplicar lógica según condición
                    $hasDeparture = isset($departuresByVidDate[$vid][$dstr])
                        || isset($departuresByPlateDate[$plate][$dstr]);

                    if ($cond === 'GN' || $cond === '') {
                        // GN y sin condición: día sin pago = deuda
                        $tempDeuda[] = ['date' => $dstr, 'day' => $day, 'sal' => 0];
                    } elseif ($cond === 'DT') {
                        // DT: solo si tuvo salida ese día
                        if ($hasDeparture) {
                            $tempDeuda[] = ['date' => $dstr, 'day' => $day, 'sal' => 1];
                        }
                    } elseif ($cond === 'EX5') {
                        // EX5: si tuvo salida sal=1, si no sal=0
                        $tempDeuda[] = [
                            'date' => $dstr,
                            'day'  => $day,
                            'sal'  => $hasDeparture ? 1 : 0,
                        ];
                    }
                }
            }

            // EX5: eliminar los primeros 5 registros con sal=0 (5 días de gracia)
            if ($cond === 'EX5') {
                $removed = 0;
                $tempDeuda = array_values(array_filter($tempDeuda, function ($item) use (&$removed) {
                    if ($item['sal'] === 0 && $removed < 5) {
                        $removed++;
                        return false;
                    }
                    return true;
                }));
            }

            // Construir fila de debt_days
            $row = [
                'vehicle_id'        => $vid,
                'legacy_plate'      => $plate,
                'is_support'        => 0,
                'days'              => 0,
                'total'             => 0.0,
                'date'              => $start->toDateString(),
                'exonerated'        => 0,
                'detail_exonerated' => null,
                'amortized'         => 0,
                'condition'         => $cond ?: null,
                'days_late'         => 0,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            for ($i = 1; $i <= 31; $i++) {
                $row['d' . $i] = null;
            }

            $daysCount = 0;
            $totalDebt = 0.0;

            foreach ($tempDeuda as $item) {
                $dayNum = $item['day'];
                $dstr   = $item['date'];
                $diax   = $item['sal'] === 1 ? 'X1' : 'X';

                $row['d' . $dayNum] = $diax;
                $daysCount++;

                $cost = $costByVidDate[$vid][$dstr] ?? 0.0;
                $totalDebt += $cost;
            }

            $row['days']      = $daysCount;
            $row['days_late'] = $daysCount;
            $row['total']     = round($totalDebt, 2);

            $payload[] = $row;
        }

        if (empty($payload)) {
            $this->warn('No se generaron filas.');
            return self::SUCCESS;
        }

        $this->line('- Insertando en debt_days...');
        foreach (array_chunk($payload, 1000) as $chunk) {
            DB::table('debt_days')->insert($chunk);
        }

        $this->info("Listo. Filas generadas: " . count($payload));
        $this->info("Para revertir: php artisan debt-days:rollback --year={$year} --month={$month}");

        return self::SUCCESS;
    }
}
