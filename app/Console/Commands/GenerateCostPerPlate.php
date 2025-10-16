<?php

namespace App\Console\Commands;

use App\Services\CostPerPlateGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateCostPerPlate extends Command
{
    protected $signature = 'cost-per-plate:generate {--date= : YYYY-MM-DD (opcional; por defecto hoy)}';
    protected $description = 'Genera costos por placa para el mes de la fecha indicada (por defecto hoy)';

    public function handle(CostPerPlateGenerator $generator)
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();

        $res = $generator->generateForMonth($date);

        if ($res['skipped'] ?? false) {
            $this->warn('No hubo base para generar (mes anterior sin datos).');
            Log::info("Ha fallado la generación de costos por placa");
            return self::SUCCESS;
        }

        $this->info("Generado. Mensual: {$res['monthly']} | Diario: {$res['daily']}");
        Log::info("Costos por placas generados con éxito.");
        return self::SUCCESS;
    }
}
