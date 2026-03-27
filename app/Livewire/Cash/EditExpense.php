<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditExpense extends Component
{
    use WithFileUploads;

    public Expense $expense;
    public int $expenseId;

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
    public ?string $image_path = null;

    public function mount(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director','gerente','administrador')) {
            abort(403);
        }

        $this->expense   = Expense::findOrFail($id);
        $this->expenseId = $id;
        $this->users     = DB::table('users')->pluck('name', 'id');
        $this->refreshConcepts();

        $e = $this->expense;

        $this->date          = optional($e->date)->format('Y-m-d');
        $this->detail        = (string)($e->detail ?? '');
        $this->total         = (float)($e->total ?? 0);
        $this->document_type = (string)($e->document_type ?? '');
        $this->in_charge     = (string)($e->in_charge ?? '');
        $this->image_path    = $e->image_path ?: null;

        $match = collect($this->concepts)->firstWhere('name', (string)$e->reason);
        if ($match) {
            $this->expenseKind = 'Fijos';
            $this->concept_id  = (int)$match['id'];
        } else {
            $this->expenseKind = 'Otros';
            $this->reason_text = (string)($e->reason ?? '');
        }
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
        if ($val === 'Fijos') $this->refreshConcepts();
        if (in_array($val, ['Otros', 'Planilla'])) $this->concept_id = null;
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

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director','gerente','administrador')) {
            abort(403);
        }

        $expense = Expense::findOrFail($id);

        if ($expense->image_path && Storage::disk('public')->exists($expense->image_path)) {
            Storage::disk('public')->delete($expense->image_path);
        }

        $expense->delete();
        session()->flash('expense_success', 'Egreso eliminado correctamente.');
        $this->redirectRoute('cash.expenses');
    }

    public function update(): void
    {
        try {
            $this->validate();

            $reason = $this->expenseKind === 'Fijos'
                ? $this->conceptNameById($this->concept_id)
                : trim($this->reason_text);

            $newImagePath = null;
            if ($this->image_file) {
                $newImagePath = $this->image_file->storePublicly('expenses', 'public');
                if ($this->expense->image_path && Storage::disk('public')->exists($this->expense->image_path)) {
                    Storage::disk('public')->delete($this->expense->image_path);
                }
            }

            $payload = [
                'date'          => $this->date,
                'reason'        => $reason,
                'detail'        => trim($this->detail),
                'total'         => round((float)$this->total, 2),
                'document_type' => trim((string)$this->document_type),
                'in_charge'     => trim((string)$this->in_charge),
            ];

            if ($newImagePath) {
                $payload['image_path'] = $newImagePath;
            }

            $this->expense->update($payload);

            session()->flash('expense_success', 'Egreso actualizado correctamente.');
            $this->redirectRoute('cash.expenses');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('expense_error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirectRoute('cash.expenses');
        }
    }

    protected function conceptNameById(?int $id): string
    {
        if (!$id) return '';
        $row = collect($this->concepts)->firstWhere('id', (int)$id);
        return $row['name'] ?? '';
    }

    public function render()
    {
        return view('livewire.cash.edit-expense');
    }
}
