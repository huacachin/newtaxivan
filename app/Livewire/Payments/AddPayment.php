<?php

namespace App\Livewire\Payments;

use App\Models\CostPerPlateDay;
use App\Models\Headquarter;
use App\Models\Payment;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AddPayment extends Component
{
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
    public ?float $pending_debt = null;   // deuda pendiente (mes anterior)

    // sedes para el select
    public $headquarters = [];

    // ===== Config de deuda (igual que tu Index) =====
    private string $debtDaysDateColumn = 'date';
    private string $debtDaysAmortCol   = 'amortized';

    // ===== Roles/Sedes =====
    private array $userHqIds = [];

    // ===== Validaciones (mismas reglas del modal, con callbacks) =====
    protected function rules(): array
    {
        $rules = [
            'plate'               => ['required','string','max:20'],
            'serie'               => ['nullable','string','max:50'],
            'date_register'       => ['required','date'],
            'date_payment'        => ['nullable','date'],
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
            'type_form.required'           => 'El tipo es obligatorio.',
            'type_form.in'                 => 'Tipo inválido.',
            'headquarter_id_form.required' => 'La sucursal es obligatoria.',
            'headquarter_id_form.exists'   => 'Sucursal no válida.',
            'amount.required'              => 'El monto es obligatorio.',
            'amount.numeric'               => 'El monto debe ser numérico.',
            'amount.gt'                    => 'El monto debe ser mayor a 0.',
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
    private function isAdmin(): bool { return $this->userHasRole('admin'); }

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
            ->first();
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

    private function recalcPendingDebt(?string $refDate = null): void
    {
        $plate = $this->normPlate();
        if ($plate === '') { $this->pending_debt = null; return; }

        if ($refDate) {
            $y = (int)date('Y', strtotime($refDate));
            $m = (int)date('m', strtotime($refDate));
        } else {
            $prev = now(config('app.timezone','America/Lima'))->copy()->startOfMonth()->subMonth();
            $y = $prev->year; $m = $prev->month;
        }

        $vehicle = Vehicle::query()
            ->whereRaw('REPLACE(UPPER(TRIM(plate)),"-","") = ?', [$this->plateNeedle()])
            ->first();

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

        if ($this->type_form === 'DEUDA') {
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

            $this->recalcPendingDebt($this->date_payment);
            if (($this->pending_debt ?? 0) <= 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'No hay deuda pendiente del mes anterior para esta placa.',
                ]);
            }
        }
    }

    private function bumpDebtMonth(int $debtDaysId, float $delta): void
    {
        DB::table('debt_days')->where('id', $debtDaysId)->update([
            $this->debtDaysAmortCol => DB::raw($this->debtDaysAmortCol.' + '.($delta + 0.0)),
            'updated_at'            => now(),
        ]);
    }

    // ===== Listeners de UI =====
    public function updatedPlate(): void
    {
        $this->plate = strtoupper($this->plate ?? '');
        $this->prefillAmountFromCost();
        $this->recalcPendingDebt();
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
        if ($this->type_form === 'DEUDA') {
            $this->recalcPendingDebt($this->date_payment);
            $this->amount = $this->pending_debt !== null
                ? number_format($this->pending_debt, 2, '.', '')
                : '';
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

        $this->resetErrorBag();
        $this->resetValidation();
    }

    // ===== Guardar (misma lógica del modal, sin dispatch/redirect) =====
    public function save(): void
    {
        $tz    = config('app.timezone','America/Lima');
        $now   = now($tz);
        $today = $now->toDateString();

        // 👉 Hora tomada en el momento de guardar (solo backend)
        $this->hour = $now->format('H:i'); // o 'H:i:s' si tu columna lo requiere

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
        $this->recalcPendingDebt();
        $this->prefillAmountFromCost();
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
        )->first();

        DB::transaction(function () use ($vehicle) {
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

            if ($this->type_form === 'DEUDA') {
                $this->applyDebtAmortizations(
                    $vehicle,
                    $this->normPlate(),
                    (float)$this->amount,
                    (int)$payment->id
                );
            }
        });

        session()->flash('add_success', true);
        $this->resetForm();
    }



    // ===== Amortización (idéntica a tu Index) =====
    private function applyDebtAmortizations(?Vehicle $vehicle, string $normPlate, float $amount, int $paymentId): void
    {
        $tz        = config('app.timezone','America/Lima');
        $prevStart = now($tz)->copy()->startOfMonth()->subMonth();
        $prevEnd   = now($tz)->copy()->startOfMonth()->subSecond();

        $q = DB::table('debt_days')
            ->select('id','total','amortized','exonerated')
            ->whereYear($this->debtDaysDateColumn, $prevStart->year)
            ->whereMonth($this->debtDaysDateColumn, $prevStart->month);

        if ($vehicle) $q->where('vehicle_id', $vehicle->id);
        else $q->whereRaw('UPPER(TRIM(legacy_plate)) = ?', [$normPlate]);

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

        $this->bumpDebtMonth((int)$row->id, (float)$use);

        DB::table('debt_days_detail')->insert([
            'debt_days_id' => (int)$row->id,
            'exonerated'   => 0,
            'amortized'    => (float)$use,
            'detail'       => 'payment:'.$paymentId,
            'user_id'      => Auth::id(),
            'date'         => $prevEnd->toDateString(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function render(): View
    {
        return view('livewire.payments.add-payment', [
            'headquarters' => $this->headquarters,
        ]);
    }
}
