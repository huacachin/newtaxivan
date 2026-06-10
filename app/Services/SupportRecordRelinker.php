<?php

namespace App\Services;

use App\Models\DebtDay;
use App\Models\Departure;
use App\Models\Payment;
use App\Models\Vehicle;

class SupportRecordRelinker
{
    /**
     * Re-vincula registros huérfanos (salidas, pagos y deudas por días) cuya
     * legacy_plate coincide con la placa del vehículo, desde su entry_date.
     *
     * Solo aplica a vehículos activos y no cesados; en otro caso no hace nada.
     *
     * Convenciones por tabla (homologadas con AddDeparture/AddPayment/GenerateDebtsDays):
     *  - departures: vinculada => vehicle_id, is_support=0, legacy_plate=NULL
     *  - payments:   vinculada => vehicle_id (legacy_plate se conserva siempre)
     *  - debt_days:  vinculada => vehicle_id, is_support=0 (legacy_plate se conserva);
     *                si ya existe fila del vehículo para ese mes, la huérfana se
     *                salta para no violar el índice único (date, vehicle_id, legacy_plate)
     *
     * @return array{departures:int,payments:int,debt_days:int,debt_days_skipped:int}
     */
    public function relink(Vehicle $vehicle): array
    {
        $result = ['departures' => 0, 'payments' => 0, 'debt_days' => 0, 'debt_days_skipped' => 0];

        $today = now(config('app.timezone', 'America/Lima'))->startOfDay();
        $ceased = $vehicle->termination_date && $vehicle->termination_date < $today;
        if ($vehicle->status !== 'active' || $ceased) {
            return $result;
        }

        $needle = $this->normalizePlate($vehicle->plate);
        if ($needle === '') {
            return $result;
        }

        // Compara legacy_plate normalizada igual que la placa (sin espacios ni guiones)
        $plateExpr = 'REPLACE(REPLACE(UPPER(TRIM(legacy_plate))," ",""),"-","")';

        $entry = $vehicle->entry_date; // límite inferior; null = sin límite

        $result['departures'] = Departure::query()
            ->whereNull('vehicle_id')
            ->whereRaw("$plateExpr = ?", [$needle])
            ->when($entry, fn ($q) => $q->where('date', '>=', $entry->toDateString()))
            ->update([
                'vehicle_id' => $vehicle->id,
                'is_support' => false,
                'legacy_plate' => null,
            ]);

        $result['payments'] = Payment::query()
            ->whereNull('vehicle_id')
            ->whereRaw("$plateExpr = ?", [$needle])
            ->when($entry, fn ($q) => $q->whereRaw('COALESCE(date_payment, date_register) >= ?', [$entry->toDateString()]))
            ->update([
                'vehicle_id' => $vehicle->id,
                'is_support' => false,
            ]);

        $existingMonths = DebtDay::where('vehicle_id', $vehicle->id)
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $candidates = DebtDay::query()
            ->whereNull('vehicle_id')
            ->whereRaw("$plateExpr = ?", [$needle])
            ->when($entry, fn ($q) => $q->where('date', '>=', $entry->copy()->startOfMonth()->toDateString()))
            ->get();

        foreach ($candidates as $row) {
            if (in_array($row->date->toDateString(), $existingMonths, true)) {
                $result['debt_days_skipped']++;

                continue;
            }

            $row->update(['vehicle_id' => $vehicle->id, 'is_support' => false]);
            $result['debt_days']++;
        }

        return $result;
    }

    /**
     * Mensaje para el usuario con el resumen de lo re-vinculado, o null si no hubo cambios.
     */
    public function summaryMessage(array $result): ?string
    {
        $parts = [];
        if ($result['departures'] > 0) {
            $parts[] = "{$result['departures']} salida(s)";
        }
        if ($result['payments'] > 0) {
            $parts[] = "{$result['payments']} pago(s)";
        }
        if ($result['debt_days'] > 0) {
            $parts[] = "{$result['debt_days']} deuda(s) por días";
        }

        if (empty($parts)) {
            return null;
        }

        $msg = 'Se re-vincularon registros de apoyo con esta placa: '.implode(', ', $parts).'.';

        if ($result['debt_days_skipped'] > 0) {
            $msg .= " ({$result['debt_days_skipped']} deuda(s) no se re-vincularon porque el vehículo ya tiene deuda ese mes)";
        }

        return $msg;
    }

    public function normalizePlate(?string $plate): string
    {
        return str_replace([' ', '-'], '', strtoupper(trim((string) $plate)));
    }
}
