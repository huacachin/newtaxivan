<?php

namespace App\Exports;

use App\Livewire\Draws\Index;
use App\Models\Vehicle;
use App\Services\DrawService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Excel de una simulación de Sorteos (no se guarda nada). Con la misma
 * semilla, mínimo y máximo que la grilla rehace exactamente la misma
 * simulación: hoja "Grilla" (vehículos x días) y hoja "Orden por día".
 */
class DrawSimulationExport implements WithMultipleSheets
{
    public function __construct(
        protected int $year,
        protected int $month,
        protected int $seed,
        protected int $min,
        protected int $max,
    ) {}

    public function sheets(): array
    {
        $days = DrawService::workingDaysOfMonth($this->year, $this->month);
        $service = new DrawService(new Randomizer(new Mt19937($this->seed)));
        $simulated = $service->simulateMonth(array_column($days, 'date'), $this->min, $this->max);

        $vehicleIds = collect($simulated)->flatMap(fn (array $day) => array_keys($day))->unique()->values()->all();
        $vehicles = Vehicle::whereIn('id', $vehicleIds)->with('driver:id,name')
            ->orderBy('sort_order')->orderBy('plate')
            ->get(['id', 'sort_order', 'plate', 'condition', 'driver_id']);

        $title = 'SIMULACIÓN DE SORTEOS - '.mb_strtoupper(Index::MONTHS[$this->month]).' '.$this->year;
        $subtitle = "Entre {$this->min} y {$this->max} participantes por día al azar; al resto se le quitó el check. No es un sorteo real ni se guardó.";

        return [
            new DrawSimulationGridSheet($title, $subtitle, $days, $vehicles, $simulated, DrawService::summarize($simulated)),
            new DrawSimulationOrderSheet($title, $days, $simulated),
        ];
    }
}
