<?php

namespace App\Livewire\Cash;

use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

class Incomes extends Component
{
    use WithPagination;
    use WithFileUploads;

    // ===== Filtros/Listado =====
    public $search = '';
    /** 1=A (reason), 2=Motivo (detail), 3=Usuario (user.name) */
    public $filterType = 1;

    public $date_start;
    public $date_end;

    public ?string $ui_date_start = null;
    public ?string $ui_date_end   = null;

    // ===== Formulario =====
    public ?int $incomeId = null;   // null = crear, id = editar
    public string $date = '';
    public string $reason = '';     // Campo "A"
    public string $detail = '';     // Campo "Motivo"
    public string $currency = 'Soles';      // Soles | Dolares (UI)
    public float|string $amount_input = ''; // Monto digitado por el usuario
    public float $exchange = 3.80;          // MVP: fijo
    public ?float $converted_total = null;  // Vista previa en soles

    // ===== Imagen =====
    /** Archivo subido (Livewire) */
    public $image_file = null;
    /** Ruta actual (editar) */
    public ?string $image_path = null;

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

            // Imagen (opcional): máx ~2MB, cualquier formato de imagen
            'image_file'   => ['nullable', 'image', 'max:2048'],
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
            'image_file.image'      => 'El archivo debe ser una imagen válida.',
            'image_file.max'        => 'La imagen no debe superar 2MB.',
        ];
    }

    public function mount(): void
    {
        $today = Carbon::today()->toDateString();
        $this->date_start = $this->date_start ?: $today;
        $this->date_end   = $this->date_end   ?: $today;

        $this->ui_date_start = $this->date_start;
        $this->ui_date_end   = $this->date_end;
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
    }

    // ===== Listado =====
    public function render()
    {
        $user = Auth::user();

        $q = Income::query()
            ->with(['user:id,name,username'])
            ->orderBy('date')
            ->orderBy('id');

        // --- Filtro por rol: controller ve solo lo suyo; admin ve todo ---
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controlador')) {
            $q->where('user_id', $user->id);
        }
        // Nota: si no es controller (p.ej. admin), no se aplica restricción adicional.

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

        // Refuerzo: si es controller, solo puede abrir sus registros
        $query = Income::with('user:id,name,username');
        $user  = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controlador')) {
            $query->where('user_id', $user->id);
        }

        $i = $query->findOrFail($id);

        $this->incomeId = $i->id;
        $this->date     = $i->date ? Carbon::parse($i->date)->toDateString() : now()->toDateString();
        $this->reason   = (string)$i->reason;
        $this->detail   = (string)$i->detail;

        // Imagen actual (si existe)
        $this->image_path = $i->image_path;
        $this->image_file = null;

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

        // Guardar imagen si se subió
        $storedPath = null;
        if ($this->image_file) {
            $storedPath = $this->image_file->store('incomes', 'public'); // storage/app/public/incomes/...
        }

        Income::create([
            'date'       => $this->date,
            'reason'     => $this->reason,
            'detail'     => $this->detail,
            'image_path' => $storedPath,
            'total'      => $total,           // guardamos en S/
            'user_id'    => Auth::id(),
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

        // Refuerzo: limitar edición si es controller
        $query = Income::query();
        $user  = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controlador')) {
            $query->where('user_id', $user->id);
        }

        $i = $query->findOrFail($this->incomeId);

        $payload = [
            'date'    => $this->date,
            'reason'  => $this->reason,
            'detail'  => $this->detail,
            'total'   => $total,
            'user_id' => Auth::id(),
        ];

        // Si sube nueva imagen, reemplaza
        if ($this->image_file) {
            if ($i->image_path && Storage::disk('public')->exists($i->image_path)) {
                Storage::disk('public')->delete($i->image_path);
            }
            $payload['image_path'] = $this->image_file->store('incomes', 'public');
            $this->image_path = $payload['image_path'];
        }

        $i->update($payload);

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
            'image_file', 'image_path',
        ]);
    }

    public function openEditWindow(int $id): void
    {
        $this->dispatch('url-open', ['url' => route('cash.incomes.edit', $id)]);
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
