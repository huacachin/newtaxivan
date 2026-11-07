<?php

namespace App\Livewire\Draws;

use App\Models\Draw;
use App\Models\DrawBlock;
use App\Services\DrawService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;

/**
 * Sorteos en el celular (lo manejan los controladores desde el teléfono): se
 * trabaja por día, sin la grilla del mes. Hereda de Index todas las acciones y
 * reglas (sortear, domingos, bloqueos, días desbloqueados, sanciones, historial); aquí solo
 * cambia qué se muestra:
 *  - Día pendiente: una tarjeta por placa con interruptor para elegir quién
 *    participa (el domingo todas empiezan apagadas) y "Sortear" fijo abajo.
 *    Las bloqueadas ese día van aparte, con su motivo y para desbloquear el día.
 *  - Día sorteado: la orden de salida 1, 2, 3… y Compartir PDF.
 */
class Mobile extends Index
{
    /** Cuántos días ya sorteados salen arriba (si no entran, la fila se desliza). */
    private const DRAWN_CHIPS = 4;

    /** Día que se está viendo (Y-m-d); va en la dirección (?dia=) para que al recargar no se pierda. */
    #[Url(as: 'dia')]
    public ?string $day = null;

    public function mount(): void
    {
        parent::mount();

        if (! $this->day || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day)) {
            $this->day = $this->drawableDates()[0] ?? $this->lastDrawDate();
        }
    }

    public function selectDay(string $date): void
    {
        $this->authorizeAccess();
        $this->day = $date;
    }

    /** Pasa al día sorteado anterior (para revisar órdenes pasadas). */
    public function previousDrawnDay(): void
    {
        $this->authorizeAccess();
        $previous = Draw::active()->whereDate('date', '<', $this->day)->max('date');
        if ($previous) {
            $this->day = Carbon::parse($previous)->toDateString();
        }
    }

    public function render(): View
    {
        $drawable = $this->drawableDates();
        $eligible = [];
        foreach ($drawable as $date) {
            $eligible[$date] = DrawService::activeVehicles($date)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        $this->syncIncluded($drawable, $eligible);

        // El día visto tiene que estar sorteado o poder sortearse; si no, el primero pendiente
        $draw = $this->day ? Draw::active()->whereDate('date', $this->day)->with('drawnBy:id,name,username')->first() : null;
        if (! $draw && ! in_array($this->day, $drawable, true)) {
            $this->day = $drawable[0] ?? $this->lastDrawDate();
            $draw = $this->day ? Draw::active()->whereDate('date', $this->day)->with('drawnBy:id,name,username')->first() : null;
        }
        $pending = ! $draw && $this->day && in_array($this->day, $drawable, true);
        $sanctionData = $this->sanctionData();

        return view('livewire.draws.mobile', [
            'drawable' => $drawable,
            'chips' => $this->dayChips($drawable),
            'draw' => $draw,
            'pending' => $pending,
            'isSunday' => $this->day && Carbon::parse($this->day)->isSunday(),
            'dayTitle' => $this->day ? Draw::dayTitleFor($this->day) : null,
            'dayShort' => $this->day ? $this->dayLabel($this->day) : '',
            'items' => $draw ? DrawService::orderItems($draw->entries()->whereNotNull('number')->get(['number', 'plate', 'driver_name'])) : [],
            'hasPrevious' => $this->day && Draw::active()->whereDate('date', '<', $this->day)->exists(),
            'vehicles' => $pending ? DrawService::activeVehicles($this->day)->with('driver:id,name')->get(['id', 'sort_order', 'plate', 'condition', 'driver_id', 'not_working_since']) : collect(),
            ...$this->blockedThatDay($pending),
            'blocks' => DrawBlock::active()->with('blockedBy:id,name,username', 'vehicle.driver:id,name', 'exceptions.createdBy:id,name,username')->get()
                ->sortBy(fn (DrawBlock $b) => [$b->vehicle?->sort_order ?? PHP_INT_MAX, $b->vehicle?->plate])->values(),
            ...$this->historyData(),
            ...$sanctionData,
            'blockable' => $sanctionData['sanctionable'],
            'canBlock' => $this->canBlock(),
            'canSeeGrid' => auth()->user()->hasAnyRole('director', 'gerente', 'administrador'),
        ]);
    }

    /**
     * Botones de días: los últimos DRAWN_CHIPS sorteados, los que se pueden sortear y el que se está viendo.
     *
     * @param  array<int, string>  $drawable
     * @return array<int, array{date: string, label: string, state: string}> state: done|pending|sunday
     */
    private function dayChips(array $drawable): array
    {
        $dates = Draw::active()->orderByDesc('date')->limit(self::DRAWN_CHIPS)->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->merge($drawable)
            ->push($this->day)
            ->filter()->unique()->sort()->values();

        return $dates->map(function (string $date) use ($drawable) {
            $pending = in_array($date, $drawable, true) && ! Draw::active()->whereDate('date', $date)->exists();

            return [
                'date' => $date,
                'label' => $this->dayLabel($date),
                'state' => ! $pending ? 'done' : (Carbon::parse($date)->isSunday() ? 'sunday' : 'pending'),
            ];
        })->all();
    }

    /**
     * Placas de la grilla que no entran el día pendiente por un bloqueo, con el bloqueo que las cubre.
     *
     * @return array{blockedVehicles: Collection, dayBlocks: Collection}
     */
    private function blockedThatDay(bool $pending): array
    {
        if (! $pending) {
            return ['blockedVehicles' => collect(), 'dayBlocks' => collect()];
        }

        $dayBlocks = DrawBlock::active()->onDate($this->day)->with('blockedBy:id,name,username')->get()->keyBy('vehicle_id');

        return [
            'blockedVehicles' => DrawService::gridVehicles($this->day)->whereIn('id', $dayBlocks->keys())
                ->with('driver:id,name')->get(['id', 'sort_order', 'plate', 'condition', 'driver_id']),
            'dayBlocks' => $dayBlocks,
        ];
    }

    private function lastDrawDate(): ?string
    {
        $last = Draw::active()->max('date');

        return $last ? Carbon::parse($last)->toDateString() : null;
    }
}
