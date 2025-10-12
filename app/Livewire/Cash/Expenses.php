<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Schema;


class Expenses extends Component
{
    use WithPagination;

    /** ====== Filtros de la tabla ====== */
    public string $search = '';
    /** 1=A (reason), 2=Motivo (detail), 3=Usuario (user.name), 4=Respons. (in_charge) */
    public int    $filterType = 1;

    public ?string $date_start = null;
    public ?string $date_end   = null;

    public int $page = 1;

    protected $queryString = [
        'search'     => ['except' => ''],
        'filterType' => ['except' => 1],
        'date_start' => ['except' => null],
        'date_end'   => ['except' => null],
        'page'       => ['except' => 1],
    ];

    /** ====== Estado del modal (crear/editar) ====== */
    public ?int   $editId = null;      // null = crear, int = editar ID
    public string $expenseKind = 'Otros'; // 'Fijos' | 'Otros'

    // Campos “Fijos”
    public ?int   $concept_id = null;  // id en tabla concepts
    public array  $concepts   = [];    // [{id, name, default}]

    // Campos “Otros”
    public string $reason_text = '';

    // Comunes
    public ?string $date          = null;
    public string  $detail        = '';
    public ?float  $total         = null;
    public string  $document_type = '';
    public string  $in_charge     = '';
    public $users;

    /** ====== Ciclo de vida ====== */
    public function mount(): void
    {
        $today             = Carbon::today()->toDateString();
        $this->date_start  = $this->date_start ?: $today;
        $this->date_end    = $this->date_end   ?: $today;
        $this->users       = DB::table('users')->pluck('name', 'id');
        $this->refreshConcepts();
    }

    private function refreshConcepts(): void
    {
        // Si no existe la tabla, salimos limpios
        if (!Schema::hasTable('concepts')) {
            $this->concepts = [];
            return;
        }

        // Candidatos posibles según legacy
        $candidates = [
            'default_amount', 'amount', 'price', 'value',
            'default_value', 'monto', 'importe'
        ];

        $available = [];
        foreach ($candidates as $col) {
            if (Schema::hasColumn('concepts', $col)) {
                $available[] = $col;
            }
        }

        // Si no hay ninguna de monto, usamos 0
        $expr = $available
            ? 'COALESCE(' . implode(',', $available) . ', 0)'
            : '0';

        // Traer id, name y el monto por defecto calculado
        $rows = DB::table('concepts')
            ->select('id', 'name')
            ->selectRaw("$expr as def_amount")
            ->orderBy('name', 'asc')
            ->get();

        // Formatear para el select del modal
        $this->concepts = $rows->map(fn ($r) => [
            'id'             => (int) $r->id,
            'name'           => (string) $r->name,
            'default_amount' => (float) $r->def_amount,
        ])->all();
    }

    public function applyDate(): void{
        $this->render();
    }


    public function render()
    {
        $q = Expense::query()
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
            switch ((int)$this->filterType) {
                case 1: $q->where('reason', 'like', "%{$s}%"); break;           // A
                case 2: $q->where('detail', 'like', "%{$s}%"); break;           // Motivo
                case 3: // Usuario
                    $q->whereHas('user', fn($qq) => $qq->where('name', 'like', "%{$s}%"));
                    break;
                case 4: $q->where('in_charge', 'like', "%{$s}%"); break;        // Respons.
                default: $q->where('reason', 'like', "%{$s}%");
            }
        }

        // Totales
        $totalGeneral = (clone $q)->sum('total');

        // Paginado
        $expenses = $q->paginate(200000);

        return view('livewire.cash.expenses', [
            'expenses'      => $expenses,
            'totalGeneral'  => $totalGeneral,
        ]);
    }

    /** ====== Reglas de validación (modal) ====== */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'expenseKind' => ['required', 'in:Fijos,Otros'],
            // Fijos
            'concept_id' => [$this->expenseKind === 'Fijos' ? 'required' : 'nullable', 'integer'],
            // Otros
            'reason_text' => [$this->expenseKind === 'Otros' ? 'required' : 'nullable', 'string', 'max:150'],
            // Comunes
            'detail' => ['required', 'string', 'max:500'],
            'total'  => ['required', 'numeric', 'min:0.01'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'in_charge'     => ['nullable', 'string', 'max:100'],
        ];
    }

    /** ====== Abrir/Reset modal ====== */
    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->date = Carbon::today()->toDateString();
        $this->dispatch('open-modal', ['name' => 'modalExpense', 'focus' => 'date']);
    }

    public function openEditModal(int $id): void
    {
        $e = Expense::findOrFail($id);

        $this->editId        = $e->id;
        $this->date          = (string)$e->date;
        $this->detail        = (string)($e->detail ?? '');
        $this->total         = (float)($e->total ?? 0);
        $this->document_type = (string)($e->document_type ?? '');
        $this->in_charge     = (string)($e->in_charge ?? '');

        // Detectar si reason coincide con un concepto (Fijos) o es libre (Otros)
        $match = collect($this->concepts)->firstWhere('name', (string)$e->reason);
        if ($match) {
            $this->expenseKind = 'Fijos';
            $this->concept_id  = (int)$match['id'];
            $this->reason_text = '';
        } else {
            $this->expenseKind = 'Otros';
            $this->concept_id  = null;
            $this->reason_text = (string)($e->reason ?? '');
        }

        $this->dispatch('open-modal', ['name' => 'modalExpense', 'focus' => 'date']);
    }

    public function resetForm(): void
    {
        $this->reset([
            'editId',
            'expenseKind',
            'concept_id',
            'reason_text',
            'date',
            'detail',
            'total',
            'document_type',
            'in_charge',
        ]);
        $this->expenseKind = 'Otros';
        $this->reason_text = '';
        $this->concept_id  = null;
        $this->clearValidation();
    }

    /** ====== Guardar (crear/editar) ====== */
    public function save(): void
    {
        $this->validate();

        $userId = optional(auth()->user())->id ?? 1;

        $reason = $this->expenseKind === 'Fijos'
            ? $this->conceptNameById($this->concept_id)
            : trim($this->reason_text);

        $payload = [
            'date'          => $this->date,
            'user_id'       => $userId,
            'reason'        => $reason,
            'detail'        => trim($this->detail),
            'total'         => round((float)$this->total, 2),
            'document_type' => trim((string)$this->document_type),
            'in_charge'     => trim((string)$this->in_charge),
        ];

        if ($this->editId) {
            Expense::where('id', $this->editId)->update($payload);
            $okMsg = 'Egreso actualizado correctamente';
        } else {
            Expense::create($payload);
            $okMsg = 'Egreso creado correctamente';
        }

        // Cerrar modal + alerta de éxito (TU dispatcher)
        $this->dispatch('modal-close',["name" => "modalExpense"]);
        $this->dispatch('successAlert', ["message" => $okMsg]);

        // Limpia el formulario (no resetea filtros ni paginación)
        $this->resetForm();
        // NOTA: dejamos que el componente rerenderice para que la tabla se actualice.
    }

    /** ====== Export ====== */
    public function export()
    {
        $route = route('exports.expenses', [
            "search"     => $this->search,
            "filterType" => $this->filterType,
            "date_start" => $this->date_start,
            "date_end"   => $this->date_end
        ]);

        $this->dispatch('url-open', ["url" => $route]);
    }

    /** ====== Handlers UI del modal ====== */
    public function updatedExpenseKind($val): void
    {
        if ($val === 'Fijos') {
            $this->refreshConcepts();
        }
        // Si pasa a "Otros", puedes limpiar concept_id
        if ($val === 'Otros') {
            $this->concept_id = null;
        }
    }

    public function updatedConceptId($value): void
    {
        if ($this->expenseKind !== 'Fijos' || empty($value)) return;

        $row = collect($this->concepts)->firstWhere('id', (int)$value);
        if ($row && ($this->total === null || $this->total == 0)) {
            // $def = $row['default'] ?? null;        // ❌
            $def = $row['default_amount'] ?? null;    // ✅
            if ($def !== null) $this->total = (float)$def;
        }
    }

    /** ====== Helpers ====== */

    protected function conceptNameById(?int $id): string
    {
        if (!$id) return '';
        $row = collect($this->concepts)->firstWhere('id', (int)$id);
        return $row['name'] ?? '';
    }
}
