<?php

namespace App\Livewire\Debts;

use App\Models\DebtDay;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class MonthlyDebt extends Component
{ // Filtros / estado de UI
    #[Url(as: 'month', history: true)]
    public string $monthDate = ''; // YYYY-MM-DD (usamos el día 1 del mes)

    // Filtros simples (opcionales)
    #[Url(as: 'q', history: true)]
    public string $search = ''; // buscar por placa (incluye legacy_plate)
    #[Url(as: 'cond', history: true)]
    public string $condition = ''; // DT / GN / EX / EX5 / Exonerado / Amortizado

    public array $rows = [];
    public array $totals = [
        'total'      => 0.0,
        'exonerated' => 0.0,
        'to_pay'     => 0.0,
        'amortized'  => 0.0,
        'pending'    => 0.0,
    ];

    public function mount(): void
    {
        if (empty($this->monthDate)) {
            // por defecto, día 1 del mes actual
            $this->monthDate = now()->startOfMonth()->toDateString();
        }
        $this->loadData();
    }

    public function updated($property): void
    {
        if (in_array($property, ['monthDate', 'search', 'condition'], true)) {
            $this->loadData();
        }
    }

    public function prevMonth(): void
    {
        $this->monthDate = Carbon::parse($this->monthDate ?: now())->subMonthNoOverflow()->startOfMonth()->toDateString();

        $this->loadData();
    }

    public function nextMonth(): void
    {
        $this->monthDate = Carbon::parse($this->monthDate ?: now())->addMonthNoOverflow()->startOfMonth()->toDateString();
        $this->loadData();
    }

    private function monthRange(): array
    {
        $d1 = Carbon::parse($this->monthDate ?: now())->startOfMonth();
        $d2 = (clone $d1)->endOfMonth();
        return [$d1->toDateString(), $d2->toDateString()];
    }

    public function render()
    {
        return view('livewire.debts.monthly-debt');
    }

    public function loadData(): void
    {
        [$from, $to] = $this->monthRange();

        // Base query a debt_days del mes
        $q = DebtDay::query()
            ->whereBetween('date', [$from, $to]);

        // Filtro por “condición”
        // - Si piden Exonerado → exonerated > 0
        // - Si piden Amortizado → se resuelve luego (porque está en payments)
        // - Si piden DT/GN/EX/EX5 → filtra por debt_days.condition
        $filterAmortized = false;
        if ($this->condition === 'Exonerado') {
            $q->where('exonerated', '>', 0);
        } elseif ($this->condition === 'Amortizado') {
            $filterAmortized = true; // se aplicará tras traer amortizaciones
        } elseif (!empty($this->condition)) {
            $q->where('condition', $this->condition);
        }

        // Filtro por búsqueda de placa (en plate del vehicle o en legacy_plate)
        if (!empty($this->search)) {
            $needle = mb_strtolower(trim($this->search));
            $q->where(function ($w) use ($needle) {
                // legacy_plate LIKE
                $w->whereRaw('LOWER(legacy_plate) LIKE ?', ['%'.$needle.'%'])
                    // o por vehicle_id a partir de vehicles.plate (usamos EXISTS)
                    ->orWhereExists(function ($sub) use ($needle) {
                        $sub->from('vehicles as v')
                            ->whereColumn('v.id', 'debt_days.vehicle_id')
                            ->whereRaw('LOWER(v.plate) LIKE ?', ['%'.$needle.'%']);
                    });
            });
        }

        // Traemos las filas del mes (sin N+1 de amortizaciones)
        $rows = $q->get();

        // Mapear vehicles (para COD/PLACA/CONDICIÓN si aplica)
        $vehicleIds = $rows->pluck('vehicle_id')->filter()->unique()->values();
        $vehicles = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->get(['id', 'sort_order', 'plate', 'condition'])
            ->keyBy('id');

        // Armar set para amortizaciones: por vehicle_id y por legacy_plate (is_support=1)
        $vehIdsForAmort = $rows->whereNotNull('vehicle_id')->pluck('vehicle_id')->unique()->values();
        $platesForAmort = $rows->whereNull('vehicle_id')->where('is_support', 1)->pluck('legacy_plate')->filter()->unique()->values();

        // Amortizaciones del mes (type='DEUDA'), usando COALESCE(date_payment, date_register)
        $dateExpr = DB::raw('COALESCE(date_payment, date_register)');

        $amortByVehicle = collect();
        if ($vehIdsForAmort->isNotEmpty()) {
            $amortByVehicle = Payment::query()
                ->selectRaw('vehicle_id, SUM(amount) as sum_amount')
                ->where('type', 'DEUDA')
                ->whereIn('vehicle_id', $vehIdsForAmort)
                ->whereBetween($dateExpr, [$from, $to])
                ->groupBy('vehicle_id')
                ->pluck('sum_amount', 'vehicle_id');
        }

        $amortByPlate = collect();
        if ($platesForAmort->isNotEmpty()) {
            $amortByPlate = Payment::query()
                ->selectRaw('legacy_plate, SUM(amount) as sum_amount')
                ->where('type', 'DEUDA')
                ->whereNull('vehicle_id')
                ->where('is_support', 1)
                ->whereIn('legacy_plate', $platesForAmort)
                ->whereBetween($dateExpr, [$from, $to])
                ->groupBy('legacy_plate')
                ->pluck('sum_amount', 'legacy_plate');
        }

        // Si pidieron "Amortizado", filtrar rows que tengan amortización > 0
        if ($filterAmortized) {
            $rows = $rows->filter(function ($r) use ($amortByVehicle, $amortByPlate) {
                if ($r->vehicle_id) {
                    return (float) ($amortByVehicle[$r->vehicle_id] ?? 0) > 0;
                }
                if ((int)$r->is_support === 1 && $r->legacy_plate) {
                    return (float) ($amortByPlate[$r->legacy_plate] ?? 0) > 0;
                }
                return false;
            })->values();
        }

        // Construcción de filas de salida + totales
        $out = [];
        $tt_total = 0.0;
        $tt_ex    = 0.0;
        $tt_toPay = 0.0;
        $tt_amort = 0.0;
        $tt_pend  = 0.0;

        $item = 0;
        foreach ($rows as $row) {
            $item++;

            // COD/PLACA/COND
            $id = $row->id;
            $veh      = $row->vehicle_id ? ($vehicles[$row->vehicle_id] ?? null) : null;
            $cod      = $veh->sort_order ?? '';
            $plateStr = $veh ? $veh->plate : ($row->legacy_plate ?? '');
            $cond     = $row->condition ?: ($veh->condition ?? '');

            // Texto de días “X / X1”
            $daysText = $this->buildDaysLabel($row, $from);

            // Amortización (S/)


            // Totales por fila (unidades correctas)
            $daysLate   = (int) $row->days_late;     // DÍAS
            $total      = (float) $row->total;       // S/
            $exonerated = (float) $row->exonerated;  // S/
            $amort = (float) $row->amortized;
            $toPay      = max(0.0, $total - $exonerated);            // S/
            $pending    = max(0.0, $total - $exonerated - $amort);   // S/

            $out[] = [
                'id' => $id,
                'item'        => $item,
                'cod'         => $cod,
                'plate'       => $plateStr,
                'condition'   => $cond,
                'days_text'   => $daysText,
                'days_late'   => $daysLate,    // T.D.N.T (días)
                'total'       => $total,       // T.D (S/)
                'exonerated'  => $exonerated,  // Ex (S/)
                'to_pay'      => $toPay,       // T.D.x.P (S/)
                'amortized'   => $amort,       // Amor (S/)
                'pending'     => $pending,     // Pend (S/)
            ];

            // Acumular totales generales
            $tt_total += $total;
            $tt_ex    += $exonerated;
            $tt_toPay += $toPay;
            $tt_amort += $amort;
            $tt_pend  += $pending;
        }

        $this->rows = $out;
        $this->totals = [
            'total'      => $tt_total,
            'exonerated' => $tt_ex,
            'to_pay'     => $tt_toPay,
            'amortized'  => $tt_amort,
            'pending'    => $tt_pend,
        ];
    }

    /**
     * Construye el texto de días con “X” y “X1”:
     * - “X”  → día sin pago con salidas
     * - “X1” → lo mismo, destacado en azul (legacy)
     */
    private function buildDaysLabel(DebtDay $row, string $fromDate): string
    {
        $monthStart = Carbon::parse($fromDate)->startOfMonth();
        $daysInMonth = $monthStart->daysInMonth;

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

    public function detail($id){
        $route = route('debts.monthly.detail',["id" => $id]);

        $this->dispatch('url-open',["url" => $route]);
    }
}
