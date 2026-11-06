<?php

namespace App\Livewire\Draws;

use App\Models\Draw;
use App\Models\DrawBlock;
use App\Models\DrawBlockException;
use App\Models\DrawEntry;
use App\Models\Holiday;
use App\Models\Vehicle;
use App\Services\DrawService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;
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
 * Cada día sorteado (real o simulado) abre el PDF de su orden de salida, que
 * se descarga y se manda por WhatsApp como archivo.
 *
 * Placas bloqueadas (director, gerente y administrador): se bloquean todo un
 * mes, un rango de fechas, días específicos o hasta desbloquearlas, y no
 * entran al sorteo ni a la simulación esos días (candado en la grilla). Las
 * que no entran a ningún sorteo que queda del mes van abajo de todo. Con clic
 * en los candados se marcan días para desbloquearlos con un motivo. Cada placa
 * tiene su historial de sanciones (clic en la placa).
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

    /** Placa cuyo historial de sanciones se está viendo. */
    public ?int $historyVehicleId = null;

    /** Domingo cuyo sorteo se está preparando (lista para elegir quiénes participan). */
    public ?string $sundayDate = null;

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
        if (Carbon::parse($date)->isSunday()) {
            // Domingo: entran solo los elegidos en la lista; se nombran ellos, no los que faltan
            $html .= '<br>Solo ellos: <b>'.e($participants->sortBy('plate')->pluck('plate')->implode(', ')).'</b>';
        } else {
            if ($excluded->isNotEmpty()) {
                $html .= '<br>Sin participar ('.$excluded->count().'): <b>'.e($excluded->pluck('plate')->implode(', ')).'</b>';
            }
            $blockedPlates = Vehicle::whereIn('id', DrawBlock::blockedVehicleIdsOn($date))->orderBy('plate')->pluck('plate');
            if ($blockedPlates->isNotEmpty()) {
                $html .= '<br>Bloqueadas, no entran ('.$blockedPlates->count().'): '.e($blockedPlates->implode(', '));
            }
        }
        // Días hábiles que se saltan (el domingo es opcional, no se avisa)
        $skipped = array_filter($drawable, fn (string $d) => $d < $date && ! Carbon::parse($d)->isSunday());
        if ($skipped) {
            $html .= '<br><span class="text-danger">El '.e(implode(' y el ', array_map(fn (string $d) => $this->dayLabel($d), $skipped))).' quedará sin sorteo.</span>';
        }
        $html .= '<br><small class="text-muted">Una vez sorteado no se puede repetir; solo el director puede anularlo.</small>';

        $this->dispatch('draw-confirm', date: $date, title: '¿Sortear el '.$this->dayLabel($date).'?', html: $html);
    }

    /**
     * Sortear un domingo: primero se abre la lista de placas activas (sin las
     * bloqueadas ni los NT en rojo), todas sin marcar, para elegir quiénes
     * SOLO participan.
     */
    public function openSundayDraw(string $date): void
    {
        $this->authorizeAccess();

        if (! Carbon::parse($date)->isSunday() || ! in_array($date, $this->drawableDates(), true)) {
            $this->dispatch('errorAlert', ['message' => 'Ese domingo ya no se puede sortear. Actualiza la página.']);

            return;
        }

        $this->sundayDate = $date;
        $this->dispatch('sunday-draw-open', date: $date, selected: array_map('intval', $this->included[$date] ?? []));
    }

    /**
     * Toma las placas elegidas en la lista del domingo y pasa a la confirmación
     * de siempre (y de ahí al sorteo).
     *
     * @param  array<int, int|string>  $vehicleIds
     */
    public function confirmSundayDraw(string $date, array $vehicleIds): void
    {
        $this->authorizeAccess();

        if ($date !== $this->sundayDate || ! Carbon::parse($date)->isSunday()) {
            $this->dispatch('errorAlert', ['message' => 'Vuelve a abrir el sorteo del domingo.']);

            return;
        }

        $eligible = DrawService::activeVehicles($date)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $chosen = array_values(array_intersect(array_map('intval', $vehicleIds), $eligible));
        if (! $chosen) {
            $this->dispatch('errorAlert', ['message' => 'Marca al menos una placa para el sorteo del domingo.']);

            return;
        }

        $this->included[$date] = array_map('strval', $chosen);
        $this->dispatch('modal-close', ['name' => 'sundayDrawModal']);
        $this->confirmDraw($date);
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
        if ($this->sundayDate === $date) {
            $this->sundayDate = null;
        }

        // Si por fuerza mayor alguien repitió número en el mes, se avisa en el momento
        $warning = '';
        if ($draw->repeated_numbers > 0) {
            $plates = app(DrawService::class)->repeatedPlates($draw);
            $warning = "Ojo: {$draw->repeated_numbers} placa(s) repitieron un número que ya tuvieron este mes"
                .($plates ? ' ('.implode(', ', $plates).')' : '')
                .'. Pasa cuando participan pocos vehículos; en la grilla quedan con recuadro rojo.';
        }

        // Aviso con el PDF del orden de salida para verlo o descargarlo y mandarlo por WhatsApp
        $this->dispatch('draw-done',
            title: '¡Sorteo realizado!',
            text: Draw::orderTitleFor($draw->date->toDateString())." · {$draw->participants} participantes",
            warning: $warning,
            pdfUrl: route('draws.pdf', $draw),
            downloadUrl: route('draws.pdf', ['draw' => $draw, 'descargar' => 1]),
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

    /* ---------- Placas bloqueadas ---------- */

    public function openBlocks(): void
    {
        $this->authorizeAccess();

        $this->dispatch('open-modal', ['name' => 'blocksModal']);
    }

    /**
     * Datos del formulario de "Bloquear": desde qué día se puede bloquear (lo
     * ya sorteado no cambia), los feriados que el calendario deshabilita y los
     * meses que se pueden bloquear completos.
     *
     * @return array{minDate: string, maxDate: string, holidays: array<int, string>, months: array<int, array{value: string, label: string}>}
     */
    #[Renderless]
    public function blockFormOptions(): array
    {
        $this->authorizeBlocking();

        $firstOpen = Carbon::parse($this->firstOpenDate());
        $maxDate = $firstOpen->copy()->addYear();
        $months = [];
        for ($month = $firstOpen->copy()->startOfMonth(), $i = 0; $i < 3; $i++, $month->addMonth()) {
            $months[] = ['value' => $month->format('Y-m'), 'label' => Str::ucfirst($month->translatedFormat('F Y'))];
        }

        return [
            'minDate' => $firstOpen->toDateString(),
            'maxDate' => $maxDate->toDateString(),
            'holidays' => Holiday::datesBetween($firstOpen->toDateString(), $maxDate->toDateString()),
            'months' => $months,
        ];
    }

    /**
     * Bloquea las placas marcadas en la tabla ("Seleccionar varios") con un
     * mismo motivo y periodo: todo un mes, un rango de fechas, días
     * específicos o hasta desbloquearlas. Cada placa queda con su bloqueo
     * (los que ya tenía siguen igual). Devuelve true si se guardó.
     *
     * @param  array<int, int|string>  $vehicleIds
     * @param  array{kind?: string, month?: string, from?: string, to?: string, dates?: array<int, string>, reason?: string}  $form
     */
    public function blockVehicles(array $vehicleIds, array $form): bool
    {
        $this->authorizeBlocking();

        try {
            $period = $this->blockPeriod($form);
        } catch (ValidationException $e) {
            $this->dispatch('errorAlert', ['message' => collect($e->errors())->flatten()->first()]);

            return false;
        }

        $vehicles = Vehicle::active()->whereIn('id', array_map('intval', $vehicleIds))->orderBy('plate')->get(['id', 'plate']);
        if ($vehicles->isEmpty()) {
            $this->dispatch('errorAlert', ['message' => 'Marca al menos una placa para bloquear.']);

            return false;
        }

        $created = DB::transaction(fn () => $vehicles->map(fn (Vehicle $vehicle) => DrawBlock::create($period + [
            'vehicle_id' => $vehicle->id,
            'blocked_by' => auth()->id(),
        ])));

        $this->dispatch('successAlert', ['message' => 'Bloqueadas ('.$vehicles->count().'): '.$vehicles->pluck('plate')->implode(', ')
            .' · '.$created->first()->periodLabel()]);

        return true;
    }

    /**
     * Desbloquea días sueltos (clic en los candados de la grilla): ese día la
     * placa sí entra al sorteo aunque su bloqueo lo cubra. Solo días que
     * todavía no se sortearon; el motivo queda en su historial.
     *
     * @param  array<int, string>  $cells  "vehicleId|Y-m-d"
     */
    public function unblockDays(array $cells, string $reason): bool
    {
        $this->authorizeBlocking();

        $reason = $this->cleanReason($reason);
        if ($reason === null) {
            return false;
        }

        $firstOpen = $this->firstOpenDate();
        $pairs = collect($cells)
            ->map(fn ($cell) => explode('|', (string) $cell))
            ->filter(fn (array $p) => count($p) === 2 && ctype_digit($p[0]) && $this->validDate($p[1]) && $p[1] >= $firstOpen)
            ->map(fn (array $p) => [(int) $p[0], $p[1]])
            ->unique(fn (array $p) => $p[0].'|'.$p[1])
            ->sortBy(fn (array $p) => $p[1])->values();

        $unblocked = DB::transaction(function () use ($pairs, $reason) {
            $done = [];
            foreach ($pairs as [$vehicleId, $date]) {
                $blocks = DrawBlock::active()->where('vehicle_id', $vehicleId)->onDate($date)->get();
                foreach ($blocks as $block) {
                    DrawBlockException::firstOrCreate(
                        ['draw_block_id' => $block->id, 'date' => $date],
                        ['reason' => $reason, 'created_by' => auth()->id()],
                    );
                }
                if ($blocks->isNotEmpty()) {
                    $done[] = [$vehicleId, $date];
                }
            }

            return $done;
        });

        if (! $unblocked) {
            $this->dispatch('errorAlert', ['message' => 'Marca días con candado que todavía no se hayan sorteado.']);

            return false;
        }

        foreach ($unblocked as [$vehicleId, $date]) {
            $this->includeAgain([$vehicleId], $date);
        }
        $plates = Vehicle::whereIn('id', array_column($unblocked, 0))->pluck('plate', 'id');
        $this->dispatch('successAlert', ['message' => 'Desbloqueados '.count($unblocked).' día(s): '
            .implode(', ', array_map(fn (array $p) => $plates[$p[0]].' '.DrawBlock::dayLabel($p[1]), $unblocked))]);

        return true;
    }

    /** Abre el historial de sanciones (bloqueos y días desbloqueados) de una placa. */
    public function openHistory(int $vehicleId): void
    {
        $this->authorizeAccess();

        $this->historyVehicleId = Vehicle::whereKey($vehicleId)->value('id');
        $this->dispatch('open-modal', ['name' => 'historyModal']);
    }

    /**
     * Quita todos los bloqueos vigentes de las placas marcadas en la tabla,
     * con el motivo (queda en su historial). Devuelve true si se guardó.
     *
     * @param  array<int, int|string>  $vehicleIds
     */
    public function unblockVehicles(array $vehicleIds, string $reason): bool
    {
        $this->authorizeBlocking();

        $reason = $this->cleanReason($reason);
        if ($reason === null) {
            return false;
        }

        $blocks = DrawBlock::active()->with('vehicle:id,plate')->whereIn('vehicle_id', array_map('intval', $vehicleIds))->get();
        if ($blocks->isEmpty()) {
            $this->dispatch('errorAlert', ['message' => 'Las placas marcadas no tienen bloqueos vigentes.']);

            return false;
        }

        DB::transaction(fn () => $blocks->each(fn (DrawBlock $block) => $this->lift($block, $reason)));
        $this->includeAgain($blocks->pluck('vehicle_id')->unique()->all());

        $this->dispatch('successAlert', ['message' => 'Desbloqueadas: '.$blocks->pluck('vehicle.plate')->unique()->implode(', ')]);

        return true;
    }

    /** Levanta un bloqueo de la lista de Bloqueos, con el motivo. */
    public function unblockVehicle(int $blockId, string $reason): bool
    {
        $this->authorizeBlocking();

        $reason = $this->cleanReason($reason);
        if ($reason === null) {
            return false;
        }

        $block = DrawBlock::active()->with('vehicle:id,plate')->findOrFail($blockId);
        $this->lift($block, $reason);
        $this->includeAgain([$block->vehicle_id]);
        $this->dispatch('successAlert', ['message' => 'Desbloqueada: '.$block->vehicle?->plate]);

        return true;
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

        $activeVehicles = DrawService::gridVehicles()->get(['id', 'sort_order', 'plate', 'condition', 'not_working_since']);
        $simulating = $this->simSeed !== null;
        $drawable = $simulating ? [] : $this->drawableDates();
        // Domingos: solo los sorteados o el que hoy se puede sortear (la simulación no los usa)
        if (! $simulating) {
            $days = $this->withSundays($days, $drawable, $from, $to);
        }
        // Quién entra a cada día sorteable (los bloqueados ese día de la semana no tienen casilla)
        $eligible = [];
        foreach ($drawable as $date) {
            $eligible[$date] = DrawService::activeVehicles($date)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if (! $simulating) {
            $this->syncIncluded($drawable, $eligible);
        }

        [$results, $cells, $simulated] = $simulating
            ? $this->simulatedMonth(array_column($days, 'date'))
            : [...$this->recordedMonth($from, $to), []];

        // Bloqueos vigentes (para la lista y el detalle de cada placa) y días con candado del mes
        $blocks = DrawBlock::active()->with('blockedBy:id,name,username', 'vehicle.driver:id,name', 'exceptions.createdBy:id,name,username')->get()
            ->sortBy(fn (DrawBlock $b) => [$b->vehicle?->sort_order ?? PHP_INT_MAX, $b->vehicle?->plate, $b->starts_on])->values();
        $blocksByVehicle = $blocks->groupBy('vehicle_id');
        // Placas que alguna vez tuvieron un bloqueo (aunque ya se cumplió o se levantó): van en rojo
        $everBlocked = DrawBlock::distinct()->pluck('vehicle_id')->flip();
        $lockedDays = $this->blockedDaysOfMonth(array_column($days, 'date'), $from, $to);

        // Abajo de todo van las que no entran a ningún sorteo que queda del mes;
        // las demás se quedan en su lugar con candado los días que no entran
        $firstOpen = $this->firstOpenDate();
        $openDates = array_values(array_filter(array_column($days, 'date'), fn (string $d) => $d >= $firstOpen));
        $isBottom = fn (Vehicle $v) => $openDates && collect($openDates)->every(fn (string $d) => isset($lockedDays[$v->id][$d]));
        $bottomVehicles = $activeVehicles->filter($isBottom)->values();
        $inPlaceVehicles = $activeVehicles->reject($isBottom)->values();

        // Vehículos con resultados este mes que hoy ya no entran (inactivos o NT en rojo), antes de las bloqueadas
        $formerIds = collect($cells)->flatMap(fn (array $day) => array_keys($day))->unique()->diff($activeVehicles->pluck('id'));
        $formerVehicles = Vehicle::whereIn('id', $formerIds)->orderBy('sort_order')->orderBy('plate')
            ->get(['id', 'sort_order', 'plate', 'condition', 'not_working_since']);

        $rows = [];
        foreach ([[$inPlaceVehicles, 'active'], [$formerVehicles, 'former'], [$bottomVehicles, 'blocked']] as [$vehicles, $kind]) {
            foreach ($vehicles as $vehicle) {
                $counts = array_fill_keys(DrawEntry::BANDS, 0);
                $numbers = [];
                foreach ($cells as $day) {
                    $band = $day[$vehicle->id]->band ?? null;
                    if ($band !== null) {
                        $counts[$band]++;
                        $numbers[] = $day[$vehicle->id]->number;
                    }
                }
                $rows[] = [
                    'id' => $vehicle->id,
                    'cod' => $vehicle->sort_order,
                    'plate' => $vehicle->plate,
                    'condition' => $vehicle->condition,
                    'nt' => Vehicle::notWorkingBadge($vehicle->not_working_since),
                    'active' => $kind === 'active',
                    'blocked' => $kind === 'blocked',
                    // Bloqueos vigentes de la placa (para el candado junto a la placa)
                    'blocks' => $kind === 'former' ? collect() : ($blocksByVehicle[$vehicle->id] ?? collect()),
                    'everBlocked' => isset($everBlocked[$vehicle->id]),
                    'counts' => $counts,
                    // Números que esta placa recibió más de una vez en el mes (no debería pasar)
                    'repeatedNumbers' => array_keys(array_filter(array_count_values($numbers), fn (int $times) => $times > 1)),
                ];
            }
        }

        // La lista de bloqueos en el mismo orden que la tabla (su Nº es correlativo)
        $rowPositions = array_flip(array_column($rows, 'id'));
        $blocks = $blocks->sortBy(fn (DrawBlock $b) => [$rowPositions[$b->vehicle_id] ?? PHP_INT_MAX, $b->starts_on])->values();

        return view('livewire.draws.index', [
            'days' => $days,
            'rows' => $rows,
            'cells' => $cells,
            'results' => $results,
            'simulating' => $simulating,
            'simSummary' => $simulating ? DrawService::summarize($simulated) : null,
            'simExportUrl' => $simulating ? route('exports.draws-simulation', [
                'year' => $this->year, 'month' => $this->month, 'seed' => $this->simSeed, 'min' => $this->simMin, 'max' => $this->simMax,
            ]) : null,
            'drawable' => $drawable,
            'eligible' => $eligible,
            'today' => Carbon::today()->toDateString(),
            'firstOpen' => $firstOpen,
            'bottomCount' => $bottomVehicles->count(),
            'drawableLabels' => array_combine($drawable, array_map(fn ($d) => $this->dayLabel($d), $drawable)),
            'monthHolidays' => $monthHolidays,
            'yearHolidays' => Holiday::whereYear('date', $this->year)->orderBy('date')->get(),
            'blocks' => $blocks,
            'lockedDays' => $lockedDays,
            // Lista del domingo que se está preparando: las placas que pueden entrar ese día
            'sundayVehicles' => ! $simulating && $this->sundayDate && in_array($this->sundayDate, $drawable, true)
                ? DrawService::activeVehicles($this->sundayDate)->with('driver:id,name')->get(['id', 'sort_order', 'plate', 'condition', 'driver_id'])
                : collect(),
            ...$this->historyData(),
            'canBlock' => $this->canBlock(),
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
                    ." · {$draw->participants} participantes · {$draw->deviations} desvío(s)"
                    .($draw->repeated_numbers > 0 ? " · {$draw->repeated_numbers} número(s) repetido(s) en el mes" : ''),
                'drawId' => $draw->id,
                'drawnAt' => $draw->created_at->format('d/m/Y H:i'),
                'drawnBy' => $draw->drawnBy?->name,
                'simulated' => false,
                'url' => route('draws.pdf', $draw),
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
     * @return array{0: array<string, array{participants: int, deviations: int, title: string, drawId: int|null, drawnAt: string|null, drawnBy: string|null, simulated: bool, url: string}>, 1: array<string, array<int, object>>, 2: array<string, array<int, array>>}
     */
    private function simulatedMonth(array $dates): array
    {
        if (count($dates) === 0) {
            return [[], [], []];
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

        return [$results, $cells, $simulated];
    }

    /**
     * @return array<int, string>
     */
    protected function drawableDates(): array
    {
        $today = Carbon::today();
        $lastDate = Draw::active()->max('date');

        return DrawService::drawableDates($lastDate ? Carbon::parse($lastDate)->toDateString() : null, $today, DrawService::holidaysAround($today));
    }

    /**
     * Días hábiles del mes en que cada placa estuvo o está bloqueada, para el
     * candado de la grilla: cuentan desde que se registró el bloqueo hasta que
     * se levantó (también los ya cumplidos), menos los días desbloqueados.
     *
     * @param  array<int, string>  $dates  días hábiles del mes
     * @return array<int, array<string, DrawBlock>> vehicle_id => [Y-m-d => bloqueo]
     */
    private function blockedDaysOfMonth(array $dates, string $from, string $to): array
    {
        $blocks = DrawBlock::overlapping($from, $to)->with('blockedBy:id,name,username', 'exceptions')->orderBy('id')->get();

        $locked = [];
        foreach ($blocks as $block) {
            $since = $block->created_at->toDateString();
            $until = $block->unblocked_at?->toDateString();
            foreach ($dates as $date) {
                if ($date >= $since && ($until === null || $date <= $until) && $block->appliesOn($date)) {
                    $locked[$block->vehicle_id][$date] = $block;
                }
            }
        }

        return $locked;
    }

    /**
     * Historial de sanciones de la placa abierta: todos sus bloqueos con sus
     * días desbloqueados, del más nuevo al más viejo.
     *
     * @return array{historyVehicle: Vehicle|null, history: \Illuminate\Support\Collection<int, DrawBlock>}
     */
    protected function historyData(): array
    {
        $vehicle = $this->historyVehicleId
            ? Vehicle::with('driver:id,name')->find($this->historyVehicleId, ['id', 'plate', 'sort_order', 'driver_id'])
            : null;
        $history = $vehicle ? DrawBlock::historyFor($vehicle->id) : collect();

        return ['historyVehicle' => $vehicle, 'history' => $history];
    }

    /** El motivo sin espacios de más, o null (con aviso) si no tiene de 3 a 255 caracteres. */
    private function cleanReason(string $reason): ?string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            $this->dispatch('errorAlert', ['message' => 'Escribe un motivo de 3 a 255 caracteres.']);

            return null;
        }

        return $reason;
    }

    /** Levanta un bloqueo completo: queda en el historial con quién, cuándo y por qué. */
    private function lift(DrawBlock $block, string $reason): void
    {
        $block->update(['unblocked_at' => now(), 'unblocked_by' => auth()->id(), 'unblock_reason' => $reason]);
    }

    /** Primer día que todavía se puede sortear (desde ahí se bloquea o desbloquea). */
    protected function firstOpenDate(): string
    {
        $today = Carbon::today();
        $lastDate = Draw::active()->max('date');

        return DrawService::firstOpenDate($lastDate ? Carbon::parse($lastDate)->toDateString() : null, $today, DrawService::holidaysAround($today));
    }

    /**
     * Valida el formulario de "Bloquear" y arma el periodo del bloqueo.
     *
     * @param  array<string, mixed>  $form
     * @return array{kind: string, reason: string, starts_on: string, ends_on: string|null, dates: array<int, string>|null}
     *
     * @throws ValidationException
     */
    private function blockPeriod(array $form): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['block' => $message]);

        $reason = trim((string) ($form['reason'] ?? ''));
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            $fail('Escribe un motivo de 3 a 255 caracteres.');
        }

        $firstOpen = $this->firstOpenDate();
        $tooEarly = 'Solo se puede bloquear desde el '.DrawBlock::dayLabel($firstOpen).': lo anterior ya se sorteó.';
        $base = ['kind' => (string) ($form['kind'] ?? ''), 'reason' => $reason, 'dates' => null];

        switch ($base['kind']) {
            case DrawBlock::KIND_MONTH:
                $month = (string) ($form['month'] ?? '');
                if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                    $fail('Elige el mes que quieres bloquear.');
                }
                $start = Carbon::createFromFormat('!Y-m', $month);
                if ($start->copy()->endOfMonth()->toDateString() < $firstOpen) {
                    $fail('Ese mes ya terminó de sortearse.');
                }

                return $base + ['starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->endOfMonth()->toDateString()];

            case DrawBlock::KIND_RANGE:
                $from = $this->validDate($form['from'] ?? null);
                $to = $this->validDate($form['to'] ?? null);
                if (! $from || ! $to) {
                    $fail('Elige desde qué día y hasta qué día.');
                }
                if ($from > $to) {
                    [$from, $to] = [$to, $from];
                }
                if ($from < $firstOpen) {
                    $fail($tooEarly);
                }
                if (Carbon::parse($from)->addYear()->toDateString() < $to) {
                    $fail('El rango no puede pasar de un año.');
                }

                return $base + ['starts_on' => $from, 'ends_on' => $to];

            case DrawBlock::KIND_DATES:
                $dates = collect((array) ($form['dates'] ?? []))->map(fn ($d) => $this->validDate($d))->filter()->unique()->sort()->values()->all();
                if (! $dates) {
                    $fail('Elige al menos un día en el calendario.');
                }
                if (count($dates) > 60) {
                    $fail('Son demasiados días sueltos: usa un rango de fechas.');
                }
                if ($dates[0] < $firstOpen) {
                    $fail($tooEarly);
                }

                return ['dates' => $dates] + $base + ['starts_on' => $dates[0], 'ends_on' => end($dates)];

            case DrawBlock::KIND_INDEFINITE:
                return $base + ['starts_on' => $firstOpen, 'ends_on' => null];
        }

        $fail('Elige cómo quieres bloquear.');
    }

    /** "2026-11-07" si es una fecha válida; null si no. */
    private function validDate(mixed $value): ?string
    {
        $value = (string) $value;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::createFromFormat('!Y-m-d', $value) : false;

        return $date && $date->toDateString() === $value ? $value : null;
    }

    /**
     * Vuelve a marcar (participan) a las placas desbloqueadas en los días
     * sorteables, o solo en $date: al desbloquear entran como todos.
     *
     * @param  array<int, int>  $vehicleIds
     */
    private function includeAgain(array $vehicleIds, ?string $date = null): void
    {
        foreach ($this->drawableDates() as $drawable) {
            if ($date !== null && $drawable !== $date) {
                continue;
            }
            $current = $this->included[$drawable] ?? null;
            if ($current === null) {
                continue; // aún no se pintó ese día: al pintarse entran todos los habilitados
            }
            $this->included[$drawable] = array_values(array_unique(array_merge($current, array_map('strval', $vehicleIds))));
        }
    }

    /**
     * Deja solo los días sorteables y marca en los nuevos a todos los que
     * entran ese día; lo que el usuario ya desmarcó se respeta. Los domingos
     * empiezan sin nadie: se elige quiénes participan en su lista.
     *
     * @param  array<int, string>  $drawable
     * @param  array<string, array<int, int>>  $eligible  fecha => vehicle_ids que entran
     */
    protected function syncIncluded(array $drawable, array $eligible): void
    {
        $this->included = array_intersect_key($this->included, array_flip($drawable));
        foreach ($drawable as $date) {
            $this->included[$date] ??= Carbon::parse($date)->isSunday() ? [] : array_map('strval', $eligible[$date] ?? []);
        }
    }

    /**
     * Columnas de la grilla: los días hábiles del mes más los domingos que se
     * sortearon o que hoy se pueden sortear (los demás domingos no se muestran).
     *
     * @param  array<int, array{date: string, n: int, weekday: string}>  $days
     * @param  array<int, string>  $drawable
     * @return array<int, array{date: string, n: int, weekday: string, sunday?: bool}>
     */
    private function withSundays(array $days, array $drawable, string $from, string $to): array
    {
        $sundays = collect(Draw::active()->whereBetween('date', [$from, $to])->pluck('date'))
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->merge(array_filter($drawable, fn (string $d) => $d >= $from && $d <= $to))
            ->filter(fn (string $d) => Carbon::parse($d)->isSunday())
            ->unique();

        foreach ($sundays as $date) {
            $day = Carbon::parse($date);
            $days[] = ['date' => $date, 'n' => $day->day, 'weekday' => DrawService::weekdayLabel($day), 'sunday' => true];
        }
        usort($days, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        return $days;
    }

    /** "Sáb 31/10" */
    protected function dayLabel(string $date): string
    {
        $day = Carbon::parse($date);

        return DrawService::weekdayLabel($day).' '.$day->format('d/m');
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('draws'), 403);
    }

    /** Bloquear y desbloquear placas: director, gerente, administrador y controlador (los controladores manejan Sorteos desde el celular). */
    protected function canBlock(): bool
    {
        return (bool) auth()->user()?->hasAnyRole('director', 'gerente', 'administrador', 'controlador');
    }

    private function authorizeBlocking(): void
    {
        abort_unless($this->canBlock(), 403);
    }

    private function authorizeDirector(): void
    {
        abort_unless(auth()->user()?->hasRole('director'), 403);
    }
}
