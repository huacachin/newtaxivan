<?php

namespace App\Services;

use App\Models\CostPerPlate;
use App\Models\CostPerPlateDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CostPerPlateGenerator
{
    /**
     * Genera para el mes de $date (1er día del mes de $date).
     * Lógica homologada con legacy:
     *  - Incluye todos los vehículos activos
     *  - Base: último día NO domingo del mes anterior; fallback 15.00
     *  - Todos los días del mes (incluyendo domingos) con el mismo monto
     *  - Limpia el mes destino antes de insertar
     */
    public function generateForMonth(Carbon $date): array
    {
        $dest = $date->copy()->startOfMonth();
        $src  = $dest->copy()->subMonth();

        $destYear  = (int) $dest->year;
        $destMonth = (int) $dest->month;

        $srcYear   = (int) $src->year;
        $srcMonth  = (int) $src->month;

        // Todos los vehículos activos
        $vehicles = DB::table('vehicles')
            ->where('status', 'active')
            ->select(['id', 'sort_order'])
            ->orderBy('sort_order')
            ->get();

        if ($vehicles->isEmpty()) {
            return ['monthly' => 0, 'daily' => 0, 'skipped' => true];
        }

        // Último día NO domingo del mes anterior por vehículo
        $lastDaily = DB::table('cost_per_plate_days as d')
            ->select('d.vehicle_id', 'd.amount')
            ->whereYear('d.date', $srcYear)
            ->whereMonth('d.date', $srcMonth)
            ->whereRaw(
                'd.date = (
                    SELECT MAX(dd.date)
                    FROM cost_per_plate_days dd
                    WHERE dd.vehicle_id = d.vehicle_id
                      AND YEAR(dd.date) = ?
                      AND MONTH(dd.date) = ?
                      AND DAYOFWEEK(dd.date) <> 1
                )',
                [$srcYear, $srcMonth]
            )
            ->get()
            ->keyBy('vehicle_id');

        $daysInDest = $dest->copy()->endOfMonth()->day;
        $now = now();

        $monthlyPayload = [];
        $dailyPayload   = [];

        foreach ($vehicles as $v) {
            $vid = (int) $v->id;

            // Monto: último día del mes anterior o fallback 15.00
            $amount = isset($lastDaily[$vid])
                ? (float) $lastDaily[$vid]->amount
                : 15.00;

            $order = (int) ($v->sort_order ?? 0);

            $monthlyPayload[] = [
                'vehicle_id' => $vid,
                'year'       => $destYear,
                'month'      => $destMonth,
                'amount'     => $amount,
                'order'      => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Todos los días del mes (incluyendo domingos) con el mismo monto
            for ($day = 1; $day <= $daysInDest; $day++) {
                $dateObj = Carbon::create($destYear, $destMonth, $day);
                $dailyPayload[] = [
                    'vehicle_id' => $vid,
                    'year'       => $destYear,
                    'month'      => $destMonth,
                    'date'       => $dateObj->toDateString(),
                    'amount'     => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($monthlyPayload, $dailyPayload, $destYear, $destMonth) {
            CostPerPlateDay::where('year', $destYear)->where('month', $destMonth)->delete();
            CostPerPlate::where('year', $destYear)->where('month', $destMonth)->delete();

            foreach (array_chunk($monthlyPayload, 1000) as $chunk) {
                CostPerPlate::insert($chunk);
            }
            foreach (array_chunk($dailyPayload, 1000) as $chunk) {
                CostPerPlateDay::insert($chunk);
            }
        });

        return ['monthly' => count($monthlyPayload), 'daily' => count($dailyPayload), 'skipped' => false];
    }
}
