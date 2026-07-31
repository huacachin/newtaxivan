<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use App\Models\ExpenseImage;
use App\Traits\LoadsExpenseSuggestions;
use App\Traits\NormalizesDecimals;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateExpense extends Component
{
    use WithFileUploads, NormalizesDecimals, LoadsExpenseSuggestions;

    public string $expenseKind = 'Otros';
    public ?int   $concept_id  = null;
    public array  $concepts    = [];
    public string $reason_text = '';
    public array  $amountSuggestions = [];
    public array  $controladores = [];
    public ?int   $headquarter_id = null;
    public array  $headquarters   = [];
    public bool   $isDraco        = false;

    public ?string $date          = null;
    public string  $detail        = '';
    public ?float  $total         = null;
    public string  $document_type = '';
    public string  $in_charge     = '';

    public $new_images = [];
    public $image_files = [];

    public function updatedNewImages(): void
    {
        foreach ($this->new_images as $file) {
            $this->image_files[] = $file;
        }
        $this->new_images = [];
    }

    public function removeImage(int $index): void
    {
        array_splice($this->image_files, $index, 1);
    }

    public function mount(): void
    {
        $this->date  = now()->toDateString();
        $this->in_charge = auth()->user()?->username ?? '';
        $this->loadExpenseSuggestions();
        $this->refreshConcepts();
        $this->loadControladores();
        $this->loadHeadquarters();
        $this->recomputeAmountSuggestions();
    }

    private function loadControladores(): void
    {
        $this->controladores = \App\Models\User::role('controlador')
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('username')
            ->all();
    }

    private function loadHeadquarters(): void
    {
        $this->headquarters = DB::table('headquarters')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn($r) => ['id' => (int)$r->id, 'name' => (string)$r->name])
            ->all();
        $this->headquarter_id = 3; // Sta. Anita por defecto
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
            ->orderBy('id')
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
            'reason_text'   => [in_array($this->expenseKind, ['Otros', 'Planilla']) ? 'required' : 'nullable', 'string', 'max:150'],
            'detail'        => ['required', 'string', 'max:500'],
            'total'         => ['required', 'numeric', 'min:0.01'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'in_charge'     => ['nullable', 'string', 'max:100'],
            'new_images'      => ['nullable', 'array', 'max:10'],
            'new_images.*'    => ['image', 'max:3072'],
            'headquarter_id'  => [$this->isDraco ? 'required' : 'nullable', 'integer'],
        ];
    }

    public function updatedExpenseKind($val): void
    {
        if ($val === 'Fijos') {
            $this->refreshConcepts();
            $this->concept_id = $this->concepts[0]['id'] ?? null;
            $this->checkDraco();
        }
        if ($val === 'Planilla') {
            $this->concept_id = null;
            $this->reason_text = $this->controladores[0] ?? '';
            $this->isDraco = false;
            $this->headquarter_id = null;
        }
        if ($val === 'Otros') {
            $this->concept_id = null;
            $this->reason_text = '';
            $this->isDraco = false;
            $this->headquarter_id = null;
        }
        $this->recomputeAmountSuggestions();
    }

    public function clear(): void
    {
        $this->reset(['expenseKind', 'concept_id', 'reason_text', 'detail', 'total', 'document_type', 'in_charge', 'image_files', 'new_images', 'isDraco', 'headquarter_id']);
        $this->date        = now()->toDateString();
        $this->expenseKind = 'Otros';
        $this->resetValidation();
    }

    public function updatedConceptId($value): void
    {
        if ($this->expenseKind === 'Fijos' && !empty($value)) {
            $row = collect($this->concepts)->firstWhere('id', (int)$value);
            if ($row && ($this->total === null || $this->total == 0)) {
                $def = $row['default_amount'] ?? null;
                if ($def !== null) $this->total = (float)$def;
            }
            $this->checkDraco();
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
            return (string) $this->conceptNameById($this->concept_id);
        }
        return trim((string) $this->reason_text);
    }

    private function checkDraco(): void
    {
        $wasDraco = $this->isDraco;
        $this->isDraco = false;

        if ($this->expenseKind === 'Fijos' && $this->concept_id) {
            $row = collect($this->concepts)->firstWhere('id', (int)$this->concept_id);
            if ($row && str_contains(strtoupper($row['name']), 'DRACO')) {
                $this->isDraco = true;
            }
        }

        if ($this->isDraco && !$wasDraco) {
            $this->headquarter_id = 3;
            $hq = collect($this->headquarters)->firstWhere('id', 3);
            $this->detail = $hq['name'] ?? '';
        } elseif (!$this->isDraco && $wasDraco) {
            $this->detail = '';
            $this->headquarter_id = null;
        }
    }

    public function updatedHeadquarterId($value): void
    {
        if ($this->isDraco && $value) {
            $hq = collect($this->headquarters)->firstWhere('id', (int)$value);
            $this->detail = $hq['name'] ?? '';
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
            $this->normalizeDecimalProps(['total']);
            $this->validate();

            $reason = $this->expenseKind === 'Fijos'
                ? $this->conceptNameById($this->concept_id)
                : trim($this->reason_text);

            $payload = [
                'date'           => $this->date,
                'reason'         => $reason,
                'kind'           => $this->expenseKind,
                'detail'         => trim($this->detail),
                'total'          => round((float)$this->total, 2),
                'document_type'  => trim((string)$this->document_type),
                'in_charge'      => trim((string)$this->in_charge),
                'user_id'        => Auth::id(),
                'headquarter_id' => $this->isDraco ? $this->headquarter_id : null,
            ];

            DB::transaction(function () use ($payload) {
                $exp = new Expense($payload);
                $exp->incrementing = true;
                $exp->save();

                foreach ($this->image_files as $file) {
                    $path = $file->storePublicly('expenses', 'public');
                    ExpenseImage::create([
                        'expense_id' => $exp->id,
                        'image_path' => $path,
                    ]);
                }
            });

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
