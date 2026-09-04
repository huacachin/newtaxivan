<?php

namespace App\Livewire\Debts;

use App\Models\DebtDay;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class MonthlyDebt extends Component
{
    // ======= Filtros / UI =======
    #[Url(as: 'month', history: true)]
    public string $monthDate = ''; // YYYY-MM-DD (día 1 del mes)

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'cond', history: true)]
    public string $condition = ''; // DT / GN / EX / EX5 / Exonerado / Amortizado

    // Selects mes/año para homogeneizar con la vista
    public int $month;            // 1..12
    public int $year;             // p.ej. 2023..2028
    public array $months = [];    // [1=>'Enero', ...]
    public array $years  = [];    // [2022,2023,2024,...]

    // Datos de salida
    public array $rows = [];
    public array $totals = [
        'total'      => 0.0,
        'exonerated' => 0.0,
        'to_pay'     => 0.0,
        'amortized'  => 0.0,
        'pending'    => 0.0,
    ];

    // ======= Ciclo de vida =======
    public function mount(): void
    {
        // monthDate por defecto: primer día del mes actual
        if (empty($this->monthDate)) {
            $this->monthDate = now()->startOfMonth()->toDateString();
        }

        // Catálogo de meses (ES)
        $this->months = [
            1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril',
            5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto',
            9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre',
        ];

        // Rango de años (ajústalo a tu gusto)
        $yNow = (int) now()->year;
        $this->years = range($yNow - 5, $yNow + 1);

        // Sincronizar month/year a partir de monthDate
        $this->syncMonthYearFromMonthDate();

        $this->loadData();
    }

    public function render()
    {
        return view('livewire.debts.monthly-debt');
    }

    // ======= Sync helpers =======
    private function syncMonthYearFromMonthDate(): void
    {
        $d = Carbon::parse($this->monthDate ?: now())->startOfMonth();
        $this->month = (int) $d->month;
        $this->year  = (int) $d->year;
    }

    private function syncMonthDateFromParts(): void
    {
        // Siempre usa día 1 del mes seleccionado
        $m = max(1, min(12, (int)$this->month));
        $y = (int) $this->year;
        $this->monthDate = Carbon::create($y, $m, 1)->toDateString();
    }

    private function monthRange(): array
    {
        $d1 = Carbon::parse($this->monthDate ?: now())->startOfMonth();
        $d2 = (clone $d1)->endOfMonth();
        return [$d1->toDateString(), $d2->toDateString()];
    }

    // ======= Listeners de UI =======
    public function updated($prop): void
    {
        if (in_array($prop, ['monthDate','search','condition'], true)) {
            if ($prop === 'monthDate') {
                $this->syncMonthYearFromMonthDate();
            }
            $this->loadData();
        }
    }

    // Cambios desde los selects
    public function updatedMonth(): void
    {
        $this->syncMonthDateFromParts();
        $this->loadData();
    }
    public function updatedYear(): void
    {
        $this->syncMonthDateFromParts();
        $this->loadData();
    }

    public function prevMonth(): void
    {
        $this->monthDate = Carbon::parse($this->monthDate ?: now())
            ->subMonthNoOverflow()
            ->startOfMonth()
            ->toDateString();

        $this->syncMonthYearFromMonthDate();
        $this->loadData();
    }

    public function nextMonth(): void
    {
        $this->monthDate = Carbon::parse($this->monthDate ?: now())
            ->addMonthNoOverflow()
            ->startOfMonth()
            ->toDateString();

        $this->syncMonthYearFromMonthDate();
        $this->loadData();
    }

    // ======= Core =======
    public function loadData(): void
    {
        [$from, $to] = $this->monthRange();

        $q = DebtDay::query()
            ->whereBetween('date', [$from, $to]);

        // Filtro por condición
        if ($this->condition === 'Exonerado') {
            $q->where('exonerated', '>', 0);
        } elseif ($this->condition === 'Amortizado') {
            $q->where('amortized', '>', 0);
        } elseif (!empty($this->condition)) {
            $q->where('condition', $this->condition);
        }

        // Filtro por búsqueda de placa (en legacy_plate o vehicles.plate)
        if (!empty($this->search)) {
            $needle = mb_strtolower(trim($this->search));
            $q->where(function ($w) use ($needle) {
                $w->whereRaw('LOWER(legacy_plate) LIKE ?', ['%' . $needle . '%'])
                    ->orWhereExists(function ($sub) use ($needle) {
                        $sub->from('vehicles as v')
                            ->whereColumn('v.id', 'debt_days.vehicle_id')
                            ->whereRaw('LOWER(v.plate) LIKE ?', ['%' . $needle . '%']);
                    });
            });
        }

        $rows = $q->with('details.images')->orderBy('id')->get();

        // Map vehículos para COD/PLACA/COND
        $vehicleIds = $rows->pluck('vehicle_id')->filter()->unique()->values();

        // Puedes dejarlo sin orderBy aquí; el orden real lo haremos en $out.
        // Lo dejo con COALESCE por si lo quieres mantener.
        $vehicles = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->get(['id','sort_order','plate','condition'])
            ->keyBy('id');

        // Lookup por placa para legacy plates (sin vehicle_id)
        $legacyPlates = $rows->whereNull('vehicle_id')->pluck('legacy_plate')->filter()->unique()->values();
        $vehiclesByPlate = $legacyPlates->isNotEmpty()
            ? Vehicle::query()
                ->whereIn('plate', $legacyPlates)
                ->get(['id','sort_order','plate','condition'])
                ->keyBy(fn($v) => strtoupper(trim($v->plate)))
            : collect();

        $out = [];

        // reinicia acumuladores en cada loadData()
        $tt_total = 0.0;
        $tt_ex    = 0.0;
        $tt_toPay = 0.0;
        $tt_amort = 0.0;
        $tt_pend  = 0.0;

        $item = 0;
        foreach ($rows as $row) {
            $item++;

            $veh      = $row->vehicle_id ? ($vehicles[$row->vehicle_id] ?? null) : null;
            if (!$veh && $row->legacy_plate) {
                $veh = $vehiclesByPlate[strtoupper(trim($row->legacy_plate))] ?? null;
            }
            $cod      = $veh?->sort_order ?? '';
            $plateStr = $veh ? $veh->plate : ($row->legacy_plate ?? '');
            $cond     = $row->condition ?: ($veh->condition ?? '');

            $daysText      = $this->buildDaysLabel($row, $from);
            $daysBreakdown = $this->buildDaysBreakdown($row, $from);

            $daysLate   = (int) $row->days_late;    // DÍAS
            $total      = (float) $row->total;      // S/
            $exonerated = (float) $row->exonerated; // S/
            $amort      = (float) $row->amortized;  // S/
            $toPay      = max(0.0, $total - $exonerated);          // S/
            $pending    = max(0.0, $total - $exonerated - $amort); // S/

            // Recopilar imágenes de todos los detalles
            $images = [];
            foreach ($row->details as $det) {
                foreach ($det->images as $img) {
                    $images[] = asset('storage/' . $img->image_path);
                }
            }

            $out[] = [
                'id'             => $row->id,
                'item'           => $item,
                'cod'            => $cod,
                'plate'          => $plateStr,
                'condition'      => $cond,
                'days_text'      => $daysText,
                'days_breakdown' => $daysBreakdown,
                'days_late'      => $daysLate,
                'total'          => $total,
                'exonerated'     => $exonerated,
                'to_pay'         => $toPay,
                'amortized'      => $amort,
                'pending'        => $pending,
                'images'         => $images,
            ];

            // Acumuladores
            $tt_total += $total;
            $tt_ex    += $exonerated;
            $tt_toPay += $toPay;
            $tt_amort += $amort;
            $tt_pend  += $pending;
        }

        $this->rows = collect($out)->values()->all();
        $this->totals = [
            'total'      => $tt_total,
            'exonerated' => $tt_ex,
            'to_pay'     => $tt_toPay,
            'amortized'  => $tt_amort,
            'pending'    => $tt_pend,
        ];
    }


    /** Construye texto de días “X / X1” (azul en X1) */
    private function buildDaysLabel(DebtDay $row, string $fromDate): string
    {
        $monthStart   = Carbon::parse($fromDate)->startOfMonth();
        $daysInMonth  = $monthStart->daysInMonth;

        $parts = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = 'd'.$d;
            $val = (string) ($row->{$col} ?? '');
            if ($val === 'X1') {
                $parts[] = "<b style='color:blue'>{$d},</b>";
            } elseif ($val === 'X') {
                $parts[] = "{$d},";
            }
        }
        return implode('', $parts);
    }

    /**
     * Devuelve un array estructurado de los días no trabajados,
     * pensado para renderizar chips en la vista mobile.
     *   [['day' => 5, 'kind' => 'X'], ['day' => 12, 'kind' => 'X1'], ...]
     */
    private function buildDaysBreakdown(DebtDay $row, string $fromDate): array
    {
        $monthStart  = Carbon::parse($fromDate)->startOfMonth();
        $daysInMonth = $monthStart->daysInMonth;

        $out = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $val = (string) ($row->{'d'.$d} ?? '');
            if ($val === 'X' || $val === 'X1') {
                $out[] = ['day' => $d, 'kind' => $val];
            }
        }
        return $out;
    }

    public function detail($id): void
    {
        $route = route('debts.monthly.detail', ["id" => $id]);
        $this->dispatch('url-open', ["url" => $route]);
    }

    // ======= Export (ajusta route names si usas otros) =======
    public function exportSummary(): void
    {
        $route = route('exports.debts.monthly', [
            'month'     => $this->monthDate,
            'search'    => $this->search,
            'condition' => $this->condition,
        ]);
        $this->dispatch('url-open', ['url' => $route]);
    }

    public function export(): void
    {
        $route = route('exports.debts-monthly', [
            'month'     => $this->monthDate,
            'search'    => $this->search,
            'condition' => $this->condition,
        ]);
        $this->dispatch('url-open', ['url' => $route]);
    }
}
