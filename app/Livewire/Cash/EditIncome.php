<?php

namespace App\Livewire\Cash;

use App\Models\Income;
use App\Models\IncomeImage;
use App\Traits\NormalizesDecimals;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditIncome extends Component
{
    use WithFileUploads, NormalizesDecimals;

    public Income $income;
    public int $incomeId;

    public string $date          = '';
    public string $reason        = '';
    public string $detail        = '';
    public string $currency      = 'Soles';
    public float|string $amount_input  = '';
    public float  $exchange      = 3.80;
    public array $amountSuggestions = [];
    public ?float $converted_total = null;

    public array $reasonTemplates = [];
    public ?string $reasonModalText = null;

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
        $this->income   = Income::with('images')->findOrFail($id);

        if (auth()->user()->cannot('update', $this->income)) {
            abort(403);
        }
        $this->incomeId = $id;

        $i = $this->income;

        $this->date         = $i->date ? Carbon::parse($i->date)->toDateString() : now()->toDateString();
        $this->reason       = (string)$i->reason;
        $this->detail       = (string)$i->detail;
        $this->currency     = 'Soles';
        $this->amount_input = number_format((float)$i->total, 2, '.', '');
        $this->exchange     = 3.80;

        $this->existing_images = $i->images->map(fn($img) => [
            'id'  => (int)$img->id,
            'url' => asset('storage/' . $img->image_path),
        ])->all();

        $this->recalcConverted();
        $this->recomputeAmountSuggestions();
        $this->loadReasonTemplates();
    }

    public function updatedReason(): void
    {
        $this->recomputeAmountSuggestions();
    }

    protected function loadReasonTemplates(): void
    {
        $this->reasonTemplates = \DB::table('incomes')
            ->whereNotNull('detail')
            ->whereRaw('CHAR_LENGTH(detail) >= ?', [30])
            ->select('detail', \DB::raw('COUNT(*) as freq'))
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

    protected function recomputeAmountSuggestions(): void
    {
        $since = now()->subDays(180)->toDateString();
        $q = \DB::table('incomes')
            ->where('total', '>', 0)
            ->where('date', '>=', $since);

        if (!empty($this->reason)) {
            $q->where('reason', $this->reason);
        }

        $this->amountSuggestions = $q
            ->select('total', \DB::raw('COUNT(*) as c'))
            ->groupBy('total')->orderByDesc('c')->limit(3)
            ->pluck('total')
            ->map(fn ($v) => number_format((float) $v, 2, '.', ''))
            ->values()->all();
    }

    protected function rules(): array
    {
        return [
            'date'         => ['required', 'date'],
            'reason'       => ['required', 'string', 'max:100'],
            'detail'       => ['required', 'string', 'max:255'],
            'currency'     => ['required', Rule::in(['Soles', 'Dolares'])],
            'amount_input' => ['required', 'numeric', 'gt:0'],
            'new_images'   => ['nullable', 'array', 'max:10'],
            'new_images.*' => ['image', 'max:3072'],
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
            'new_images.*.image'    => 'Cada archivo debe ser una imagen válida.',
            'new_images.*.max'      => 'Cada imagen no debe superar 3MB.',
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
        $income = Income::findOrFail($id);

        if (auth()->user()->cannot('delete', $income)) {
            abort(403);
        }

        foreach ($income->images as $img) {
            if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
        }

        $income->delete();
        session()->flash('income_success', 'Ingreso eliminado correctamente.');
        $this->redirectRoute('cash.incomes');
    }

    public function update(): void
    {
        if (auth()->user()->cannot('update', $this->income)) {
            abort(403);
        }

        try {
            $this->normalizeDecimalProps(['amount_input']);
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

            DB::transaction(function () use ($payload) {
                $this->income->update($payload);

                if (!empty($this->deleted_image_ids)) {
                    $imagesToDelete = IncomeImage::where('income_id', $this->income->id)
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
                    $path = $file->storePublicly('incomes', 'public');
                    IncomeImage::create([
                        'income_id'  => $this->income->id,
                        'image_path' => $path,
                    ]);
                }
            });

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
