<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use App\Traits\NormalizesDecimals;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Schema;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class Expenses extends Component
{
    use WithPagination;
    use WithFileUploads;
    use NormalizesDecimals;

    /** ====== Filtros de la tabla ====== */
    public string $search = '';
    /** 1=A (reason), 2=Motivo (detail), 3=Usuario (user.name), 4=Respons. (in_charge) */
    public int    $filterType = 1;

    public ?string $date_start = null;
    public ?string $date_end   = null;

    public ?string $ui_date_start = null;
    public ?string $ui_date_end   = null;

    public int $page = 1;

    protected $queryString = [
        'search'     => ['except' => ''],
        'filterType' => ['except' => 1],
        'date_start' => ['except' => null],
        'date_end'   => ['except' => null],
        'page'       => ['except' => 1],
    ];

    /** ====== Estado del modal (crear/editar) ====== */
    public ?int   $editId = null;        // null = crear, int = editar ID
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

    // Imagen
    public $image_file = null;         // archivo subido
    public ?string $image_path = null; // ruta actual en edición

    /** ====== Ciclo de vida ====== */
    public function mount(): void
    {
        $today             = Carbon::today()->toDateString();
        $this->date_start  = $this->date_start ?: $today;
        $this->date_end    = $this->date_end   ?: $today;

        $this->ui_date_start = $this->date_start;
        $this->ui_date_end   = $this->date_end;

        $this->users       = DB::table('users')->pluck('name', 'id');
        $this->refreshConcepts();
    }

    private function refreshConcepts(): void
    {
        if (!Schema::hasTable('concepts')) {
            $this->concepts = [];
            return;
        }

        $candidates = ['default_amount','amount','price','value','default_value','monto','importe'];
        $available = [];
        foreach ($candidates as $col) if (Schema::hasColumn('concepts', $col)) $available[] = $col;

        $expr = $available ? 'COALESCE(' . implode(',', $available) . ', 0)' : '0';

        $rows = DB::table('concepts')
            ->select('id', 'name')
            ->selectRaw("$expr as def_amount")
            ->orderBy('name', 'asc')
            ->get();

        $this->concepts = $rows->map(fn ($r) => [
            'id'             => (int) $r->id,
            'name'           => (string) $r->name,
            'default_amount' => (float) $r->def_amount,
        ])->all();
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

    public function render()
    {
        $user = Auth::user();

        $q = Expense::query()
            ->with(['user:id,name,username', 'images'])
            ->orderBy('date')
            ->orderBy('id');

        // ---- Restricción por rol ----
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controlador')) {
            $q->where('user_id', $user->id);
        }
        // admin (u otros roles) ven sin restricción extra.

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
                case 1: $q->where('reason', 'like', "%{$s}%"); break;
                case 2: $q->where('detail', 'like', "%{$s}%"); break;
                case 3: $q->whereHas('user', fn($qq) => $qq->where('name', 'like', "%{$s}%")); break;
                case 4: $q->where('in_charge', 'like', "%{$s}%"); break;
                default: $q->where('reason', 'like', "%{$s}%");
            }
        }

        $totalGeneral = (clone $q)->sum('total');
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
            'concept_id' => [$this->expenseKind === 'Fijos' ? 'required' : 'nullable', 'integer'],
            'reason_text' => [$this->expenseKind === 'Otros' ? 'required' : 'nullable', 'string', 'max:150'],
            'detail' => ['required', 'string', 'max:500'],
            'total'  => ['required', 'numeric', 'min:0.01'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'in_charge'     => ['nullable', 'string', 'max:100'],
            // Imagen opcional
            'image_file'    => ['nullable', 'image', 'max:3072'], // ~3MB
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
        // Restringir qué registro se puede abrir si es controller
        $query = Expense::query();
        $user  = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controlador')) {
            $query->where('user_id', $user->id);
        }
        $e = $query->findOrFail($id);

        $this->editId        = $e->id;
        $this->date          = (string)$e->date;
        $this->detail        = (string)($e->detail ?? '');
        $this->total         = (float)($e->total ?? 0);
        $this->document_type = (string)($e->document_type ?? '');
        $this->in_charge     = (string)($e->in_charge ?? '');

        // Imagen actual
        $this->image_path    = $e->image_path ?: null;
        $this->image_file    = null;

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
            'editId','expenseKind','concept_id','reason_text','date','detail','total','document_type','in_charge',
            'image_file','image_path'
        ]);
        $this->expenseKind = 'Otros';
        $this->reason_text = '';
        $this->concept_id  = null;
        $this->clearValidation();
    }

    /** ====== Guardar (crear/editar) ====== */
    public function save(): void
    {
        $this->normalizeDecimalProps(['total']);
        $this->validate();

        $userId = Auth::id();

        // === Resolver HQ primario del usuario que se está guardando en user_id
        $hqId = $this->resolveUserPrimaryHeadquarterId((int)$userId);

        // Si la tabla expenses tiene la columna headquarter_id, debe existir valor resuelto
        if (Schema::hasColumn('expenses','headquarter_id') && empty($hqId)) {
            $this->addError('headquarter_id', 'No se pudo determinar el Headquarter primario del usuario.');
            return;
        }

        $reason = $this->expenseKind === 'Fijos'
            ? $this->conceptNameById($this->concept_id)
            : trim($this->reason_text);

        // Manejo de imagen (si se sube)
        $newImagePath = null;
        if ($this->image_file) {
            $newImagePath = $this->image_file->storePublicly('expenses', 'public');
        }

        $payload = [
            'date'          => $this->date,
            'user_id'       => $userId,
            'reason'        => $reason,
            'detail'        => trim($this->detail),
            'total'         => round((float)$this->total, 2),
            'document_type' => trim((string)$this->document_type),
            'in_charge'     => trim((string)$this->in_charge),
        ];

        // Adjuntar HQ si la columna existe
        if (Schema::hasColumn('expenses','headquarter_id')) {
            $payload['headquarter_id'] = (int)$hqId;
        }

        if ($newImagePath) {
            $payload['image_path'] = $newImagePath;
        }

        if ($this->editId) {
            // Limitar edición si es controller
            $query = Expense::query();
            $user  = Auth::user();
            if ($user && method_exists($user, 'hasRole') && $user->hasRole('controlador')) {
                $query->where('user_id', $user->id);
            }

            $e = $query->findOrFail($this->editId);

            // si hay nueva imagen, elimina la anterior
            if ($newImagePath && $e->image_path && Storage::disk('public')->exists($e->image_path)) {
                Storage::disk('public')->delete($e->image_path);
            }

            $e->update($payload);
            $okMsg = 'Egreso actualizado correctamente';
        } else {
            Expense::create($payload);
            $okMsg = 'Egreso creado correctamente';
        }

        $this->dispatch('modal-close',["name" => "modalExpense"]);
        $this->dispatch('successAlert', ["message" => $okMsg]);

        $this->resetForm();
    }

    public function openEditWindow(int $id): void
    {
        $this->dispatch('url-open', ['url' => route('cash.expenses.edit', $id)]);
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
        if ($val === 'Fijos') $this->refreshConcepts();
        if ($val === 'Otros') $this->concept_id = null;
    }

    public function updatedConceptId($value): void
    {
        if ($this->expenseKind !== 'Fijos' || empty($value)) return;

        $row = collect($this->concepts)->firstWhere('id', (int)$value);
        if ($row && ($this->total === null || $this->total == 0)) {
            $def = $row['default_amount'] ?? null;
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

    /**
     * Resuelve el headquarter primario del usuario dado.
     * Estrategia:
     *  1) users.headquarter_id
     *  2) users.primary_headquarter_id
     *  3) pivot headquarter_user (is_primary=1) o el primero
     */
    private function resolveUserPrimaryHeadquarterId(int $userId): ?int
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        // 1) users.headquarter_id
        if (Schema::hasColumn('users', 'headquarter_id')) {
            $val = DB::table('users')->where('id', $userId)->value('headquarter_id');
            if (!empty($val)) return (int)$val;
        }

        // 2) users.primary_headquarter_id
        if (Schema::hasColumn('users', 'primary_headquarter_id')) {
            $val = DB::table('users')->where('id', $userId)->value('primary_headquarter_id');
            if (!empty($val)) return (int)$val;
        }

        // 3) Pivot: headquarter_user
        if (Schema::hasTable('headquarter_user')) {
            // Preferir is_primary = 1 si la columna existe
            $query = DB::table('headquarter_user')->where('user_id', $userId);

            if (Schema::hasColumn('headquarter_user', 'is_primary')) {
                $val = (clone $query)->where('is_primary', 1)->value('headquarter_id');
                if (!empty($val)) return (int)$val;
            }

            // Si no hay is_primary o no hay marcado, tomar el primero
            $val = $query->orderBy('headquarter_id')->value('headquarter_id');
            if (!empty($val)) return (int)$val;
        }

        return null;
    }
}
