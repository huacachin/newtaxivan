<?php

namespace App\Livewire\Payments;

use App\Models\CostPerPlateDay;
use App\Models\Headquarter;
use App\Models\Payment;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';
    // ===== Filtros (listado) =====
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $filter = '';          // 1=Placa, 2=Usuario, 3=Serie
    #[Url(as: 'from')]
    public string $date_start = '';
    #[Url(as: 'to')]
    public string $date_end   = '';
    public ?string $ui_date_start = null;
    public ?string $ui_date_end   = null;
    #[Url(as: 'hq')]
    public string $headquarter_id = '';  // '' = todos (FILTRO)
    #[Url]
    public string $type = '';            // '' = todos (FILTRO)

    public $headquarters = [];

    // ===== Formulario =====
    public ?int $paymentId = null;
    public string $plate = '';
    public ?string $serie = null;
    public string $date_register = '';
    public ?string $date_payment = null;
    public string $hour = '';                 // HH:mm
    public string $type_form = '';            // PAGO | DEUDA | RETRASO
    public ?int $headquarter_id_form = null;  // FK
    public float|string $amount = '';
    public ?float $latitude = null;
    public ?float $longitude = null;

    // costo del día (PAGO/RETRASO)
    public ?float $detected_cost = null;

    // deuda total pendiente (todas las filas en debt_days de la placa)
    public ?float $pending_debt = null;

    // ===== Config de deuda =====
    private string $debtDaysDateColumn = 'date';      // fecha mensual en debt_days
    private string $debtDaysAmortCol   = 'amortized'; // acumulador en debt_days

    // ===== Validaciones =====
    protected function rules(): array
    {

        $datePaymentRule = ['nullable','date']; // por defecto

        if (in_array($this->type_form, ['DEUDA','RETRASO'], true)) {
            $datePaymentRule = ['required','date'];
        }

        $rules = [
            'plate' => ['required','string','min:6','max:20'],
            'serie'               => ['nullable','string','max:50'],
            'date_register'       => ['required','date'],
            'date_payment'        => ['nullable','date'],
            'hour'                => ['required'],
            'type_form'           => ['required','in:PAGO,DEUDA,RETRASO'],
            'headquarter_id_form' => ['required','integer','exists:headquarters,id'],
            'amount'              => ['required','numeric','gt:0'],
            'latitude'            => ['nullable','numeric'],
            'longitude'           => ['nullable','numeric'],
        ];

        // PAGO/RETRASO: si hay costo, el monto debe coincidir
        if (!is_null($this->detected_cost) && in_array($this->type_form, ['PAGO','RETRASO'], true)) {
            $rules['amount'][] = function ($attr, $val, $fail) {
                $val = (float)$val;
                if (abs($val - (float)$this->detected_cost) > 0.001) {
                    $fail('El monto debe coincidir con el costo del día para la placa.');
                }
            };
        }

        // DEUDA: no puede pagar más que lo pendiente
        if ($this->type_form === 'DEUDA') {
            $rules['amount'][] = function ($attr, $val, $fail) {
                $pend = (float)($this->pending_debt ?? 0);
                $valf = (float)$val;
                if ($pend <= 0.0) {
                    $fail('No hay deuda pendiente para esta placa.');
                    return;
                }
                if ($valf - $pend > 0.0001) {
                    $fail('El monto no puede exceder la deuda pendiente (S/ '.number_format($pend,2).').');
                }
            };
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'plate.required'               => 'La placa es obligatoria.',
            'date_register.required'       => 'La fecha de registro es obligatoria.',
            'hour.required'                => 'La hora es obligatoria.',
            'type_form.required'           => 'El tipo es obligatorio.',
            'type_form.in'                 => 'Tipo inválido.',
            'headquarter_id_form.required' => 'La sucursal es obligatoria.',
            'headquarter_id_form.exists'   => 'Sucursal no válida.',
            'amount.required'              => 'El monto es obligatorio.',
            'amount.numeric'               => 'El monto debe ser numérico.',
            'amount.gt'                    => 'El monto debe ser mayor a 0.',
        ];
    }

    // ==============================
    // === INTEGRACIÓN ROLES/SEDES: helpers de rol y sedes del usuario
    // ==============================

    /** IDs de sedes asignadas al usuario autenticado (pivote + primaria por compatibilidad) */
    private array $userHqIds = [];

    /** Retorna true si el usuario autenticado tiene el rol indicado (insensible a mayúsculas). */
    private function userHasRole(string $needle): bool
    {
        $u = Auth::user();
        if (!$u) return false;

        $needle = mb_strtolower($needle);
        return $u->getRoleNames()
            ->map(fn ($r) => mb_strtolower($r))
            ->contains($needle);
    }

    /** Atajos legibles */
    private function isAdmin(): bool
    {
        return $this->userHasRole('director') || $this->userHasRole('gerente') || $this->userHasRole('administrador');
    }
    private function isController(): bool
    {
        return $this->userHasRole('controlador');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedHeadquarterId(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function applyDate(): void
    {
        $this->validate([
            'ui_date_start' => ['required','date'],
            'ui_date_end'   => ['required','date'],
        ], [], [
            'ui_date_start' => 'fecha inicio',
            'ui_date_end'   => 'fecha fin',
        ]);

        $from = $this->ui_date_start;
        $to   = $this->ui_date_end;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $this->date_start = $from;
        $this->date_end   = $to;
        $this->resetPage();
    }

    /** Carga ids de sedes asignadas al usuario (N:N + primaria en users.headquarter_id) */
    private function loadUserHeadquarters(): void
    {
        $u = Auth::user();
        if (!$u) {
            $this->userHqIds = [];
            return;
        }

        $ids = $u->headquarters()->pluck('headquarters.id')->map(fn($v)=>(int)$v)->all();
        if ($u->headquarter_id && !in_array((int)$u->headquarter_id, $ids, true)) {
            $ids[] = (int)$u->headquarter_id;
        }
        $this->userHqIds = $ids;
    }

    // ===== Ciclo de vida =====
    public function mount(): void
    {
        $now   = now(config('app.timezone','America/Lima'));
        $today = $now->toDateString();

        $this->date_start    = $this->date_start ?: $today;
        $this->date_end      = $this->date_end   ?: $today;

        // 🔹 Inicializa formulario en hoy
        $this->date_register = $today;
        $this->date_payment  = $today;

        $this->ui_date_start = $this->date_start;
        $this->ui_date_end   = $this->date_end;

        $this->hour          = $now->format('H:i:s');

        // === INTEGRACIÓN ROLES/SEDES: catálogo de sedes para filtros y formularios
        $this->loadUserHeadquarters();
        if ($this->isAdmin()) {
            $this->headquarters = Headquarter::where('status','active')
                ->orderBy('name')->get(['id','name']);     // ✅ modelos Eloquent (no arrays)
        } else {
            $ids = $this->userHqIds ?: [-1];
            $this->headquarters = Headquarter::where('status','active')
                ->whereIn('id', $ids)
                ->orderBy('name')->get(['id','name']);     // ✅ modelos Eloquent (no arrays)
        }
    }

    // ===== Helpers =====
    private function normPlate(): string
    {
        return strtoupper(trim($this->plate ?? ''));
    }
    private function plateNeedle(): string
    {
        return str_replace('-', '', $this->normPlate());
    }

    /** Costo del día (PAGO/RETRASO) — para DEUDA no se aplica */
    private function prefillAmountFromCost(): void
    {
        if ($this->type_form === 'DEUDA') {
            $this->detected_cost = null;
            return;
        }

        $plate = $this->normPlate();
        $date  = ($this->type_form === 'RETRASO' && $this->date_payment)
            ? $this->date_payment
            : ($this->date_register ?: '');
        if ($plate === '' || $date === '') {
            $this->detected_cost = null;
            return;
        }

        $vehicle = Vehicle::query()
            ->whereRaw('REPLACE(UPPER(TRIM(plate)),"-","") = ?', [$this->plateNeedle()])
            ->where('status', 'active')
            ->first();

        // Vehículo activo pero con fecha de cese vencida → no vincular
        if ($vehicle && $vehicle->termination_date && $vehicle->termination_date < now(config('app.timezone', 'America/Lima'))->startOfDay()) {
            $vehicle = null;
        }

        if (!$vehicle) {
            $this->detected_cost = null;
            return;
        }

        $cost = CostPerPlateDay::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereDate('date', $date)
            ->value('amount');

        $this->detected_cost = $cost !== null ? (float)$cost : null;

        if (!is_null($this->detected_cost)) {
            $this->amount = number_format($this->detected_cost, 2, '.', '');
        }
    }

    /** Calcula deuda total pendiente (todas las filas debt_days de la placa) */
    private function recalcPendingDebt(?string $refDate = null): void
    {
        $plate = $this->normPlate();
        if ($plate === '') { $this->pending_debt = null; return; }

        // Si me pasan una fecha, uso su año/mes; si no, mes anterior al actual
        if ($refDate) {
            $y = (int)date('Y', strtotime($refDate));
            $m = (int)date('m', strtotime($refDate));
        } else {
            $prev = now(config('app.timezone','America/Lima'))->copy()->startOfMonth()->subMonth();
            $y = $prev->year; $m = $prev->month;
        }

        $vehicle = Vehicle::query()
            ->whereRaw('REPLACE(UPPER(TRIM(plate)),"-","") = ?', [$this->plateNeedle()])
            ->where('status', 'active')
            ->first();

        // Vehículo activo pero con fecha de cese vencida → no vincular
        if ($vehicle && $vehicle->termination_date && $vehicle->termination_date < now(config('app.timezone', 'America/Lima'))->startOfDay()) {
            $vehicle = null;
        }

        $q = DB::table('debt_days')
            ->select('id','total','amortized','exonerated')
            ->whereYear($this->debtDaysDateColumn, $y)
            ->whereMonth($this->debtDaysDateColumn, $m);

        if ($vehicle) $q->where('vehicle_id', $vehicle->id);
        else $q->whereRaw('UPPER(TRIM(legacy_plate)) = ?', [$plate]);

        $row = $q->first();
        if (!$row) { $this->pending_debt = 0.0; return; }

        $this->pending_debt = max(
            (float)$row->total - (float)$row->amortized - (float)$row->exonerated,
            0.0
        );
    }

    /** Aplica amortización FIFO: del mes más antiguo al más reciente */
    private function applyDebtAmortizations(?Vehicle $vehicle, string $normPlate, float $amount, int $paymentId): void
    {
        $tz        = config('app.timezone','America/Lima');
        $prevStart = now($tz)->copy()->startOfMonth()->subMonth();
        $prevEnd   = now($tz)->copy()->startOfMonth()->subSecond(); // último día del mes anterior

        $q = DB::table('debt_days')
            ->select('id','total','amortized','exonerated')
            ->whereYear($this->debtDaysDateColumn, $prevStart->year)
            ->whereMonth($this->debtDaysDateColumn, $prevStart->month);

        if ($vehicle) {
            $q->where('vehicle_id', $vehicle->id);
        } else {
            $q->whereRaw('UPPER(TRIM(legacy_plate)) = ?', [$normPlate]);
        }

        $row = $q->first();
        if (!$row) {
            throw ValidationException::withMessages([
                'amount' => 'No existe deuda del mes anterior para esta placa.'
            ]);
        }

        $pending = max((float)$row->total - (float)$row->amortized - (float)$row->exonerated, 0.0);
        $use = min($amount, $pending);

        if ($use <= 0.0001) {
            throw ValidationException::withMessages([
                'amount' => 'No hay deuda pendiente del mes anterior.'
            ]);
        }

        // Sumar al acumulado mensual
        $this->bumpDebtMonth((int)$row->id, (float)$use);

        // Crear detalle (una sola fila porque es un único mes)
        DB::table('debt_days_detail')->insert([
            'debt_days_id' => (int)$row->id,
            'exonerated'   => 0,
            'amortized'    => (float)$use,
            'detail'       => 'payment:'.$paymentId,
            'user_id'      => Auth::id(),
            'date'         => $prevEnd->toDateString(), // fecha dentro del mes anterior
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // si el monto > pending (no debería por la validación), queda remanente sin usar — ignoramos.
    }


    /** Reglas de negocio comunes (ajustadas: DEUDA sin restricción de fecha) */
    private function validateBusinessCommon(): void
    {
        if ($this->type_form === 'PAGO') {
            $today = now(config('app.timezone','America/Lima'))->toDateString();
            $this->date_register = $today;
            $this->date_payment  = $today;
        }

        // PAGO/RETRASO requieren costo del día
        if (in_array($this->type_form, ['PAGO','RETRASO'], true) && is_null($this->detected_cost)) {
            throw ValidationException::withMessages([
                'amount' => 'No hay costo configurado para la fecha/placa seleccionadas.',
            ]);
        }

        // RETRASO: no puede ser hoy
        if ($this->type_form === 'RETRASO' && $this->date_payment) {
            $tz = config('app.timezone','America/Lima');
            $now = now($tz);
            if ($now->isSameDay(\Carbon\Carbon::parse($this->date_payment, $tz))) {
                throw ValidationException::withMessages([
                    'date_payment' => 'El retraso no puede ser en la misma fecha de hoy.',
                ]);
            }
        }

        // RETRASO: no duplicar PAGO/RETRASO mismo día/placa
        if ($this->type_form === 'RETRASO' && $this->date_payment) {
            $dup = Payment::query()
                ->whereDate('date_payment', $this->date_payment)
                ->whereIn('type', ['PAGO','RETRASO'])
                ->whereRaw('REPLACE(UPPER(TRIM(legacy_plate)),"-","") = ?', [$this->plateNeedle()])
                ->exists();

            if ($dup) {
                throw ValidationException::withMessages([
                    'date_payment' => 'Ya existe un PAGO/RETRASO registrado para la placa en esa fecha.',
                ]);
            }
        }

        if ($this->type_form === 'DEUDA') {
            // Asegura que la fecha esté en el mes anterior (tu regla actual)
            $tz = config('app.timezone','America/Lima');
            $now = now($tz);
            $prevStart = $now->copy()->startOfMonth()->subMonth();
            $prevEnd   = $now->copy()->startOfMonth()->subSecond();

            if ($this->date_payment) {
                $dp = \Carbon\Carbon::parse($this->date_payment, $tz)->startOfDay();
                if (!($dp->betweenIncluded($prevStart, $prevEnd))) {
                    throw ValidationException::withMessages([
                        'date_payment' => 'Las amortizaciones DEUDA solo se registran para el mes anterior ('.$prevStart->format('Y-m').').',
                    ]);
                }
            }

            // 👇 Que la deuda visible coincida con el mes de la fecha elegida
            $this->recalcPendingDebt($this->date_payment);
            if (($this->pending_debt ?? 0) <= 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'No hay deuda pendiente del mes anterior para esta placa.',
                ]);
            }
        }
    }

    /** Suma/resta en el acumulador `amortized` de debt_days */
    private function bumpDebtMonth(int $debtDaysId, float $delta): void
    {
        DB::table('debt_days')->where('id', $debtDaysId)->update([
            $this->debtDaysAmortCol => DB::raw($this->debtDaysAmortCol.' + '.($delta + 0.0)),
            'updated_at'            => now(),
        ]);
    }

    // ===== Livewire listeners =====
    public function updatedPlate(): void
    {
        $this->prefillAmountFromCost();
        $this->recalcPendingDebt();
    }
    public function updatedDateRegister(): void
    {
        $this->prefillAmountFromCost();
    }
    public function updated($name, $value): void
    {
        if (in_array($name, ['plate','type_form'], true)) {
            // cambió placa o tipo => recalcular vistas auxiliares
            $this->recalcPendingDebt();
            $this->prefillAmountFromCost();
        }
        if ($name === 'date_register') {
            $this->prefillAmountFromCost();
        }
        // Si hay costo detectado (PAGO/RETRASO), forzar el amount
        if ($name === 'amount' && !is_null($this->detected_cost) && $this->type_form !== 'DEUDA') {
            $det = number_format($this->detected_cost, 2, '.', '');
            if ((string)$this->amount !== $det) {
                $this->amount = $det;
            }
        }
    }

    // ===== Modales =====
    public function openAddWindow(): void
    {
        $route = route('payments.add');

        $this->dispatch('url-open',["url" => $route]);
    }


    public function openEditWindow(int $id): void
    {
        $route = route('payments.edit',["id" => $id]);

        $this->dispatch('url-open',["url" => $route]);
    }

    public function updatedTypeForm($value): void
    {
        $tz = config('app.timezone','America/Lima');
        $today = now($tz)->toDateString();

        if ($value === 'PAGO') {
            $this->date_register = $today;
            $this->date_payment  = $today;
            $this->prefillAmountFromCost();
        }

        if ($value === 'RETRASO') {
            // por defecto usa hoy, pero el usuario puede cambiarla
            $this->date_payment = $today;
            $this->prefillAmountFromCost();
        }

        if ($value === 'DEUDA') {
            // por defecto, última fecha del mes anterior
            $prevEnd = now($tz)->copy()->startOfMonth()->subSecond();
            $this->date_payment = $prevEnd->toDateString();
            $this->detected_cost = null;

            $this->recalcPendingDebt($this->date_payment);
            $this->amount = $this->pending_debt !== null
                ? number_format($this->pending_debt, 2, '.', '')
                : '';
        }
    }

    private function resetForm(): void
    {
        $this->reset([
            'paymentId','plate','serie','date_register','date_payment','hour',
            'type_form','headquarter_id_form','amount','latitude','longitude',
            'detected_cost','pending_debt',
        ]);

        $this->clearValidationState();
    }

    // ===== Guardar / Actualizar =====
    public function save(): void
    {
        $today = now(config('app.timezone','America/Lima'))->toDateString();
        $this->date_register = $today;
        if ($this->type_form === 'PAGO') {
            $today = now(config('app.timezone','America/Lima'))->toDateString();
            $this->date_register = $today;
            $this->date_payment  = $today;
        }

        // === INTEGRACIÓN ROLES/SEDES: controller solo puede usar sedes asignadas
        if (
            !$this->isAdmin()
            && $this->headquarter_id_form
            && !in_array((int)$this->headquarter_id_form, $this->userHqIds, true)
        ) {
            $this->addError('headquarter_id_form', 'No tienes acceso a esta sucursal.');
            return;
        }

        // Calcular auxiliares y validar
        $this->recalcPendingDebt();
        $this->prefillAmountFromCost();
        $this->validate();
        $this->validateBusinessCommon();

        // Duplicado exacto (solo si hay date_payment)
        if ($this->date_payment) {
            $dupExact = Payment::query()
                ->where('type', $this->type_form)
                ->whereDate('date_payment', $this->date_payment)
                ->whereRaw('REPLACE(UPPER(TRIM(legacy_plate)),"-","") = ?', [$this->plateNeedle()])
                ->exists();
            if ($dupExact && $this->type_form !== 'DEUDA') {
                throw ValidationException::withMessages([
                    'date_payment' => 'Ya existe un registro con el mismo Tipo y Fecha de Pago para esta placa.',
                ]);
            }
        }

        $vehicle = Vehicle::whereRaw(
            'REPLACE(UPPER(TRIM(plate)),"-","") = ?',
            [$this->plateNeedle()]
        )->where('status', 'active')->first();

        // Vehículo activo pero con fecha de cese vencida → no vincular
        if ($vehicle && $vehicle->termination_date && $vehicle->termination_date < now(config('app.timezone', 'America/Lima'))->startOfDay()) {
            $vehicle = null;
        }

        DB::transaction(function () use ($vehicle) {
            $payment = Payment::create([
                'vehicle_id'     => $vehicle?->id,
                'legacy_plate'   => $this->normPlate(),
                'serie'          => $this->serie,
                'date_register'  => $this->date_register,
                'date_payment'   => $this->date_payment,
                'hour'           => $this->hour,
                'type'           => $this->type_form,
                'headquarter_id' => $this->headquarter_id_form,
                'user_id'        => Auth::id(),
                'amount'         => (float)$this->amount,
                'latitude'       => $this->latitude,
                'longitude'      => $this->longitude,
            ]);

            if ($this->type_form === 'DEUDA') {
                $this->applyDebtAmortizations($vehicle, $this->normPlate(), (float)$this->amount, (int)$payment->id);
            }
        });

        $dateStart = $this->ui_date_start;
        $dateEnd   = $this->ui_date_end;

        $this->resetForm();
        $this->dispatch('modal-close', ['name' => 'modalAddPayment']);
        $this->dispatch('successAlert', ['message' => 'Pago registrado correctamente']);
        $this->dispatch('restore-payment-dates', ['date_start' => $dateStart, 'date_end' => $dateEnd]);
    }

    public function update(): void
    {
        if (!$this->paymentId) return;

        if ($this->type_form === 'PAGO') {
            $today = now(config('app.timezone','America/Lima'))->toDateString();
            $this->date_register = $today;
            $this->date_payment  = $today;
        }

        // === INTEGRACIÓN ROLES/SEDES: controller solo puede usar sedes asignadas
        if (
            !$this->isAdmin()
            && $this->headquarter_id_form
            && !in_array((int)$this->headquarter_id_form, $this->userHqIds, true)
        ) {
            $this->addError('headquarter_id_form', 'No tienes acceso a esta sucursal.');
            return;
        }

        $this->recalcPendingDebt();
        $this->prefillAmountFromCost();
        $this->validate();
        $this->validateBusinessCommon();

        $p = Payment::findOrFail($this->paymentId);

        $vehicle = Vehicle::whereRaw(
            'REPLACE(UPPER(TRIM(plate)),"-","") = ?',
            [$this->plateNeedle()]
        )->where('status', 'active')->first();

        // Vehículo activo pero con fecha de cese vencida → no vincular
        if ($vehicle && $vehicle->termination_date && $vehicle->termination_date < now(config('app.timezone', 'America/Lima'))->startOfDay()) {
            $vehicle = null;
        }

        DB::transaction(function () use ($p, $vehicle) {
            $oldType = $p->type;

            // Revertir amortizaciones previas si eran DEUDA
            if ($oldType === 'DEUDA') {
                $olds = DB::table('debt_days_detail')
                    ->where('detail', 'like', 'payment:'.$p->id.'%')
                    ->get();

                foreach ($olds as $od) {
                    $this->bumpDebtMonth((int)$od->debt_days_id, -1 * (float)$od->amortized);
                    DB::table('debt_days_detail')->where('id', $od->id)->delete();
                }
            }

            // Actualizar pago
            $p->update([
                'vehicle_id'     => $vehicle?->id,
                'legacy_plate'   => $this->normPlate(),
                'serie'          => $this->serie,
                'date_register'  => $this->date_register,
                'date_payment'   => $this->date_payment,
                'hour'           => $this->hour,
                'type'           => $this->type_form,
                'headquarter_id' => $this->headquarter_id_form,
                'user_id'        => Auth::id(),
                'amount'         => (float)$this->amount,
                'latitude'       => $this->latitude,
                'longitude'      => $this->longitude,
            ]);

            // Aplicar nuevas amortizaciones si ahora es DEUDA
            if ($this->type_form === 'DEUDA') {
                $this->applyDebtAmortizations($vehicle, $this->normPlate(), (float)$this->amount, (int)$p->id);
            }
        });

        $dateStart = $this->ui_date_start;
        $dateEnd   = $this->ui_date_end;

        $this->resetForm();
        $this->dispatch('modal-close', ['name' => 'modalEditPayment']);
        $this->dispatch('successAlert', ['message' => 'Pago actualizado correctamente']);
        $this->dispatch('restore-payment-dates', ['date_start' => $dateStart, 'date_end' => $dateEnd]);
    }

    // ===== Listado / Export =====
    public function render()
    {
        // Query base con todos los filtros/búsquedas
        $q = Payment::query()
            ->when($this->date_start && $this->date_end,
                fn($q) => $q->whereBetween('date_register', [$this->date_start, $this->date_end]),
                fn($q) => $q->whereBetween('date_register', [now()->toDateString(), now()->toDateString()])
            )
            ->when($this->headquarter_id !== '' && $this->headquarter_id !== null,
                fn($q) => $q->where('headquarter_id', $this->headquarter_id)
            )
            ->when($this->type !== '',
                fn($q) => $q->where('type', $this->type)
            )
            ->when(trim($this->search) !== '', function ($q) {
                $term  = trim($this->search);
                $plate = strtoupper($term);
                switch ($this->filter) {
                    case '1': // Placa
                        $q->where(function ($qq) use ($plate) {
                            $qq->where('legacy_plate', 'like', '%'.$plate.'%')
                                ->orWhereHas('vehicle', fn($v) => $v->where('plate','like','%'.$plate.'%'));
                        });
                        break;
                    case '2': // Usuario
                        $q->whereHas('user', fn($u) => $u->where('name','like','%'.$term.'%'));
                        break;
                    case '3': // Serie
                        $q->where('serie','like','%'.$term.'%');
                        break;
                    default: // Mixto
                        $q->where(function ($qq) use ($term, $plate) {
                            $qq->where('serie','like','%'.$term.'%')
                                ->orWhere('legacy_plate','like','%'.$plate.'%')
                                ->orWhereHas('vehicle', fn($v) => $v->where('plate','like','%'.$plate.'%'))
                                ->orWhereHas('user', fn($u) => $u->where('name','like','%'.$term.'%'));
                        });
                }
            });

        // === INTEGRACIÓN ROLES/SEDES: admin ve todo; controller solo sus propios registros
        if (!$this->isAdmin()) {
            $q->where('user_id', Auth::id());
        }

        // Total general (una sola query de sum, sin cargar modelos)
        $total_general = (clone $q)->sum('amount');

        // Datos paginados con eager loading
        $payments = $q->with(['vehicle:id,plate', 'user:id,name,username', 'headquarter:id,name'])
            ->orderBy('date_register')
            ->orderBy('hour')
            ->get();

        return view('livewire.payments.index', [
            'payments'      => $payments,
            'headquarters'  => $this->headquarters,
            'total_general' => $total_general
        ]);
    }

    public function export()
    {
        $route = route('exports.payments', [
            "search"        => $this->search,
            "filter"        => $this->filter,
            "date_start"    => $this->date_start,
            "date_end"      => $this->date_end,
            "headquarterId" => $this->headquarter_id,
            "type"          => $this->type
        ]);

        $this->dispatch('url-open', ["url" => $route]);
    }

    private function clearValidationState(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function cancelAdd(): void
    {
        $this->resetForm();           // limpia campos
        $this->clearValidationState(); // limpia errores
        $this->dispatch('modal-close', ['name' => 'modalAddPayment']);
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->clearValidationState();
        $this->dispatch('modal-close', ['name' => 'modalEditPayment']);
    }

    public function updatedDatePayment(): void
    {
        if ($this->type_form === 'RETRASO') {
            // Para retraso el costo es el de la fecha que estás seleccionando
            $this->prefillAmountFromCost();
        }

        if ($this->type_form === 'DEUDA') {
            // La deuda visible y el monto siguen al mes de la fecha elegida
            $this->recalcPendingDebt($this->date_payment);
            $this->amount = $this->pending_debt !== null
                ? number_format($this->pending_debt, 2, '.', '')
                : '';
        }
    }

    public function daily()
    {
        $route = route('payments.daily');

        $this->dispatch('url-open', ["url" => $route]);
    }

    public function monthly()
    {
        $route = route('payments.monthly');

        $this->dispatch('url-open', ["url" => $route]);
    }

    public function stats()
    {
        $route = route('payments.stats');

        $this->dispatch('url-open', ["url" => $route]);
    }
}
