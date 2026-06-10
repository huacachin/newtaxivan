<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\CostPerPlateGenerator;
use App\Services\SupportRecordRelinker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RelinkSupportRecords extends Command
{
    protected $signature = 'vehicles:relink-support
                            {plate? : Placa del vehículo a procesar}
                            {--all : Procesa todos los vehículos activos}';

    protected $description = 'Re-vincula salidas, pagos y deudas registrados como apoyo cuya placa ya existe como vehículo activo, y completa sus costos por placa del mes en curso';

    public function handle(SupportRecordRelinker $relinker, CostPerPlateGenerator $costGenerator): int
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

        $totals = ['departures' => 0, 'payments' => 0, 'debt_days' => 0, 'debt_days_skipped' => 0, 'cost_days' => 0];

        foreach ($vehicles as $vehicle) {
            [$result, $costs] = DB::transaction(fn () => [
                $relinker->relink($vehicle),
                $costGenerator->generateForVehicle($vehicle),
            ]);

            foreach ($result as $key => $value) {
                $totals[$key] += $value;
            }
            $totals['cost_days'] += $costs['daily'];

            if (array_sum($result) > 0 || $costs['monthly'] > 0 || $costs['daily'] > 0) {
                $this->line(sprintf(
                    '%s: %d salida(s), %d pago(s), %d deuda(s) re-vinculadas, %d día(s) de costo generados%s',
                    $vehicle->plate,
                    $result['departures'],
                    $result['payments'],
                    $result['debt_days'],
                    $costs['daily'],
                    $result['debt_days_skipped'] > 0 ? " ({$result['debt_days_skipped']} deuda(s) saltadas por mes duplicado)" : ''
                ));
            }
        }

        $this->info(sprintf(
            'Total: %d salida(s), %d pago(s), %d deuda(s) re-vinculadas, %d deuda(s) saltadas, %d día(s) de costo generados.',
            $totals['departures'],
            $totals['payments'],
            $totals['debt_days'],
            $totals['debt_days_skipped'],
            $totals['cost_days']
        ));

        return self::SUCCESS;
    }
}
