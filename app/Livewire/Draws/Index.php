<?php

namespace App\Livewire\Draws;

use App\Models\Draw;
use App\Models\DrawEntry;
use App\Models\Holiday;
use App\Models\Vehicle;
use App\Services\DrawService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Sorteos: grilla de vehículos activos (sin los NT en rojo) por días hábiles
 * del mes (lunes a sábado sin feriados). Se sortea hoy o el siguiente día hábil, con su botón
 * en la cabecera; todos salen marcados y quien se desmarca no participa ese
 * día (queda registrado como excluido). El reparto y sus reglas viven en
 * DrawService. Solo el director anula, solo el último sorteo y con motivo.
 *
 * Cada día sorteado abre su página "Orden de salida": pública (sin login) para
 * mandarla por WhatsApp, o con sesión si es un día simulado.
 *
 * Simular llena el mes en pantalla sin guardar nada: cada día participan al
 * azar entre simMin y simMax vehículos y al resto se le quita el check. Se
 * guarda solo la semilla, así cada render rehace la misma simulación.
 */
class Index extends Component
{
    public const MONTHS = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    #[Url]
    public int $month = 0;

    #[Url]
    public int $year = 0;

    /** Vehículos marcados por día sorteable: ['Y-m-d' => [vehicle_id, ...]]. */
    public array $included = [];

    public ?int $annulDrawId = null;

    public string $annulReason = '';

    public string $holidayDate = '';

    public string $holidayDescription = '';

    /** Semilla de la simulación en pantalla (null = viendo los sorteos reales). */
    public ?int $simSeed = null;

    public int $simMin = 28;

    public int $simMax = 38;

    public function mount(): void
    {
        $this->authorizeAccess();

        if ($this->month < 1 || $this->month > 12 || $this->year < 2000) {
            $this->month = Carbon::today()->month;
            $this->year = Carbon::today()->year;
        }
    }

    public function prevMonth(): void
    {
        $this->goToMonth(Carbon::create($this->year, $this->month, 1)->subMonth()->toDateString());
    }

    public function nextMonth(): void
    {
        $this->goToMonth(Carbon::create($this->year, $this->month, 1)->addMonth()->toDateString());
    }

    public function goToMonth(string $date): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }
        $day = Carbon::parse($date);
        $this->month = $day->month;
        $this->year = $day->year;
    }

    /* ---------- Simular (no guarda nada) ---------- */

    public function openSimulation(): void
    {
        $this->authorizeAccess();

        $this->resetValidation();
        $this->dispatch('open-modal', ['name' => 'simulateModal', 'focus' => 'simMin']);
    }

    public function simulate(): void
    {
        $this->authorizeAccess();

        $this->validate(
            ['simMin' => 'required|integer|min:1|max:999', 'simMax' => 'required|integer|gte:simMin|max:999'],
            ['simMax.gte' => 'El máximo no puede ser menor que el mínimo.'],
            ['simMin' => 'mínimo', 'simMax' => 'máximo'],
        );

        $this->simSeed = random_int(1, 2_147_483_647);
        $this->dispatch('modal-close', ['name' => 'simulateModal']);
    }

    public function resimulate(): void
    {
        $this->authorizeAccess();

        $this->simSeed = random_int(1, 2_147_483_647);
    }

    public function exitSimulation(): void
    {
        $this->simSeed = null;
    }

    /* ---------- Sortear ---------- */

    /**
     * Valida y pide confirmación mostrando cuántos participan y a quiénes se quita.
     */
    public function confirmDraw(string $date): void
    {
        $this->authorizeAccess();

        $drawable = $this->drawableDates();
        if (! in_array($date, $drawable, true)) {
            $this->dispatch('errorAlert', ['message' => 'Ese día ya no se puede sortear. Actualiza la página.']);

            return;
        }

        $included = array_map('intval', $this->included[$date] ?? []);
        $vehicles = DrawService::activeVehicles($date)->get(['id', 'plate']);
        $participants = $vehicles->whereIn('id', $included);
        $excluded = $vehicles->whereNotIn('id', $included);

        if ($participants->isEmpty()) {
            $this->dispatch('errorAlert', ['message' => 'Marca al menos un vehículo para sortear.']);

            return;
        }

        $html = 'Participan <b>'.$participants->count().'</b> vehículo(s), números del 1 al '.$participants->count().'.';
        if ($excluded->isNotEmpty()) {
            $html .= '<br>Sin participar ('.$excluded->count().'): <b>'.e($excluded->pluck('plate')->implode(', ')).'</b>';
        }
        if ($date !== $drawable[0]) {
            $html .= '<br><span class="text-danger">El '.e($this->dayLabel($drawable[0])).' quedará sin sorteo.</span>';
        }
        $html .= '<br><small class="text-muted">Una vez sorteado no se puede repetir; solo el director puede anularlo.</small>';

        $this->dispatch('draw-confirm', date: $date, title: '¿Sortear el '.$this->dayLabel($date).'?', html: $html);
    }

    public function draw(string $date): void
    {
        $this->authorizeAccess();

        try {
            $draw = app(DrawService::class)->run($date, $this->included[$date] ?? [], auth()->user());
        } catch (ValidationException $e) {
            $this->dispatch('errorAlert', ['message' => collect($e->errors())->flatten()->first()]);

            return;
        } catch (QueryException $e) {
            // 23000: otro usuario sorteó ese día justo antes; 40001: choque de bloqueos
            if (! in_array($e->getCode(), ['23000', '40001'], true)) {
                throw $e;
            }
            $this->dispatch('errorAlert', ['message' => 'Otro usuario acaba de sortear. Actualiza la página.']);

            return;
        }

        unset($this->included[$date]);

        // Aviso con el link público del orden de salida (se abre con la lista apareciendo de a uno)
        $this->dispatch('draw-done',
            title: '¡Sorteo realizado!',
            text: Draw::orderTitleFor($draw->date->toDateString())." · {$draw->participants} participantes",
            pageUrl: $draw->publicUrl().'?revelar=1',
            whatsappUrl: $draw->whatsappUrl(),
        );
    }

    /* ---------- Anular (solo director) ---------- */

    public function openAnnul(int $drawId): void
    {
        $this->authorizeDirector();

        $this->annulDrawId = $drawId;
        $this->annulReason = '';
        $this->resetValidation();
        $this->dispatch('open-modal', ['name' => 'annulDrawModal', 'focus' => 'annulReason']);
    }

    public function annul(): void
    {
        $this->authorizeDirector();

        $this->validate(
            ['annulDrawId' => 'required|integer', 'annulReason' => 'required|string|min:5|max:255'],
            [],
            ['annulReason' => 'motivo'],
        );

        try {
            $draw = app(DrawService::class)->annul($this->annulDrawId, trim($this->annulReason), auth()->user());
        } catch (ValidationException $e) {
            $this->dispatch('modal-close', ['name' => 'annulDrawModal']);
            $this->dispatch('errorAlert', ['message' => collect($e->errors())->flatten()->first()]);

            return;
        }

        $this->reset('annulDrawId', 'annulReason');
        $this->dispatch('modal-close', ['name' => 'annulDrawModal']);
        $this->dispatch('successAlert', ['message' => 'Sorteo del '.$this->dayLabel($draw->date->toDateString()).' anulado.']);
    }

    /* ---------- Feriados ---------- */

    public function openHolidays(): void
    {
        $this->authorizeAccess();

        $this->reset('holidayDate', 'holidayDescription');
        $this->resetValidation();
        $this->dispatch('open-modal', ['name' => 'holidaysModal', 'focus' => 'holidayDate']);
    }

    public function addHoliday(): void
    {
        $this->authorizeAccess();

        $this->validate(
            ['holidayDate' => 'required|date_format:Y-m-d|unique:holidays,date', 'holidayDescription' => 'required|string|max:100'],
            [],
            ['holidayDate' => 'fecha', 'holidayDescription' => 'descripción'],
        );

        if (Draw::active()->whereDate('date', $this->holidayDate)->exists()) {
            $this->addError('holidayDate', 'Ese día ya tiene sorteo; el director debe anularlo primero.');

            return;
        }

        Holiday::create([
            'date' => $this->holidayDate,
            'description' => trim($this->holidayDescription),
            'created_by' => auth()->id(),
        ]);

        $this->reset('holidayDate', 'holidayDescription');
    }

    public function deleteHoliday(int $id): void
    {
        $this->authorizeAccess();

        Holiday::findOrFail($id)->delete();
    }

    /** Carga los feriados nacionales del año que se está viendo (sin duplicar). */
    public function loadNationalHolidays(): void
    {
        $this->authorizeAccess();

        $added = 0;
        foreach (Holiday::nationalFor($this->year) as $date => $description) {
            $holiday = Holiday::firstOrCreate(['date' => $date], ['description' => $description, 'created_by' => auth()->id()]);
            $added += $holiday->wasRecentlyCreated ? 1 : 0;
        }

        $this->dispatch('successAlert', ['message' => $added > 0
            ? "Se agregaron {$added} feriado(s) nacionales de {$this->year}."
            : "Los feriados nacionales de {$this->year} ya estaban cargados."]);
    }

    /* ---------- Vista ---------- */

    public function render()
    {
        $monthStart = Carbon::create($this->year, $this->month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $from = $monthStart->toDateString();
        $to = $monthEnd->toDateString();

        $monthHolidays = Holiday::whereBetween('date', [$from, $to])->orderBy('date')->get();

        $days = DrawService::workingDaysOfMonth($this->year, $this->month);

        $activeVehicles = DrawService::activeVehicles()->get(['id', 'sort_order', 'plate', 'condition', 'not_working_since']);
        $simulating = $this->simSeed !== null;
        $drawable = $simulating ? [] : $this->drawableDates();
        if (! $simulating) {
            $this->syncIncluded($drawable, $activeVehicles->pluck('id')->all());
        }

        [$results, $cells] = $simulating
            ? $this->simulatedMonth(array_column($days, 'date'))
            : $this->recordedMonth($from, $to);

        // Vehículos con resultados este mes que hoy ya no entran (inactivos o NT en rojo): al final
        $formerIds = collect($cells)->flatMap(fn (array $day) => array_keys($day))->unique()->diff($activeVehicles->pluck('id'));
        $formerVehicles = Vehicle::whereIn('id', $formerIds)->orderBy('sort_order')->orderBy('plate')
            ->get(['id', 'sort_order', 'plate', 'condition', 'not_working_since']);

        $rows = [];
        foreach ([[$activeVehicles, true], [$formerVehicles, false]] as [$vehicles, $isActive]) {
            foreach ($vehicles as $vehicle) {
                $counts = array_fill_keys(DrawEntry::BANDS, 0);
                foreach ($cells as $day) {
                    $band = $day[$vehicle->id]->band ?? null;
                    if ($band !== null) {
                        $counts[$band]++;
                    }
                }
                $rows[] = [
                    'id' => $vehicle->id,
                    'cod' => $vehicle->sort_order,
                    'plate' => $vehicle->plate,
                    'condition' => $vehicle->condition,
                    'nt' => Vehicle::notWorkingBadge($vehicle->not_working_since),
                    'active' => $isActive,
                    'counts' => $counts,
                ];
            }
        }

        return view('livewire.draws.index', [
            'days' => $days,
            'rows' => $rows,
            'cells' => $cells,
            'results' => $results,
            'simulating' => $simulating,
            'simSummary' => $simulating ? $this->simulationSummary($results, $cells, $rows) : null,
            'drawable' => $drawable,
            'drawableLabels' => array_combine($drawable, array_map(fn ($d) => $this->dayLabel($d), $drawable)),
            'monthHolidays' => $monthHolidays,
            'yearHolidays' => Holiday::whereYear('date', $this->year)->orderBy('date')->get(),
            'annulledDraws' => $simulating ? collect() : Draw::whereNotNull('annulled_at')->whereBetween('date', [$from, $to])
                ->with('drawnBy:id,name', 'annulledBy:id,name')->orderBy('annulled_at')->get(),
            'lastActiveDrawId' => $simulating ? null : Draw::active()->orderByDesc('date')->value('id'),
            'annulTarget' => $this->annulDrawId ? Draw::find($this->annulDrawId, ['id', 'date', 'participants']) : null,
            'isDirector' => auth()->user()->hasRole('director'),
            'monthName' => self::MONTHS[$this->month],
            'years' => range(Carbon::today()->year - 2, Carbon::today()->year + 1),
            'bandLabels' => DrawEntry::BAND_LABELS,
        ]);
    }

    /**
     * Sorteos vigentes del mes: cabecera por día y resultado por vehículo.
     *
     * @return array{0: array<string, array{participants: int, deviations: int, title: string, drawId: int|null, drawnAt: string|null, drawnBy: string|null, simulated: bool, url: string}>, 1: array<string, array<int, object>>}
     */
    private function recordedMonth(string $from, string $to): array
    {
        $draws = Draw::active()->whereBetween('date', [$from, $to])->with('drawnBy:id,name')->get();

        $results = [];
        $dateById = [];
        foreach ($draws as $draw) {
            $date = $draw->date->toDateString();
            $dateById[$draw->id] = $date;
            $results[$date] = [
                'participants' => $draw->participants,
                'deviations' => $draw->deviations,
                'title' => 'Sorteado por '.($draw->drawnBy?->name ?? '—').' el '.$draw->created_at->format('d/m H:i')
                    ." · {$draw->participants} participantes · {$draw->deviations} desvío(s)",
                'drawId' => $draw->id,
                'drawnAt' => $draw->created_at->format('d/m/Y H:i'),
                'drawnBy' => $draw->drawnBy?->name,
                'simulated' => false,
                'url' => $draw->publicUrl(),
            ];
        }

        $cells = [];
        $entries = DrawEntry::whereIn('draw_id', array_keys($dateById))->get(['draw_id', 'vehicle_id', 'plate', 'driver_name', 'number', 'band', 'previous_band', 'deviated']);
        foreach ($entries as $entry) {
            $cells[$dateById[$entry->draw_id]][$entry->vehicle_id] = $entry;
        }

        return [$results, $cells];
    }

    /**
     * Simulación del mes con la semilla actual, partiendo de las franjas reales
     * anteriores al mes. Cada día entran los habilitados a esa fecha.
     *
     * @param  array<int, string>  $dates
     * @return array{0: array<string, array{participants: int, deviations: int, title: string, drawId: int|null, drawnAt: string|null, drawnBy: string|null, simulated: bool, url: string}>, 1: array<string, array<int, object>>}
     */
    private function simulatedMonth(array $dates): array
    {
        if (count($dates) === 0) {
            return [[], []];
        }

        $service = new DrawService(new Randomizer(new Mt19937($this->simSeed)));
        $simulated = $service->simulateMonth($dates, $this->simMin, $this->simMax);

        $results = [];
        $cells = [];
        foreach ($simulated as $date => $day) {
            $participants = count(array_filter($day, fn (array $e) => $e['number'] !== null));
            $deviations = count(array_filter($day, fn (array $e) => $e['deviated']));
            $results[$date] = [
                'participants' => $participants,
                'deviations' => $deviations,
                'title' => "Simulación · {$participants} participantes · {$deviations} desvío(s)",
                'drawId' => null,
                'drawnAt' => null,
                'drawnBy' => null,
                'simulated' => true,
                'url' => route('draws.simulation', ['date' => $date, 'seed' => $this->simSeed, 'min' => $this->simMin, 'max' => $this->simMax]),
            ];
            $cells[$date] = array_map(fn (array $e) => (object) $e, $day);
        }

        return [$results, $cells];
    }

    /**
     * Totales de la simulación para el aviso de arriba.
     *
     * @return array{days: int, minParticipants: int, maxParticipants: int, numbers: int, deviations: int, repeats: int, even: int, vehicles: int, maxGap: int}
     */
    private function simulationSummary(array $results, array $cells, array $rows): array
    {
        $participants = array_column($results, 'participants');
        $repeats = 0;
        foreach ($cells as $day) {
            foreach ($day as $entry) {
                $repeats += ($entry->band !== null && $entry->band === $entry->previous_band) ? 1 : 0;
            }
        }
        $gaps = array_map(fn (array $r) => max($r['counts']) - min($r['counts']), array_filter($rows, fn (array $r) => array_sum($r['counts']) > 0));

        return [
            'days' => count($results),
            'minParticipants' => $participants ? min($participants) : 0,
            'maxParticipants' => $participants ? max($participants) : 0,
            'numbers' => array_sum($participants),
            'deviations' => array_sum(array_column($results, 'deviations')),
            'repeats' => $repeats,
            'even' => count(array_filter($gaps, fn (int $g) => $g <= 1)),
            'vehicles' => count($gaps),
            'maxGap' => $gaps ? max($gaps) : 0,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function drawableDates(): array
    {
        $today = Carbon::today();
        $lastDate = Draw::active()->max('date');

        return DrawService::drawableDates($lastDate ? Carbon::parse($lastDate)->toDateString() : null, $today, DrawService::holidaysAround($today));
    }

    /**
     * Deja solo los días sorteables y marca a todos los activos en los nuevos;
     * lo que el usuario ya desmarcó se respeta.
     *
     * @param  array<int, string>  $drawable
     * @param  array<int, int>  $activeIds
     */
    private function syncIncluded(array $drawable, array $activeIds): void
    {
        $this->included = array_intersect_key($this->included, array_flip($drawable));
        foreach ($drawable as $date) {
            $this->included[$date] ??= array_map('strval', $activeIds);
        }
    }

    /** "Sáb 31/10" */
    private function dayLabel(string $date): string
    {
        $day = Carbon::parse($date);

        return DrawService::weekdayLabel($day).' '.$day->format('d/m');
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('draws'), 403);
    }

    private function authorizeDirector(): void
    {
        abort_unless(auth()->user()?->hasRole('director'), 403);
    }
}
