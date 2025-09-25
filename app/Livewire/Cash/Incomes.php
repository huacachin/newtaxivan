<?php

namespace App\Livewire\Cash;

use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Incomes extends Component
{
    use WithPagination;

    // ===== Filtros/Listado =====
    public $search = '';
    /** 1=A (reason), 2=Motivo (detail), 3=Usuario (user.name) */
    public $filterType = 1;

    public $date_start;
    public $date_end;

    // ===== Formulario =====
    public ?int $incomeId = null;   // null = crear, id = editar
    public string $date = '';
    public string $reason = '';     // Campo "A"
    public string $detail = '';     // Campo "Motivo"
    public string $currency = 'Soles';      // Soles | Dolares (UI)
    public float|string $amount_input = ''; // Monto digitado por el usuario
    public float $exchange = 3.80;          // MVP: fijo
    public ?float $converted_total = null;  // Vista previa en soles

    protected $queryString = [
        'search'     => ['except' => ''],
        'filterType' => ['except' => 1],
        'date_start' => ['except' => null],
        'date_end'   => ['except' => null],
        'page'       => ['except' => 1],
    ];

    protected function rules(): array
    {
        return [
            'date'         => ['required', 'date'],
            'reason'       => ['required', 'string', 'max:100'],
            'detail'       => ['required', 'string', 'max:255'],
            'currency'     => ['required', Rule::in(['Soles','Dolares'])],
            'amount_input' => ['required', 'numeric', 'gt:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'date.required'         => 'La fecha es obligatoria.',
            'reason.required'       => 'El campo “A” es obligatorio.',
            'detail.required'       => 'El motivo es obligatorio.',
            'currency.required'     => 'La moneda es obligatoria.',
            'amount_input.required' => 'El monto es obligatorio.',
            'amount_input.numeric'  => 'El monto debe ser numérico.',
            'amount_input.gt'       => 'El monto debe ser mayor a 0.',
        ];
    }

    public function mount(): void
    {
        $today = Carbon::today()->toDateString();
        $this->date_start = $this->date_start ?: $today;
        $this->date_end   = $this->date_end   ?: $today;
    }

    // ===== Listado =====
    public function render()
    {
        $q = Income::query()
            ->with(['user:id,name'])
            ->orderBy('date')
            ->orderBy('id');

        // Rango de fechas
        if ($this->date_start && $this->date_end) {
            $q->whereBetween('date', [$this->date_start, $this->date_end]);
        } elseif ($this->date_start) {
            $q->where('date', '>=', $this->date_start);
        } elseif ($this->date_end) {
            $q->where('date', '<=', $this->date_end);
        }

        // Búsqueda
        $s = trim((string)$this->search);
        if ($s !== '') {
            if ($this->filterType == 1) {
                $q->where('reason', 'like', "%{$s}%");
            } elseif ($this->filterType == 2) {
                $q->where('detail', 'like', "%{$s}%");
            } elseif ($this->filterType == 3) {
                $q->whereHas('user', fn($qq) => $qq->where('name', 'like', "%{$s}%"));
            } else {
                $q->where('reason', 'like', "%{$s}%");
            }
        }

        $totalGeneral = (clone $q)->sum('total');
        $incomes = $q->paginate(20000);
        $pageSum = $incomes->getCollection()->sum('total');

        return view('livewire.cash.incomes', [
            'incomes'      => $incomes,
            'pageSum'      => $pageSum,
            'totalGeneral' => $totalGeneral,
        ]);
    }

    // ===== Abrir modales =====
    public function openAddModal(): void
    {
        $this->resetForm();
        $this->date = now()->toDateString();
        $this->currency = 'Soles';
        $this->exchange = 3.80; // MVP fijo
        $this->recalcConverted();
        $this->dispatch('open-modal', ['name' => 'modalAddIncome']);
    }

    public function openEditModal(int $id): void
    {
        $this->resetForm();

        $i = Income::with('user:id,name')->findOrFail($id);
        $this->incomeId = $i->id;
        $this->date     = $i->date ? Carbon::parse($i->date)->toDateString() : now()->toDateString();
        $this->reason   = (string)$i->reason;
        $this->detail   = (string)$i->detail;

        // Como almacenamos total en soles, por defecto cargamos en Soles:
        $this->currency      = 'Soles';
        $this->amount_input  = number_format((float)$i->total, 2, '.', '');
        $this->exchange      = 3.80;
        $this->recalcConverted();

        $this->dispatch('open-modal', ['name' => 'modalEditIncome']);
    }

    // ===== Guardar/Actualizar =====
    public function save(): void
    {
        $this->validate();

        $total = $this->currency === 'Dolares'
            ? round(((float)$this->amount_input) * $this->exchange, 2)
            : round((float)$this->amount_input, 2);

        Income::create([
            'date'    => $this->date,
            'reason'  => $this->reason,
            'detail'  => $this->detail,
            'total'   => $total,          // guardamos en S/
            'user_id' => Auth::id(),
        ]);

        $this->resetForm();
        $this->dispatch('modal-close', ['name' => 'modalAddIncome']);
        $this->dispatch('successAlert', ['message' => 'Ingreso registrado correctamente']);
    }

    public function update(): void
    {
        if (!$this->incomeId) return;

        $this->validate();

        $total = $this->currency === 'Dolares'
            ? round(((float)$this->amount_input) * $this->exchange, 2)
            : round((float)$this->amount_input, 2);

        $i = Income::findOrFail($this->incomeId);
        $i->update([
            'date'    => $this->date,
            'reason'  => $this->reason,
            'detail'  => $this->detail,
            'total'   => $total,
            'user_id' => Auth::id(),
        ]);

        $this->resetForm();
        $this->dispatch('modal-close', ['name' => 'modalEditIncome']);
        $this->dispatch('successAlert', ['message' => 'Ingreso actualizado correctamente']);
    }

    // ===== Utilitarios =====
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

    public function closeModal(string $name): void
    {
        // Limpia errores y formulario al cerrar para que no queden pegados
        $this->resetErrorBag();
        $this->resetValidation();

        if ($name === 'modalAddIncome' || $name === 'modalEditIncome') {
            $this->resetForm();
        }
        // el hide real lo hace Bootstrap con data-bs-dismiss
    }

    private function resetForm(): void
    {
        $this->reset([
            'incomeId', 'date', 'reason', 'detail',
            'currency', 'amount_input', 'exchange', 'converted_total',
        ]);
    }

    // Export
    public function export()
    {
        $route = route('exports.incomes', [
            "search"     => $this->search,
            "filterType" => $this->filterType,
            "date_start" => $this->date_start,
            "date_end"   => $this->date_end
        ]);

        $this->dispatch('url-open', ["url" => $route]);
    }
}
