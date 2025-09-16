<?php

namespace App\Livewire\Debts;

use App\Models\DebtDay;
use App\Models\DebtDayDetail;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MonthlyDetail extends Component
{
    public int $id;                 // debt_days.id desde la ruta
    public ?DebtDay $debtDay = null;

    // Cabecera
    public string $date = '';
    public string $plate = '';
    public int    $days = 0;
    public float  $total = 0.0;

    // Inputs del formulario
    public float  $exonerateInput = 0.0;
    public string $detailInput = '';
    public float  $amortizeInput = 0.0; // opcional (en legacy estaba oculto)

    // Totales calculados
    public float $sumExonerated = 0.0;
    public float $sumAmortized  = 0.0;
    public float $pending       = 0.0;

    // Tabla de detalles
    public $details = [];

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
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->exonerateInput <= 0 && $this->amortizeInput <= 0) {
            $this->addError('exonerateInput', 'Debes ingresar exonerado y/o amortización.');
            return;
        }

        DB::transaction(function () {
            // 1) Crear detalle
            DebtDayDetail::create([
                'debt_days_id' => $this->debtDay->id,
                'exonerated'   => round($this->exonerateInput ?: 0, 2),
                'amortized'    => round($this->amortizeInput ?: 0, 2),
                'detail'       => trim($this->detailInput),
                'user_id'      => 1,//optional(Auth::user())->id,
                'date'         => now()->toDateString(),
            ]);

            // 2) Recalcular totales del padre desde los detalles (fuente de la verdad)
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
        $this->reset(['exonerateInput','amortizeInput','detailInput']);
        $this->loadData();
        session()->flash('ok', 'Guardado con éxito.');
    }

    public function deleteDetail(int $detailId): void
    {
        DB::transaction(function () use ($detailId) {
            $detail = DebtDayDetail::where('debt_days_id', $this->id)->findOrFail($detailId);
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
        $this->debtDay = DebtDay::with(['vehicle','details.user'])
            ->findOrFail($this->id);

        $this->date  = (string)$this->debtDay->date;
        $this->plate = $this->debtDay->vehicle?->plate ?: ($this->debtDay->legacy_plate ?? '');
        $this->days  = (int)($this->debtDay->days ?? 0);
        $this->total = (float)($this->debtDay->total ?? 0);

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
                'user'       => $d->user?->name ?? '—',
            ])->all();
    }

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
