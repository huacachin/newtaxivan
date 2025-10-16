<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateDebtsDays extends Command
{
    protected $signature = 'debt-days:generate {--year=} {--month=}';
    protected $description = 'Genera deudas por días no trabajados (por vehículo) para un mes, marcando X (sin salida) o X1 (DT con salida pero sin pago), sin contar domingos.';

    // Tablas/campos
    private const T_DEPARTURES   = 'departures';
    private const D_DATE         = 'date';
    private const D_VEHICLE_ID   = 'vehicle_id';
    private const D_LEGACY       = 'legacy_plate';
    private const D_IS_SUPPORT   = 'is_support';

    private const T_CP_DAY       = 'cost_per_plate_days';
    private const CPD_VEHICLE_ID = 'vehicle_id';
    private const CPD_DATE       = 'date';
    private const CPD_AMOUNT     = 'amount';

    private const T_VEHICLES     = 'vehicles';
    private const V_ID           = 'id';
    private const V_CONDITION    = 'condition'; // EX => excluir; DT => generar sin deuda, marcar X1 cuando aplique

    private const T_PAYMENTS     = 'payments';
    private const P_DATE_PAY     = 'date_payment';
    private const P_PLATE        = 'legacy_plate';
    private const P_TYPE         = 'type';

    private const T_DEBTS        = 'debt_days';

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

        // 1) Días trabajados por vehículo (al menos 1 departure ese día)
        $this->line('- Cargando departures (días trabajados)...');
        $deps = DB::table(self::T_DEPARTURES)
            ->select([
                self::D_VEHICLE_ID . ' as vehicle_id',
                DB::raw('DATE(' . self::D_DATE . ') as d'),
            ])
            ->whereBetween(self::D_DATE, [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('vehicle_id', 'd')
            ->get();

        $workedDaysByVehicle = [];
        foreach ($deps as $row) {
            $vid = (int) $row->vehicle_id;
            if ($vid <= 0) continue;
            $workedDaysByVehicle[$vid][$row->d] = true;
        }

        // 2) Costos diarios del mes
        $this->line('- Cargando cost_per_plate_days (costos diarios)...');
        $cpd = DB::table(self::T_CP_DAY)
            ->select([self::CPD_VEHICLE_ID.' as vehicle_id', self::CPD_DATE.' as date', self::CPD_AMOUNT.' as amount'])
            ->whereYear(self::CPD_DATE, $year)
            ->whereMonth(self::CPD_DATE, $month)
            ->get();

        $costByVehicleByDate = [];
        foreach ($cpd as $row) {
            $vid = (int) $row->vehicle_id;
            if ($vid <= 0) continue;
            $costByVehicleByDate[$vid][$row->date] = (float) $row->amount;
        }

        // 3) Candidatos: unión de los que tienen departures o costos
        $vehicleIds = collect(array_keys($workedDaysByVehicle))
            ->merge(array_keys($costByVehicleByDate))
            ->filter(fn($vid) => (int)$vid > 0)
            ->unique()
            ->values();

        if ($vehicleIds->isEmpty()) {
            $this->warn('No hay vehículos con datos en este mes.');
            return self::SUCCESS;
        }

        // 4) conditions desde vehicles: excluir EX, incluir DT y demás
        $this->line('- Validando vehículos (excluir condition=EX; incluir DT)...');
        $vehicles = DB::table(self::T_VEHICLES)
            ->whereIn(self::V_ID, $vehicleIds)
            ->get([self::V_ID.' as id', self::V_CONDITION.' as condition'])
            ->keyBy('id');

        // Filtra: fuera EX y que existan
        $vehicleIds = $vehicleIds->filter(function($vid) use ($vehicles){
            if (!isset($vehicles[$vid])) return false;         // no existe en vehicles
            $cond = $vehicles[$vid]->condition ?? null;
            return $cond !== 'EX';                              // EX: excluir completamente
        })->values();

        if ($vehicleIds->isEmpty()) {
            $this->warn('Todos los candidatos eran EX o inexistentes. Nada que generar.');
            return self::SUCCESS;
        }

        // 5) legacy_plate / is_support: última departure del MES
        $this->line('- Cargando plate/support de última departure del mes...');
        $latestInMonth = DB::table(self::T_DEPARTURES.' as d')
            ->join(DB::raw('(
                SELECT '.self::D_VEHICLE_ID.' as vehicle_id, MAX('.self::D_DATE.') as maxd
                FROM '.self::T_DEPARTURES.'
                WHERE '.self::D_DATE.' BETWEEN ? AND ?
                GROUP BY '.self::D_VEHICLE_ID.'
            ) as m'), function($join){
                $join->on('d.'.self::D_VEHICLE_ID, '=', 'm.vehicle_id')
                    ->on('d.'.self::D_DATE, '=', 'm.maxd');
            })
            ->setBindings([$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereIn('d.'.self::D_VEHICLE_ID, $vehicleIds)
            ->get([
                'd.'.self::D_VEHICLE_ID.' as vehicle_id',
                'd.'.self::D_LEGACY.' as legacy_plate',
                'd.'.self::D_IS_SUPPORT.' as is_support',
            ])
            ->keyBy('vehicle_id');

        // 6) Para los que no tuvieron departure en el mes: última departure histórica (<= fin de mes)
        $missingMetaFor = $vehicleIds->filter(fn($vid) => !isset($latestInMonth[$vid]))->values();
        $latestAny = collect();
        if ($missingMetaFor->isNotEmpty()) {
            $this->line('- Cargando plate/support de última departure histórica...');
            $latestAny = DB::table(self::T_DEPARTURES.' as d')
                ->join(DB::raw('(
                    SELECT '.self::D_VEHICLE_ID.' as vehicle_id, MAX('.self::D_DATE.') as maxd
                    FROM '.self::T_DEPARTURES.'
                    WHERE '.self::D_DATE.' <= ?
                    GROUP BY '.self::D_VEHICLE_ID.'
                ) as m'), function($join){
                    $join->on('d.'.self::D_VEHICLE_ID, '=', 'm.vehicle_id')
                        ->on('d.'.self::D_DATE, '=', 'm.maxd');
                })
                ->setBindings([$end->toDateTimeString()])
                ->whereIn('d.'.self::D_VEHICLE_ID, $missingMetaFor)
                ->get([
                    'd.'.self::D_VEHICLE_ID.' as vehicle_id',
                    'd.'.self::D_LEGACY.' as legacy_plate',
                    'd.'.self::D_IS_SUPPORT.' as is_support',
                ])
                ->keyBy('vehicle_id');
        }

        // 7) Pagos del mes: por legacy_plate + date_payment, tipos PAGO/RETRASO
        $this->line('- Cargando pagos del mes (PAGO/RETRASO) por placa y fecha de pago...');
        $pays = DB::table(self::T_PAYMENTS)
            ->select([self::P_PLATE.' as plate', DB::raw('DATE('.self::P_DATE_PAY.') as d')])
            ->whereBetween(self::P_DATE_PAY, [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereIn(self::P_TYPE, ['PAGO', 'RETRASO'])
            ->get();

        $paidByPlateByDate = [];
        foreach ($pays as $row) {
            if (!$row->plate) continue;
            $paidByPlateByDate[$row->plate][$row->d] = true;
        }

        // 8) Borrar previos del mes/año en debt_days
        $this->line('- Eliminando registros previos del mes en debt_days...');
        DB::table(self::T_DEBTS)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->delete();

        // 9) Construcción e inserción
        $now = now();
        $payload = [];

        foreach ($vehicleIds as $vid) {
            $vid = (int) $vid;

            $cond = $vehicles[$vid]->condition ?? null; // (no EX aquí)
            $isDT = ($cond === 'DT');                  // DT: generar sin deuda, pero marcar X1 si hubo salida sin pago

            // Plate/Support (última salida mes o histórica)
            $meta = $latestInMonth[$vid] ?? $latestAny[$vid] ?? null;
            $legacyPlate = $meta->legacy_plate ?? null;
            $isSupport   = isset($meta->is_support) ? (int) $meta->is_support : 0;

            $row = [
                'vehicle_id'        => $vid,
                'legacy_plate'      => $legacyPlate,
                'is_support'        => $isSupport,
                'days'              => 0,
                'total'             => 0.0,
                'date'              => $start->toDateString(), // 1er día del mes
                'exonerated'        => 0,
                'detail_exonerated' => null,
                'amortized'         => 0,
                'condition'         => $cond,
                'days_late'         => 0,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            // d1..d31 => null
            for ($i = 1; $i <= 31; $i++) {
                $row['d'.$i] = null;
            }

            if ($isDT) {
                // DT: sin deuda. Si hubo salida y NO hay pago ese día => X1
                if ($legacyPlate) {
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $date = Carbon::create($year, $month, $day);
                        if ($date->isSunday()) continue; // no considerar domingos
                        $dstr   = $date->toDateString();
                        $worked = isset($workedDaysByVehicle[$vid][$dstr]);
                        if ($worked) {
                            $paid = isset($paidByPlateByDate[$legacyPlate][$dstr]);
                            if (!$paid) {
                                $row['d'.$day] = 'X1';
                            }
                        }
                    }
                }
                // total/días permanecen en 0
                $payload[] = $row;
                continue;
            }

            // No DT/EX: calcular deuda por días sin salida (sin domingos)
            $daysLate = 0;
            $totalDebt = 0.0;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($year, $month, $day);
                if ($date->isSunday()) continue; // no contar domingos

                $dstr   = $date->toDateString();
                $worked = isset($workedDaysByVehicle[$vid][$dstr]);

                if (!$worked) {
                    $row['d'.$day] = 'X';
                    $daysLate++;
                    $totalDebt += (float) ($costByVehicleByDate[$vid][$dstr] ?? 0.0);
                }
            }

            $row['days_late'] = $daysLate;
            $row['days']      = $daysLate;
            $row['total']     = $totalDebt;

            $payload[] = $row;
        }

        if (empty($payload)) {
            $this->warn('No se construyeron filas (tras filtrar vehicles/condición).');
            return self::SUCCESS;
        }

        $this->line('- Insertando en debt_days...');
        foreach (array_chunk($payload, 1000) as $chunk) {
            DB::table(self::T_DEBTS)->insert($chunk);
        }

        $this->info('¡Listo! Filas generadas: '.count($payload));
        return self::SUCCESS;
    }
}
