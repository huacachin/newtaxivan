<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateExpense extends Component
{
    use WithFileUploads;

    public string $expenseKind = 'Otros';
    public ?int   $concept_id  = null;
    public array  $concepts    = [];
    public string $reason_text = '';

    public ?string $date          = null;
    public string  $detail        = '';
    public ?float  $total         = null;
    public string  $document_type = '';
    public string  $in_charge     = '';
    public $users;

    public $image_file = null;

    public function mount(): void
    {
        $this->date  = now()->toDateString();
        $this->users = DB::table('users')->pluck('name', 'id');
        $this->refreshConcepts();
    }

    private function refreshConcepts(): void
    {
        if (!Schema::hasTable('concepts')) {
            $this->concepts = [];
            return;
        }

        $candidates = ['default_amount', 'amount', 'price', 'value', 'default_value', 'monto', 'importe'];
        $available  = [];
        foreach ($candidates as $col) {
            if (Schema::hasColumn('concepts', $col)) $available[] = $col;
        }
        $expr = $available ? 'COALESCE(' . implode(',', $available) . ', 0)' : '0';

        $rows = DB::table('concepts')
            ->select('id', 'name')
            ->selectRaw("$expr as def_amount")
            ->orderBy('name')
            ->get();

        $this->concepts = $rows->map(fn($r) => [
            'id'             => (int)$r->id,
            'name'           => (string)$r->name,
            'default_amount' => (float)$r->def_amount,
        ])->all();
    }

    public function rules(): array
    {
        return [
            'date'          => ['required', 'date'],
            'expenseKind'   => ['required', 'in:Fijos,Otros,Planilla'],
            'concept_id'    => [$this->expenseKind === 'Fijos' ? 'required' : 'nullable', 'integer'],
            'reason_text'   => [$this->expenseKind === 'Otros' ? 'required' : 'nullable', 'string', 'max:150'],
            'detail'        => ['required', 'string', 'max:500'],
            'total'         => ['required', 'numeric', 'min:0.01'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'in_charge'     => ['nullable', 'string', 'max:100'],
            'image_file'    => ['nullable', 'image', 'max:3072'],
        ];
    }

    public function updatedExpenseKind($val): void
    {
        if ($val === 'Fijos') {
            $this->refreshConcepts();
            $this->concept_id = $this->concepts[0]['id'] ?? null;
        }
        if (in_array($val, ['Otros', 'Planilla'])) $this->concept_id = null;
    }

    public function clear(): void
    {
        $this->reset(['expenseKind', 'concept_id', 'reason_text', 'detail', 'total', 'document_type', 'in_charge', 'image_file']);
        $this->date        = now()->toDateString();
        $this->expenseKind = 'Otros';
        $this->resetValidation();
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

    protected function conceptNameById(?int $id): string
    {
        if (!$id) return '';
        $row = collect($this->concepts)->firstWhere('id', (int)$id);
        return $row['name'] ?? '';
    }

    public function save(): void
    {
        try {
            $this->validate();

            $reason = $this->expenseKind === 'Fijos'
                ? $this->conceptNameById($this->concept_id)
                : trim($this->reason_text);

            $payload = [
                'date'          => $this->date,
                'reason'        => $reason,
                'detail'        => trim($this->detail),
                'total'         => round((float)$this->total, 2),
                'document_type' => trim((string)$this->document_type),
                'in_charge'     => trim((string)$this->in_charge),
                'user_id'       => Auth::id(),
            ];

            if ($this->image_file) {
                $payload['image_path'] = $this->image_file->storePublicly('expenses', 'public');
            }

            Expense::create($payload);

            session()->flash('expense_success', 'Egreso registrado correctamente.');
            $this->redirectRoute('cash.expenses');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('expense_error', 'Error al registrar: ' . $e->getMessage());
            $this->redirectRoute('cash.expenses');
        }
    }

    public function render()
    {
        return view('livewire.cash.create-expense');
    }
}
