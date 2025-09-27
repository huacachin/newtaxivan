<?php

namespace App\Livewire\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class Stats extends Component
{
    #[Url(except: null)] public ?int $year  = null;   // 1..N
    #[Url(except: null)] public ?int $month = null;   // 1..12

    public int $daysInMonth = 30;

    /**
     * Estructura:
     * $rows = [
     *   'Controller Name' => [
     *      'Headquarter Name' => [
     *         'PAGO'    => ['days'=>[1=>monto,...], 'total'=>x],
     *         'RETRASO' => ['days'=>[], 'total'=>x],
     *         'DEUDA'   => ['days'=>[], 'total'=>x],
     *      ],
     *   ],
     * ]
     */
    public array $rows = [];

    public array $totalsPerDay = [];   // suma PAGO+RETRASO+DEUDA por día
    public float $grandTotal   = 0.0;  // total general

    public function mount(): void
    {
        $now = CarbonImmutable::now();
        $this->year  = $this->year  ?: (int) $now->year;
        $this->month = $this->month ?: (int) $now->month;
        $this->loadData();
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['year','month'], true)) {
            $this->loadData();
        }
    }

    public function render()
    {
        $years  = range((int)date('Y') - 10, (int)date('Y') + 1);
        $months = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ];

        return view('livewire.payments.stats', compact('years','months'));
    }

    /* ======================= CORE ======================= */

    protected function loadData(): void
    {
        $this->rows = [];
        $this->totalsPerDay = [];
        $this->grandTotal = 0.0;

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();
        $this->daysInMonth = (int) $start->daysInMonth;

        // Inicializamos totales por día
        for ($d=1; $d <= $this->daysInMonth; $d++) {
            $this->totalsPerDay[$d] = 0.0;
        }

        // Agregación por controlador, sucursal, tipo y día
        // Nota: usamos date_register para estadístico (como “Caja”).
        $aggs = DB::table('payments as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
            ->leftJoin('headquarters as h', 'h.id', '=', 'p.headquarter_id')
            ->selectRaw("
                COALESCE(u.name,'-')  as controller,
                COALESCE(h.name,'-')  as headquarter,
                UPPER(p.type)         as t,
                DAY(p.date_register)  as d,
                SUM(p.amount)         as s
            ")
            ->whereYear('p.date_register', $start->year)
            ->whereMonth('p.date_register', $start->month)
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO','DEUDA'])
            ->groupBy('controller','headquarter','t','d')
            ->orderBy('controller')
            ->orderBy('headquarter')
            ->get();

        // Pre-armamos estructura vacía para cada (controller, headquarter)
        foreach ($aggs as $r) {
            $ctrl = (string)$r->controller;
            $hq   = (string)$r->headquarter;

            $this->rows[$ctrl] = $this->rows[$ctrl] ?? [];
            $this->rows[$ctrl][$hq] = $this->rows[$ctrl][$hq] ?? [
                'PAGO'    => ['days'=>array_fill(1,$this->daysInMonth,0.0), 'total'=>0.0],
                'RETRASO' => ['days'=>array_fill(1,$this->daysInMonth,0.0), 'total'=>0.0],
                'DEUDA'   => ['days'=>array_fill(1,$this->daysInMonth,0.0), 'total'=>0.0],
            ];
        }

        // Asignamos montos por día
        foreach ($aggs as $r) {
            $ctrl = (string)$r->controller;
            $hq   = (string)$r->headquarter;
            $t    = (string)$r->t;
            $d    = (int)$r->d;
            $s    = (float)$r->s;

            if (!isset($this->rows[$ctrl][$hq][$t])) continue;
            if ($d >= 1 && $d <= $this->daysInMonth) {
                $this->rows[$ctrl][$hq][$t]['days'][$d] += $s;
            }
        }

        // Totales por fila y totales generales
        foreach ($this->rows as $ctrl => $byHq) {
            foreach ($byHq as $hq => $blocks) {
                foreach (['PAGO','RETRASO','DEUDA'] as $t) {
                    $sumRow = array_sum($blocks[$t]['days']);
                    $this->rows[$ctrl][$hq][$t]['total'] = $sumRow;

                    // Totales por día (sumamos los 3 tipos)
                    for ($d=1; $d <= $this->daysInMonth; $d++) {
                        $this->totalsPerDay[$d] += (float)$blocks[$t]['days'][$d];
                    }
                    $this->grandTotal += (float)$sumRow;
                }
            }
        }
    }

    /* ======================= HELPERS ======================= */

    public function monthName(): string
    {
        return strtoupper(\Carbon\Carbon::create($this->year, $this->month, 1)->translatedFormat('F'));
    }

    public function export(): void{
        $route = route('exports.payments-stats',
            [   "year" => $this->year,
                "month" => $this->month,
            ]);

        $this->dispatch('url-open',["url" => $route]);
    }

}
