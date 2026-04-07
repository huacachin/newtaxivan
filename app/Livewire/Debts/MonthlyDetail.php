<?php

namespace App\Livewire\Debts;

use App\Models\DebtDay;
use App\Models\DebtDayDetail;
use App\Models\DebtDayDetailImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\ValidationException;

class MonthlyDetail extends Component
{
    use WithFileUploads;

    public int $id;                 // debt_days.id desde la ruta
    public ?DebtDay $debtDay = null;

    // Cabecera
    public string $date = '';
    public string $plate = '';
    public int    $days = 0;        // <- ahora calculado desde d1..d31
    public float  $total = 0.0;

    // Inputs del formulario
    public float  $exonerateInput = 0.0;
    public string $detailInput = '';
    public float  $amortizeInput = 0.0; // opcional (en legacy estaba oculto)

    // Totales calculados
    public float $sumExonerated = 0.0;
    public float $sumAmortized  = 0.0;
    public float $pending       = 0.0;

    // Imágenes (upload múltiple — acumulativo)
    public $new_images = [];
    public $image_files = [];

    // Tabla de detalles
    public $details = [];

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

    public function mount(int $id): void
    {
        $this->id = $id;
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.debts.monthly-detail')->title('Deuda: Actualizar');
    }

    public function rules(): array
    {
        return [
            'exonerateInput' => ['nullable','numeric','min:0', function($attr,$value,$fail){
                if ($value > $this->pending) $fail('El exonerado no puede superar el pendiente.');
            }],
            'amortizeInput'  => ['nullable','numeric','min:0', function($attr,$value,$fail){
                if ($value > $this->pending) $fail('La amortización no puede superar el pendiente.');
            }],
            'detailInput'    => ['required','string','max:500'],
            'new_images'    => ['nullable','array','max:10'],
            'new_images.*'  => ['image','max:3072'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $ex = (float)($this->exonerateInput ?: 0);
        $am = (float)($this->amortizeInput ?: 0);

        if ($ex <= 0 && $am <= 0) {
            $this->addError('exonerateInput', 'Debes ingresar exonerado y/o amortización.');
            return;
        }

        // Nueva validación conjunta (suma)
        if (round($ex + $am, 2) > round($this->pending, 2)) {
            throw ValidationException::withMessages([
                'exonerateInput' => 'La suma de Exonerado + Amortización no puede superar el pendiente.',
            ]);
        }

        DB::transaction(function () use ($ex, $am) {
            // 1) Crear detalle
            $detail = DebtDayDetail::create([
                'debt_days_id' => $this->debtDay->id,
                'exonerated'   => round($ex, 2),
                'amortized'    => round($am, 2),
                'detail'       => trim($this->detailInput),
                'user_id'      => 1, // TODO: usa Auth::id() cuando corresponda
                'date'         => now()->toDateString(),
            ]);

            // 2) Guardar imágenes
            if (!empty($this->image_files)) {
                foreach ($this->image_files as $file) {
                    $path = $file->storePublicly('debt-details', 'public');
                    DebtDayDetailImage::create([
                        'debt_day_detail_id' => $detail->id,
                        'image_path'         => $path,
                    ]);
                }
            }

            // 3) Recalcular totales del padre desde los detalles (fuente de la verdad)
            $sums = DebtDayDetail::where('debt_days_id', $this->debtDay->id)
                ->selectRaw('COALESCE(SUM(exonerated),0) exo, COALESCE(SUM(amortized),0) amo')
                ->first();

            $append = $this->debtDay->detail_exonerated ? ($this->debtDay->detail_exonerated.' - ') : '';
            $this->debtDay->update([
                'exonerated'        => round((float)$sums->exo, 2),
                'amortized'         => round((float)$sums->amo, 2),
                'detail_exonerated' => $append . trim($this->detailInput),
            ]);
        });

        // Reset y recarga
        $this->reset(['exonerateInput','amortizeInput','detailInput','image_files','new_images']);
        $this->loadData();
        $this->dispatch('successAlert',["message" => "Se ha guardado exitosamente."]);
    }

    public function questionDelete($id): void
    {
        $this->dispatch('questionDelete',["id" => $id]);
    }

    #[On('register_destroy')]
    public function deleteDetail(int $id): void
    {
        DB::transaction(function () use ($id) {
            $detail = DebtDayDetail::where('debt_days_id', $this->id)->findOrFail($id);

            // Eliminar imágenes del storage
            foreach ($detail->images as $img) {
                Storage::disk('public')->delete($img->image_path);
            }
            $detail->images()->delete();

            $detail->delete();

            $sums = DebtDayDetail::where('debt_days_id', $this->id)
                ->selectRaw('COALESCE(SUM(exonerated),0) exo, COALESCE(SUM(amortized),0) amo')
                ->first();

            $this->debtDay->update([
                'exonerated' => round((float)$sums->exo, 2),
                'amortized'  => round((float)$sums->amo, 2),
            ]);
        });

        $this->loadData();
        session()->flash('ok','Detalle eliminado.');
    }

    private function loadData(): void
    {
        $this->debtDay = DebtDay::with(['vehicle','details.user','details.images'])
            ->findOrFail($this->id);

        $this->date  = (string)$this->debtDay->date;
        $this->plate = $this->debtDay->vehicle?->plate ?: ($this->debtDay->legacy_plate ?? '');
        $this->total = (float)($this->debtDay->total ?? 0);

        // ✅ DÍAS NO TRABAJADOS: contar X / X1 en d1..d31
        $this->days  = $this->countDaysFromColumns($this->debtDay);

        // Totales desde detalles
        $this->sumExonerated = (float)$this->debtDay->details->sum('exonerated');
        $this->sumAmortized  = (float)$this->debtDay->details->sum('amortized');
        $this->pending       = max(0, round($this->total - $this->sumExonerated - $this->sumAmortized, 2));

        $this->details = $this->debtDay->details
            ->sortByDesc('date')
            ->values()
            ->map(fn($d) => [
                'id'         => $d->id,
                'date'       => $d->date,
                'detail'     => $d->detail,
                'exonerated' => number_format((float)$d->exonerated, 2),
                'amortized'  => number_format((float)$d->amortized, 2),
                'user'       => $d->user?->username ?? '—',
                'images'     => $d->images->map(fn($img) => [
                    'id'   => $img->id,
                    'url'  => asset('storage/' . $img->image_path),
                ])->all(),
            ])->all();
    }

    /** Cuenta cuántos días d1..d31 están marcados con 'X' o 'X1'. */
    private function countDaysFromColumns(DebtDay $row): int
    {
        $count = 0;
        for ($i = 1; $i <= 31; $i++) {
            $col = 'd'.$i;
            $val = (string)($row->{$col} ?? '');
            if ($val === 'X' || $val === 'X1') {
                $count++;
            }
        }
        return $count;
    }

    /** Texto de días “X / X1” (azul en X1) */
    public function getDaysStringProperty(): string
    {
        $out = [];
        for ($i=1; $i<=31; $i++) {
            $key = 'd'.$i;
            $val = (string)($this->debtDay->{$key} ?? '');
            if ($val === 'X' || $val === 'X1') {
                $num = (string)$i;
                $out[] = $val === 'X1'
                    ? "<b style=\"color:blue\">{$num}</b>"
                    : $num;
            }
        }
        return implode(', ', $out);
    }
}
