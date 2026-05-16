<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use App\Models\ExpenseImage;
use App\Traits\NormalizesDecimals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditExpense extends Component
{
    use WithFileUploads, NormalizesDecimals;

    public Expense $expense;
    public int $expenseId;

    public string $expenseKind = 'Otros';
    public ?int   $concept_id  = null;
    public array  $concepts    = [];
    public array  $controladores = [];
    public string $reason_text = '';
    public array  $amountSuggestions = [];
    public array  $reasonTemplates = [];
    public ?string $reasonModalText = null;

    public ?string $date          = null;
    public string  $detail        = '';
    public ?float  $total         = null;
    public string  $document_type = '';
    public string  $in_charge     = '';
    public $users;

    public $new_images = [];
    public $image_files = [];
    public array $existing_images = [];
    public array $deleted_image_ids = [];

    public function updatedNewImages(): void
    {
        foreach ($this->new_images as $file) {
            $this->image_files[] = $file;
        }
        $this->new_images = [];
    }

    public function removeNewImage(int $index): void
    {
        array_splice($this->image_files, $index, 1);
    }

    public function removeExistingImage(int $imageId): void
    {
        if (!in_array($imageId, $this->deleted_image_ids, true)) {
            $this->deleted_image_ids[] = $imageId;
        }
    }

    public function restoreExistingImage(int $imageId): void
    {
        $this->deleted_image_ids = array_values(array_filter(
            $this->deleted_image_ids,
            fn($id) => $id !== $imageId
        ));
    }

    public function mount(int $id): void
    {
        $this->expense   = Expense::with('images')->findOrFail($id);

        if (auth()->user()->cannot('update', $this->expense)) {
            abort(403);
        }
        $this->expenseId = $id;
        $this->users     = DB::table('users')->where('status', 'active')->pluck('username', 'id');
        $this->refreshConcepts();
        $this->loadControladores();

        $e = $this->expense;

        $this->date          = optional($e->date)->format('Y-m-d');
        $this->detail        = (string)($e->detail ?? '');
        $this->total         = (float)($e->total ?? 0);
        $this->document_type = (string)($e->document_type ?? '');
        $this->in_charge     = (string)($e->in_charge ?? '');

        $this->existing_images = $e->images->map(fn($img) => [
            'id'  => (int)$img->id,
            'url' => asset('storage/' . $img->image_path),
        ])->all();

        $this->expenseKind = in_array($e->kind, ['Fijos','Otros','Planilla'], true) ? $e->kind : 'Otros';

        if ($this->expenseKind === 'Fijos') {
            $match = collect($this->concepts)->firstWhere('name', (string)$e->reason);
            $this->concept_id = $match ? (int)$match['id'] : null;
        } else {
            $this->reason_text = (string)($e->reason ?? '');
        }

        $this->recomputeAmountSuggestions();
        $this->loadReasonTemplates();
    }

    protected function loadReasonTemplates(): void
    {
        $this->reasonTemplates = DB::table('expenses')
            ->whereNotNull('detail')
            ->whereRaw('CHAR_LENGTH(detail) >= ?', [30])
            ->where('created_at', '>=', now()->subYear())
            ->select('detail', DB::raw('COUNT(*) as freq'))
            ->groupBy('detail')
            ->orderByDesc('freq')
            ->limit(20)
            ->pluck('detail')
            ->all();
    }

    public function openReasonModal(string $text): void
    {
        if (trim($text) === '') return;
        $this->reasonModalText = $text;
        $this->dispatch('open-modal', ['name' => 'reasonTemplateModal']);
    }

    public function acceptReasonModal(): void
    {
        $this->detail = (string) $this->reasonModalText;
        $this->reasonModalText = null;
        $this->dispatch('modal-close', ['name' => 'reasonTemplateModal']);
        $this->dispatch('reason-detail-updated', detail: $this->detail);
    }

    private function loadControladores(): void
    {
        $this->controladores = \App\Models\User::role('controlador')
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('username')
            ->all();
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
            'new_images'    => ['nullable', 'array', 'max:10'],
            'new_images.*'  => ['image', 'max:3072'],
        ];
    }

    public function updatedExpenseKind($val): void
    {
        if ($val === 'Fijos') $this->refreshConcepts();
        if (in_array($val, ['Otros', 'Planilla'])) $this->concept_id = null;
        if ($val === 'Planilla' && !in_array($this->reason_text, $this->controladores, true)) {
            $this->reason_text = $this->controladores[0] ?? '';
        }
        $this->recomputeAmountSuggestions();
    }

    public function updatedConceptId($value): void
    {
        if ($this->expenseKind === 'Fijos' && !empty($value)) {
            $row = collect($this->concepts)->firstWhere('id', (int)$value);
            if ($row && ($this->total === null || $this->total == 0)) {
                $def = $row['default_amount'] ?? null;
                if ($def !== null) $this->total = (float)$def;
            }
        }
        $this->recomputeAmountSuggestions();
    }

    public function updatedReasonText(): void
    {
        $this->recomputeAmountSuggestions();
    }

    protected function recomputeAmountSuggestions(): void
    {
        $since = now()->subDays(180)->toDateString();
        $q = \DB::table('expenses')
            ->where('total', '>', 0)
            ->where('date', '>=', $since);

        $reasonLabel = $this->resolveCurrentReasonLabel();
        if ($reasonLabel !== '') {
            $q->where('reason', $reasonLabel);
        }

        $this->amountSuggestions = $q
            ->select('total', \DB::raw('COUNT(*) as c'))
            ->groupBy('total')->orderByDesc('c')->limit(3)
            ->pluck('total')
            ->map(fn ($v) => number_format((float) $v, 2, '.', ''))
            ->values()->all();
    }

    protected function resolveCurrentReasonLabel(): string
    {
        if ($this->expenseKind === 'Fijos' && $this->concept_id) {
            $row = collect($this->concepts)->firstWhere('id', (int) $this->concept_id);
            return $row ? (string) ($row['name'] ?? '') : '';
        }
        return trim((string) $this->reason_text);
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        $expense = Expense::findOrFail($id);

        if (auth()->user()->cannot('delete', $expense)) {
            abort(403);
        }

        foreach ($expense->images as $img) {
            if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
        }

        $expense->delete();
        session()->flash('expense_success', 'Egreso eliminado correctamente.');
        $this->redirectRoute('cash.expenses');
    }

    public function update(): void
    {
        if (auth()->user()->cannot('update', $this->expense)) {
            abort(403);
        }

        try {
            $this->normalizeDecimalProps(['total']);
            $this->validate();

            $reason = $this->expenseKind === 'Fijos'
                ? $this->conceptNameById($this->concept_id)
                : trim($this->reason_text);

            $payload = [
                'date'          => $this->date,
                'reason'        => $reason,
                'kind'          => $this->expenseKind,
                'detail'        => trim($this->detail),
                'total'         => round((float)$this->total, 2),
                'document_type' => trim((string)$this->document_type),
                'in_charge'     => trim((string)$this->in_charge),
            ];

            DB::transaction(function () use ($payload) {
                $this->expense->update($payload);

                if (!empty($this->deleted_image_ids)) {
                    $imagesToDelete = ExpenseImage::where('expense_id', $this->expense->id)
                        ->whereIn('id', $this->deleted_image_ids)
                        ->get();
                    foreach ($imagesToDelete as $img) {
                        if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                            Storage::disk('public')->delete($img->image_path);
                        }
                        $img->delete();
                    }
                }

                foreach ($this->image_files as $file) {
                    $path = $file->storePublicly('expenses', 'public');
                    ExpenseImage::create([
                        'expense_id' => $this->expense->id,
                        'image_path' => $path,
                    ]);
                }
            });

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
