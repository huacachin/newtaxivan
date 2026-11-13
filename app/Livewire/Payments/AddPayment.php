<?php

namespace App\Livewire\Payments;

use App\Models\CostPerPlateDay;
use App\Models\Headquarter;
use App\Models\Payment;
use App\Models\Vehicle;
use App\Services\DebtPaymentAllocator;
use App\Traits\NormalizesDecimals;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AddPayment extends Component
{
    use NormalizesDecimals;

    // ===== Formulario (mismos campos del modal) =====
    public ?int $paymentId = null; // no se usa al crear, pero queda por compatibilidad
    public string $plate = '';
    public ?string $serie = null;
    public string $date_register = '';
    public ?string $date_payment = null;
    protected string $hour = '';                 // HH:mm
    public string $type_form = '';            // PAGO | DEUDA | RETRASO
    public ?int $headquarter_id_form = null;  // FK
    public float|string $amount = '';
    public ?float $latitude = null;
    public ?float $longitude = null;

    // auxiliares
    public ?float $detected_cost = null;  // costo del día (PAGO/RETRASO)
    public ?float $pending_debt = null;   // deuda pendiente (todos los meses)

    /** @var list<array{id: int, date: string, label: string, pending: float}> Meses pendientes, del más antiguo */
    public array $debtMonths = [];

    /** DEUDA: mes que paga (debt_days.id): siempre el pendiente más antiguo; un pago por mes */
    public ?int $debt_month_id = null;

    // Chips de sugerencias para el campo amount
    public array $amountSuggestions = [];

    // sedes para el select
    public $headquarters = [];

    // ===== Roles/Sedes =====
    private array $userHqIds = [];

    // ===== Validaciones (mismas reglas del modal, con callbacks) =====
    protected function rules(): array
    {
        $rules = [
            'plate' => ['required','string','min:6','max:20'],
            'serie'               => ['required','string','max:50'],
            'date_register'       => ['required','date'],
            'date_payment'        => ['nullable','date'],
            'type_form'           => ['required','in:PAGO,DEUDA,RETRASO'],
            'headquarter_id_form' => ['required','integer','exists:headquarters,id'],
            'amount'              => ['required','numeric','gt:0'],
            'latitude'            => ['required','numeric'],
            'longitude'           => ['required','numeric'],
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

        // DEUDA: un solo mes, y no más de lo que debe ese mes
        if ($this->type_form === 'DEUDA') {
            $rules['debt_month_id'] = ['required', 'integer'];
            $rules['amount'][] = function ($attr, $val, $fail) {
                if (($this->pending_debt ?? 0) <= 0.0) {
                    $fail('Esta placa no tiene deuda pendiente.');
                    return;
                }
                $month = $this->selectedDebtMonth();
                if ($month && (float) $val - $month['pending'] > 0.0001) {
                    $fail('El monto no puede pasar lo que debe '.$month['label'].' (S/ '.number_format($month['pending'], 2).'). Los otros meses van en pagos aparte.');
                }
            };
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'plate.required'               => 'La placa es obligatoria.',
            'serie.required'               => 'La serie es obligatoria.',
            'date_register.required'       => 'La fecha de registro es obligatoria.',
            'type_form.required'           => 'El tipo es obligatorio.',
            'type_form.in'                 => 'Tipo inválido.',
            'headquarter_id_form.required' => 'La sucursal es obligatoria.',
            'headquarter_id_form.exists'   => 'Sucursal no válida.',
            'amount.required'              => 'El monto es obligatorio.',
            'amount.numeric'               => 'El monto debe ser numérico.',
            'amount.gt'                    => 'El monto debe ser mayor a 0.',
            'debt_month_id.required'       => 'Elige el mes que paga.',
        ];
    }

    // ===== Helpers de rol/sedes =====
    private function userHasRole(string $needle): bool
    {
        $u = Auth::user();
        if (!$u) return false;
        $needle = mb_strtolower($needle);
        return $u->getRoleNames()->map(fn($r)=>mb_strtolower($r))->contains($needle);
    }
    private function isAdmin(): bool { return $this->userHasRole('director') || $this->userHasRole('gerente') || $this->userHasRole('administrador'); }

    private function loadUserHeadquarters(): void
    {
        $u = Auth::user();
        if (!$u) { $this->userHqIds = []; return; }

        $ids = $u->headquarters()->pluck('headquarters.id')->map(fn($v)=>(int)$v)->all();
        if ($u->headquarter_id && !in_array((int)$u->headquarter_id, $ids, true)) {
            $ids[] = (int)$u->headquarter_id;
        }
        $this->userHqIds = $ids;
    }

    /** Sedes permitidas (pivot + primaria), ints y únicas */
    private function allowedHqIds(): array
    {
        $u = Auth::user();
        if (!$u) return [];
        $ids = $u->headquarters()->pluck('headquarters.id')->map(fn($v)=>(int)$v)->all();
        $primary = (int) ($u->headquarter_id ?? 0);
        if ($primary && !in_array($primary, $ids, true)) $ids[] = $primary;
        return array_values(array_unique(array_map('intval', $ids)));
    }

    // ===== Ciclo de vida =====
    public function mount(): void
    {
        $tz    = config('app.timezone','America/Lima');
        $now   = now($tz);
        $today = $now->toDateString();

        // Inicializa formulario en hoy
        $this->date_register = $today;
        $this->date_payment  = $today;
        $this->type_form     = 'PAGO';

        // Cargar sedes y ordenar con primaria primero + seleccionar por defecto
        $this->loadUserHeadquarters();
        $primaryId = (int) (Auth::user()?->headquarter_id ?? 0);

        if ($this->isAdmin()) {
            $this->headquarters = Headquarter::where('status','active')
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$primaryId ?: 0])
                ->orderBy('name')
                ->get(['id','name']);
        } else {
            $ids = $this->allowedHqIds() ?: [-1];
            $this->headquarters = Headquarter::where('status','active')
                ->whereIn('id', $ids)
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$primaryId ?: 0])
                ->orderBy('name')
                ->get(['id','name']);
        }

        if (!$this->headquarter_id_form) {
            if ($primaryId && $this->headquarters->contains('id', $primaryId)) {
                $this->headquarter_id_form = $primaryId;
            } else {
                $this->headquarter_id_form = optional($this->headquarters->first())->id;
            }
        }
    }

    // ===== Helpers dominio =====
    private function normPlate(): string
    {
        return strtoupper(trim($this->plate ?? ''));
    }
    private function plateNeedle(): string
    {
        return str_replace('-', '', $this->normPlate());
    }

    private function prefillAmountFromCost(): void
    {
        if ($this->type_form === 'DEUDA') { $this->detected_cost = null; return; }

        $plate = $this->normPlate();
        $date  = ($this->type_form === 'RETRASO' && $this->date_payment)
            ? $this->date_payment
            : ($this->date_register ?: '');
        if ($plate === '' || $date === '') { $this->detected_cost = null; return; }

        $vehicle = Vehicle::query()
            ->whereRaw('REPLACE(UPPER(TRIM(plate)),"-","") = ?', [$this->plateNeedle()])
            ->where('status', 'active')
            ->first();

        // Vehículo activo pero con fecha de cese vencida → no vincular
        if ($vehicle && $vehicle->termination_date && $vehicle->termination_date < now(config('app.timezone', 'America/Lima'))->startOfDay()) {
            $vehicle = null;
        }

        if (!$vehicle) { $this->detected_cost = null; return; }

        $cost = CostPerPlateDay::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereDate('date', $date)
            ->value('amount');

        $this->detected_cost = $cost !== null ? (float)$cost : null;
        if (!is_null($this->detected_cost) && $this->type_form !== 'DEUDA') {
            $this->amount = number_format($this->detected_cost, 2, '.', '');
        }
    }

    /** Deuda pendiente de la placa: todos los meses, del más antiguo al más reciente */
    private function recalcPendingDebt(): void
    {
        $plate = $this->normPlate();
        if ($plate === '') {
            $this->pending_debt = null;
            $this->debtMonths = [];

            return;
        }

        $this->debtMonths = app(DebtPaymentAllocator::class)->pendingMonths($plate);
        $this->pending_debt = DebtPaymentAllocator::total($this->debtMonths);
    }

    /**
     * DEUDA: paga el mes pendiente más antiguo (los demás esperan a que termine),
     * monto sugerido = lo que debe ese mes y fecha de pago = fin de ese mes.
     */
    private function syncDebtFields(): void
    {
        $this->detected_cost = null;
        $this->recalcPendingDebt();
        $this->debt_month_id = $this->debtMonths[0]['id'] ?? null;
        $this->fillFromDebtMonth();
    }

    /** Monto y fecha de pago del mes elegido */
    private function fillFromDebtMonth(): void
    {
        $month = $this->selectedDebtMonth();
        $this->date_payment = $month
            ? DebtPaymentAllocator::coverDate([$month])
            : now(config('app.timezone', 'America/Lima'))->toDateString();
        $this->amount = $month ? number_format($month['pending'], 2, '.', '') : '';
    }

    /** @return array{id: int, date: string, label: string, pending: float}|null */
    private function selectedDebtMonth(): ?array
    {
        return collect($this->debtMonths)->firstWhere('id', $this->debt_month_id);
    }

    /** El mes no se elige: siempre vuelve al pendiente más antiguo */
    public function updatedDebtMonthId(): void
    {
        $this->syncDebtFields();
    }

    /** Desde el aviso de deuda: cobrar el mes más antiguo (pasa a tipo Deuda) */
    public function collectDebt(): void
    {
        $this->type_form = 'DEUDA';
        $this->syncDebtFields();
    }

    /** Reglas de negocio (idénticas) */
    private function validateBusinessCommon(): void
    {
        if ($this->type_form === 'PAGO') {
            $today = now(config('app.timezone','America/Lima'))->toDateString();
            $this->date_register = $today;
            $this->date_payment  = $today;
        }

        if (in_array($this->type_form, ['PAGO','RETRASO'], true) && is_null($this->detected_cost)) {
            throw ValidationException::withMessages([
                'amount' => 'No hay costo configurado para la fecha/placa seleccionadas.',
            ]);
        }

        if ($this->type_form === 'RETRASO' && $this->date_payment) {
            $tz = config('app.timezone','America/Lima');
            $now = now($tz);
            if ($now->isSameDay(\Carbon\Carbon::parse($this->date_payment, $tz))) {
                throw ValidationException::withMessages([
                    'date_payment' => 'El retraso no puede ser en la misma fecha de hoy.',
                ]);
            }
        }

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

        // RETRASO de un día que ya quedó en la deuda de su mes: se cobraría dos veces
        if ($this->type_form === 'RETRASO' && $this->date_payment) {
            $conflict = app(DebtPaymentAllocator::class)->closedDayConflict($this->normPlate(), $this->date_payment);
            if ($conflict) {
                throw ValidationException::withMessages(['date_payment' => $conflict]);
            }
        }

        // DEUDA: un mes pendiente, el que se eligió
        if ($this->type_form === 'DEUDA') {
            $this->recalcPendingDebt();
            if (($this->pending_debt ?? 0) <= 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'Esta placa no tiene deuda pendiente.',
                ]);
            }
            if (! $this->selectedDebtMonth()) {
                throw ValidationException::withMessages([
                    'debt_month_id' => 'Elige el mes que paga.',
                ]);
            }
        }
    }

    // ===== Listeners de UI =====
    public function updatedPlate(): void
    {
        $this->plate = strtoupper($this->plate ?? '');
        $this->prefillAmountFromCost();
        if ($this->type_form === 'DEUDA') {
            $this->syncDebtFields();
        } else {
            $this->recalcPendingDebt();
        }
        $this->recomputeAmountSuggestions();
    }

    protected function recomputeAmountSuggestions(): void
    {
        $plate = preg_replace('/\s+/', '', strtoupper(trim((string) $this->plate)));
        if (strlen($plate) < 6) { $this->amountSuggestions = []; return; }

        $vehicle = Vehicle::whereRaw('UPPER(TRIM(plate)) = ?', [$plate])->first();
        if (!$vehicle) { $this->amountSuggestions = []; return; }

        $since = now(config('app.timezone','America/Lima'))->subDays(180)->toDateString();
        $this->amountSuggestions = \DB::table('payments')
            ->where('vehicle_id', $vehicle->id)
            ->where('amount', '>', 0)
            ->where('date_payment', '>=', $since)
            ->select('amount', \DB::raw('COUNT(*) as c'))
            ->groupBy('amount')->orderByDesc('c')->limit(3)
            ->pluck('amount')
            ->map(fn ($v) => number_format((float) $v, 2, '.', ''))
            ->values()->all();
    }
    public function updated($name, $value): void
    {
        if ($name === 'type_form' || $name === 'plate') {
            $this->recalcPendingDebt();
            $this->prefillAmountFromCost();
        }
        if ($name === 'date_register') {
            $this->prefillAmountFromCost();
        }
        if ($name === 'amount' && !is_null($this->detected_cost) && $this->type_form !== 'DEUDA') {
            $det = number_format($this->detected_cost, 2, '.', '');
            if ((string)$this->amount !== $det) $this->amount = $det;
        }
    }
    public function updatedDatePayment(): void
    {
        if ($this->type_form === 'RETRASO') {
            $this->prefillAmountFromCost();
        }
    }
    public function updatedTypeForm($value): void
    {
        $tz = config('app.timezone','America/Lima');
        $today = now($tz)->toDateString();

        if ($value === 'PAGO') {
            $this->date_register = $today;
            $this->date_payment  = $today;
            $this->prefillAmountFromCost();
        } elseif ($value === 'RETRASO') {
            $this->date_payment = $today;
            $this->prefillAmountFromCost();
        } elseif ($value === 'DEUDA') {
            $this->syncDebtFields();
        }
    }

    private function resetForm(): void
    {
        $tz = config('app.timezone','America/Lima');
        $now = now($tz);
        $today = $now->toDateString();

        $this->paymentId = null;
        $this->plate = '';
        $this->serie = null;
        $this->date_register = $today;
        $this->date_payment  = $today;
        // Sede primaria o primera disponible
        $primaryId = (int) (Auth::user()?->headquarter_id ?? 0);
        if ($this->headquarters instanceof \Illuminate\Support\Collection && $this->headquarters->isNotEmpty()) {
            if ($primaryId && $this->headquarters->contains('id', $primaryId)) {
                $this->headquarter_id_form = $primaryId;
            } else {
                $this->headquarter_id_form = optional($this->headquarters->first())->id;
            }
        }
        $this->type_form = '';
        $this->amount = '';
        $this->latitude = null;
        $this->longitude = null;

        $this->detected_cost = null;
        $this->pending_debt = null;
        $this->debtMonths = [];
        $this->debt_month_id = null;

        $this->resetErrorBag();
        $this->resetValidation();
    }

    // ===== Guardar (misma lógica del modal, sin dispatch/redirect) =====
    public function save(): void
    {
        if (empty($this->latitude) || empty($this->longitude)) {
            $this->dispatch('errorAlert', ['message' => 'Activa tu ubicación para poder agregar']);
            return;
        }

        $tz    = config('app.timezone','America/Lima');
        $now   = now($tz);
        $today = $now->toDateString();

        // 👉 Hora tomada en el momento de guardar (solo backend)
        $this->hour = $now->format('H:i:s');

        // Si es PAGO, forzamos hoy
        if ($this->type_form === 'PAGO') {
            $this->date_register = $today;
            $this->date_payment  = $today;
        }

        // Validación de acceso a sede (no-admin)
        if (!$this->isAdmin()) {
            $allowed = $this->allowedHqIds();
            $chosen  = (int) ($this->headquarter_id_form ?? 0);
            if (!$chosen || !in_array($chosen, $allowed, true)) {
                $this->addError('headquarter_id_form', 'No tienes acceso a esta sucursal.');
                return;
            }
        }

        // Calcular auxiliares + validar
        $this->serie = trim((string) $this->serie) ?: null;
        $this->recalcPendingDebt();
        $this->prefillAmountFromCost();
        $this->normalizeDecimalProps(['amount']);
        $this->validate();
        $this->validateBusinessCommon();

        // Duplicado exacto (por tipo/fecha/placa) si hay date_payment
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
            $allocator = app(DebtPaymentAllocator::class);
            $parts = [];
            if ($this->type_form === 'DEUDA') {
                // Un solo mes, con las filas bloqueadas
                $parts = $allocator->planMonth($this->normPlate(), $this->debt_month_id, (float) $this->amount);
                $this->date_payment = DebtPaymentAllocator::coverDate($parts);
            }

            $payment = Payment::create([
                'vehicle_id'     => $vehicle?->id,
                'legacy_plate'   => $this->normPlate(),
                'serie'          => $this->serie,
                'date_register'  => $this->date_register,
                'date_payment'   => $this->date_payment,
                'hour'           => $this->hour,        // 👉 siempre backend
                'type'           => $this->type_form,
                'headquarter_id' => $this->headquarter_id_form,
                'user_id'        => Auth::id(),
                'amount'         => (float)$this->amount,
                'latitude'       => $this->latitude,
                'longitude'      => $this->longitude,
            ]);

            if ($parts) {
                $allocator->record((int) $payment->id, $parts, $this->date_register);
            }
        });

        session()->flash('payment_success', 'Pago registrado correctamente.');
        $this->resetForm();

        redirect()->route('payments.index');
    }



    public function render(): View
    {
        return view('livewire.payments.add-payment', [
            'headquarters' => $this->headquarters,
        ]);
    }
}
