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
use Livewire\Attributes\On;
use Livewire\Component;

class EditPayment extends Component
{
    // ===== Identificador =====
    public ?int $paymentId = null;

    // ===== Formulario (mismos campos del modal) =====
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

    // Auxiliares
    public ?float $detected_cost = null;  // costo del día (PAGO/RETRASO)
    public ?float $pending_debt = null;   // deuda pendiente (mes anterior)

    // Sedes para el select
    public $headquarters = [];

    // ===== Config de deuda (igual que tu Index) =====
    private string $debtDaysDateColumn = 'date';
    private string $debtDaysAmortCol   = 'amortized';

    // ===== Roles/Sedes =====
    private array $userHqIds = [];

    // ==============================
    // Validaciones (idénticas a tu Index)
    // ==============================
    protected function rules(): array
    {
        $rules = [
            'plate'               => ['required','string','max:20'],
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
    // Helpers de rol/sedes
    // ==============================
    private function userHasRole(string $needle): bool
    {
        $u = Auth::user();
        if (!$u) return false;
        $needle = mb_strtolower($needle);
        return $u->getRoleNames()->map(fn($r)=>mb_strtolower($r))->contains($needle);
    }
    private function isAdmin(): bool { return $this->userHasRole('director') || $this->userHasRole('gerente'); }

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

    // ==============================
    // Ciclo de vida
    // ==============================
    public function mount(int $id): void
    {
        $this->paymentId = $id;

        // Cargar sedes (primaria primero)
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

        $this->loadRowOrAbort();           // Carga el registro a editar
        $this->recalcPendingDebt();        // Calcula deuda visible
        $this->prefillAmountFromCost();    // Calcula costo si aplica
    }

    private function loadRowOrAbort(): void
    {
        $p = Payment::with(['headquarter','vehicle'])->find($this->paymentId);
        if (!$p) abort(404);

        $this->plate               = $p->legacy_plate ?: ($p->vehicle->plate ?? '');
        $this->serie               = $p->serie ?? '';
        $this->date_register       = $p->date_register ? $p->date_register->format('Y-m-d') : '';
        $this->date_payment        = $p->date_payment ? $p->date_payment->format('Y-m-d') : '';
        $this->hour                = $p->hour ? substr($p->hour, 0, 5) : '';
        $this->type_form           = $p->type ? strtoupper($p->type) : '';
        $this->headquarter_id_form = $p->headquarter_id ?? null;
        $this->amount              = $p->amount !== null ? (float)$p->amount : '';
        $this->latitude            = $p->latitude;
        $this->longitude           = $p->longitude;
    }

    // ==============================
    // Dominio
    // ==============================
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

    /** Deuda total pendiente (mes anterior o según refDate) */
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

    /** Reglas de negocio (mismas que en Index) */
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

    /** Suma/resta en el acumulador `amortized` de debt_days */
    private function bumpDebtMonth(int $debtDaysId, float $delta): void
    {
        DB::table('debt_days')->where('id', $debtDaysId)->update([
            $this->debtDaysAmortCol => DB::raw($this->debtDaysAmortCol.' + '.($delta + 0.0)),
            'updated_at'            => now(),
        ]);
    }

    /** Aplica amortización del mes anterior */
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

    // ==============================
    // Listeners UI
    // ==============================
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

    // ==============================
    // Actualizar
    // ==============================
    public function update(): void
    {
        if (!$this->paymentId) return;

        // Si es PAGO, forzamos hoy para register/payment (igual que tu Index)
        if ($this->type_form === 'PAGO') {
            $today = now(config('app.timezone','America/Lima'))->toDateString();
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

        try {
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

                // Aplicar amortizaciones si ahora es DEUDA
                if ($this->type_form === 'DEUDA') {
                    $this->applyDebtAmortizations($vehicle, $this->normPlate(), (float)$this->amount, (int)$p->id);
                }
            });

            session()->flash('payment_success', 'Pago actualizado correctamente.');
            $this->redirectRoute('payments.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('payment_error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirectRoute('payments.index');
        }
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!$this->isAdmin()) {
            abort(403);
        }
        Payment::findOrFail($id)->delete();
        session()->flash('payment_success', 'Pago eliminado correctamente.');
        $this->redirectRoute('payments.index');
    }

    public function render(): View
    {
        return view('livewire.payments.edit-payment', [
            'headquarters' => $this->headquarters,
        ]);
    }
}
