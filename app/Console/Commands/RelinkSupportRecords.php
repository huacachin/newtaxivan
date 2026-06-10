<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\SupportRecordRelinker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RelinkSupportRecords extends Command
{
    protected $signature = 'vehicles:relink-support
                            {plate? : Placa del vehículo a procesar}
                            {--all : Procesa todos los vehículos activos}';

    protected $description = 'Re-vincula salidas, pagos y deudas registrados como apoyo cuya placa ya existe como vehículo activo';

    public function handle(SupportRecordRelinker $relinker): int
    {
        $plate = $this->argument('plate');

        if (! $plate && ! $this->option('all')) {
            $this->error('Indica una placa o usa --all para procesar todos los vehículos activos.');

            return self::FAILURE;
        }

        if ($plate) {
            $needle = $relinker->normalizePlate($plate);
            $vehicle = Vehicle::whereRaw('REPLACE(REPLACE(UPPER(TRIM(plate))," ",""),"-","") = ?', [$needle])
                ->where('status', 'active')
                ->first();

            if (! $vehicle) {
                $this->error("No existe un vehículo activo con la placa {$plate}.");

                return self::FAILURE;
            }

            $vehicles = collect([$vehicle]);
        } else {
            $vehicles = Vehicle::active()->orderBy('plate')->get();
        }

        $totals = ['departures' => 0, 'payments' => 0, 'debt_days' => 0, 'debt_days_skipped' => 0];

        foreach ($vehicles as $vehicle) {
            $result = DB::transaction(fn () => $relinker->relink($vehicle));

            foreach ($totals as $key => $_) {
                $totals[$key] += $result[$key];
            }

            if (array_sum($result) > 0) {
                $this->line(sprintf(
                    '%s: %d salida(s), %d pago(s), %d deuda(s) re-vinculadas%s',
                    $vehicle->plate,
                    $result['departures'],
                    $result['payments'],
                    $result['debt_days'],
                    $result['debt_days_skipped'] > 0 ? " ({$result['debt_days_skipped']} deuda(s) saltadas por mes duplicado)" : ''
                ));
            }
        }

        $this->info(sprintf(
            'Total: %d salida(s), %d pago(s), %d deuda(s) re-vinculadas, %d deuda(s) saltadas.',
            $totals['departures'],
            $totals['payments'],
            $totals['debt_days'],
            $totals['debt_days_skipped']
        ));

        return self::SUCCESS;
    }
}
