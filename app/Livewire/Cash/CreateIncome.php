<?php

namespace App\Livewire\Cash;

use App\Models\Income;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateIncome extends Component
{
    use WithFileUploads;

    public string $date          = '';
    public string $reason        = '';
    public string $detail        = '';
    public string $currency      = 'Soles';
    public float|string $amount_input  = '';
    public float  $exchange      = 3.80;
    public ?float $converted_total = null;

    public $image_file = null;

    public function mount(): void
    {
        $this->date     = now()->toDateString();
        $this->currency = 'Soles';
    }

    protected function rules(): array
    {
        return [
            'date'         => ['required', 'date'],
            'reason'       => ['required', 'string', 'max:100'],
            'detail'       => ['required', 'string', 'max:255'],
            'currency'     => ['required', Rule::in(['Soles', 'Dolares'])],
            'amount_input' => ['required', 'numeric', 'gt:0'],
            'image_file'   => ['nullable', 'image', 'max:2048'],
        ];
    }

    protected function messages(): array
    {
        return [
            'date.required'         => 'La fecha es obligatoria.',
            'reason.required'       => 'El campo "A" es obligatorio.',
            'detail.required'       => 'El motivo es obligatorio.',
            'currency.required'     => 'La moneda es obligatoria.',
            'amount_input.required' => 'El monto es obligatorio.',
            'amount_input.numeric'  => 'El monto debe ser numérico.',
            'amount_input.gt'       => 'El monto debe ser mayor a 0.',
            'image_file.image'      => 'El archivo debe ser una imagen válida.',
            'image_file.max'        => 'La imagen no debe superar 2MB.',
        ];
    }

    public function recalcConverted(): void
    {
        if ($this->amount_input === '' || !is_numeric($this->amount_input)) {
            $this->converted_total = null;
            return;
        }
        $val = (float)$this->amount_input;
        $this->converted_total = $this->currency === 'Dolares'
            ? round($val * $this->exchange, 2)
            : round($val, 2);
    }

    public function clear(): void
    {
        $this->reset(['reason', 'detail', 'currency', 'amount_input', 'converted_total', 'image_file']);
        $this->date     = now()->toDateString();
        $this->currency = 'Soles';
        $this->resetValidation();
    }

    public function updatedCurrency(): void
    {
        $this->recalcConverted();
    }

    public function updatedAmountInput(): void
    {
        $this->recalcConverted();
    }

    public function save(): void
    {
        try {
            $this->validate();

            $total = $this->currency === 'Dolares'
                ? round(((float)$this->amount_input) * $this->exchange, 2)
                : round((float)$this->amount_input, 2);

            $payload = [
                'date'    => $this->date,
                'reason'  => $this->reason,
                'detail'  => $this->detail,
                'total'   => $total,
                'user_id' => Auth::id(),
            ];

            if ($this->image_file) {
                $payload['image_path'] = $this->image_file->store('incomes', 'public');
            }

            Income::create($payload);

            session()->flash('income_success', 'Ingreso registrado correctamente.');
            $this->redirectRoute('cash.incomes');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('income_error', 'Error al registrar: ' . $e->getMessage());
            $this->redirectRoute('cash.incomes');
        }
    }

    public function render()
    {
        return view('livewire.cash.create-income');
    }
}
