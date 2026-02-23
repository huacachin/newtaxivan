<?php

namespace App\Livewire\Cash;

use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditIncome extends Component
{
    use WithFileUploads;

    public Income $income;
    public int $incomeId;

    public string $date          = '';
    public string $reason        = '';
    public string $detail        = '';
    public string $currency      = 'Soles';
    public float|string $amount_input  = '';
    public float  $exchange      = 3.80;
    public ?float $converted_total = null;

    public $image_file  = null;
    public ?string $image_path = null;

    public function mount(int $id): void
    {
        if (!auth()->user()?->hasRole('admin')) {
            abort(403);
        }

        $this->income   = Income::findOrFail($id);
        $this->incomeId = $id;

        $i = $this->income;

        $this->date         = $i->date ? Carbon::parse($i->date)->toDateString() : now()->toDateString();
        $this->reason       = (string)$i->reason;
        $this->detail       = (string)$i->detail;
        $this->currency     = 'Soles';
        $this->amount_input = number_format((float)$i->total, 2, '.', '');
        $this->exchange     = 3.80;
        $this->image_path   = $i->image_path ?: null;

        $this->recalcConverted();
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

    public function updatedCurrency(): void
    {
        $this->recalcConverted();
    }

    public function updatedAmountInput(): void
    {
        $this->recalcConverted();
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->hasRole('admin')) {
            abort(403);
        }

        $income = Income::findOrFail($id);

        if ($income->image_path && Storage::disk('public')->exists($income->image_path)) {
            Storage::disk('public')->delete($income->image_path);
        }

        $income->delete();
        session()->flash('income_success', 'Ingreso eliminado correctamente.');
        $this->redirectRoute('cash.incomes');
    }

    public function update(): void
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
                if ($this->income->image_path && Storage::disk('public')->exists($this->income->image_path)) {
                    Storage::disk('public')->delete($this->income->image_path);
                }
                $payload['image_path'] = $this->image_file->store('incomes', 'public');
            }

            $this->income->update($payload);

            session()->flash('income_success', 'Ingreso actualizado correctamente.');
            $this->redirectRoute('cash.incomes');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('income_error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirectRoute('cash.incomes');
        }
    }

    public function render()
    {
        return view('livewire.cash.edit-income');
    }
}
