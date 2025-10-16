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
     * Reglas:
     *  - Base por vehículo: último día NO domingo del mes anterior (diario); fallback mensual.
     *  - Domingos del mes destino => 0.
     *  - Limpia el mes destino antes de insertar.
     */
    public function generateForMonth(Carbon $date): array
    {
        $dest = $date->copy()->startOfMonth();
        $src  = $dest->copy()->subMonth();

        $destYear  = (int) $dest->year;
        $destMonth = (int) $dest->month;

        $srcYear   = (int) $src->year;
        $srcMonth  = (int) $src->month;

        // Último día NO domingo del mes anterior
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

        // Mensual del mes anterior (fallback y order)
        $srcMonthly = CostPerPlate::query()
            ->where('year', $srcYear)
            ->where('month', $srcMonth)
            ->get(['vehicle_id','amount','order'])
            ->keyBy('vehicle_id');

        if ($lastDaily->isEmpty() && $srcMonthly->isEmpty()) {
            return ['monthly' => 0, 'daily' => 0, 'skipped' => true];
        }

        $daysInDest = $dest->copy()->endOfMonth()->day;
        $now = now();

        $monthlyPayload = [];
        $dailyPayload   = [];

        $vehicleIds = collect($lastDaily->keys())->merge($srcMonthly->keys())->unique()->values();

        foreach ($vehicleIds as $vid) {
            $amount = null;
            if (isset($lastDaily[$vid])) {
                $amount = (float) $lastDaily[$vid]->amount;
            } elseif (isset($srcMonthly[$vid])) {
                $amount = (float) $srcMonthly[$vid]->amount;
            }
            if ($amount === null) {
                continue;
            }

            $order = isset($srcMonthly[$vid]) ? (int) $srcMonthly[$vid]->order : 0;

            $monthlyPayload[] = [
                'vehicle_id' => $vid,
                'year'       => $destYear,
                'month'      => $destMonth,
                'amount'     => $amount,
                'order'      => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            for ($day = 1; $day <= $daysInDest; $day++) {
                $dateObj = Carbon::create($destYear, $destMonth, $day);
                $dailyPayload[] = [
                    'vehicle_id' => $vid,
                    'year'       => $destYear,
                    'month'      => $destMonth,
                    'date'       => $dateObj->toDateString(),
                    'amount'     => $dateObj->isSunday() ? 0.0 : $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (empty($monthlyPayload) && empty($dailyPayload)) {
            return ['monthly' => 0, 'daily' => 0, 'skipped' => true];
        }

        DB::transaction(function () use ($monthlyPayload, $dailyPayload, $destYear, $destMonth) {
            // borrar destino primero
            CostPerPlateDay::where('year', $destYear)->where('month', $destMonth)->delete();
            CostPerPlate::where('year', $destYear)->where('month', $destMonth)->delete();

            // insertar
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
