<?php
// app/Livewire/Reports/DeparturesStatsMonthly.php

namespace App\Livewire\Departures;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class Stats extends Component
{
    #[Url(except: null)]
    public ?int $year  = null;

    #[Url(except: null)]
    public ?int $month = null; // 1..12

    public int $daysInMonth = 30;

    /** Filas renderizables (2 por cada Controller/Paradero: "Salidas" y "S/") */
    public array $rows = [];

    /** Totales por día (columnas) */
    public array $totalsSalidas = []; // SUM(times)
    public array $totalsMonto   = []; // SUM(price)

    /** Totales generales */
    public int|float $grandSalidas = 0;
    public int|float $grandMonto   = 0;

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

    protected function loadData(): void
    {
        $start = CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();
        $end   = $start->endOfMonth();
        $this->daysInMonth = (int) $start->daysInMonth;

        /**
         * Estadístico:
         * - "Salidas": SUM(times)
         * - "S/":      SUM(price)
         * Por Controller (users.name) y Paradero (headquarters.name), por día.
         */
        $sql = <<<SQL
WITH RECURSIVE days(d) AS (
  SELECT 1
  UNION ALL
  SELECT d+1 FROM days WHERE d < DAY(LAST_DAY(:start_date))
),
d0 AS (
  SELECT
    d.id,
    d.user_id,
    d.headquarter_id,
    DATE(d.`date`)  AS ddate,
    COALESCE(d.times, 0)  AS times,
    COALESCE(d.price, 0)  AS price
  FROM departures d
  WHERE d.`date` BETWEEN :start_ts AND :end_ts
),
base AS (
  SELECT
    u.username  AS controller,
    h.name  AS stop,
    d0.ddate,
    SUM(d0.times) AS salidas,
    SUM(d0.price) AS monto
  FROM d0
  JOIN users        u ON u.id = d0.user_id
  JOIN headquarters h ON h.id = d0.headquarter_id
  GROUP BY u.username, h.name, d0.ddate
),
per_day AS (
  SELECT
    controller,
    stop,
    DAY(ddate)     AS day,
    salidas,
    monto
  FROM base
)
SELECT
  d.d          AS day,
  c.controller,
  c.stop,
  COALESCE(SUM(CASE WHEN p.day = d.d THEN p.salidas END), 0) AS salidas,
  COALESCE(SUM(CASE WHEN p.day = d.d THEN p.monto   END), 0) AS monto
FROM days d
LEFT JOIN (SELECT DISTINCT controller, stop FROM per_day) c ON 1=1
LEFT JOIN per_day p
  ON p.controller = c.controller AND p.stop = c.stop
GROUP BY c.controller, c.stop, d.d
ORDER BY c.controller, c.stop, d.d;
SQL;

        $raw = collect(DB::select($sql, [
            'start_date' => $start->format('Y-m-d'),
            'start_ts'   => $start->toDateTimeString(),
            'end_ts'     => $end->toDateTimeString(),
        ]));

        // Si no hay datos, resetea y sal.
        if ($raw->isEmpty()) {
            $this->rows = [];
            $this->totalsSalidas = array_fill(1, $this->daysInMonth, 0);
            $this->totalsMonto   = array_fill(1, $this->daysInMonth, 0);
            $this->grandSalidas = $this->grandMonto = 0;
            return;
        }

        $grouped = $raw->groupBy(fn($r) => ($r->controller ?? '—').'||'.($r->stop ?? '—'));

        $this->rows = [];
        $this->totalsSalidas = array_fill(1, $this->daysInMonth, 0);
        $this->totalsMonto   = array_fill(1, $this->daysInMonth, 0);
        $this->grandSalidas = $this->grandMonto = 0;

        foreach ($grouped as $key => $items) {
            [$controller, $stop] = explode('||', $key);

            $salidasDays = array_fill(1, $this->daysInMonth, 0);
            $montoDays   = array_fill(1, $this->daysInMonth, 0);

            foreach ($items as $row) {
                $d = (int) $row->day;
                $salidasDays[$d] = (int)   $row->salidas;
                $montoDays[$d]   = (float) $row->monto;
            }

            $salidasTotal = array_sum($salidasDays);
            $montoTotal   = array_sum($montoDays);

            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $this->totalsSalidas[$d] += $salidasDays[$d];
                $this->totalsMonto[$d]   += $montoDays[$d];
            }

            $this->grandSalidas += $salidasTotal;
            $this->grandMonto   += $montoTotal;

            // Fila "Salidas"
            $this->rows[] = [
                'controller' => $controller,
                'stop'       => $stop,
                'type'       => 'Salidas',
                'days'       => $salidasDays,
                'total_sal'  => $salidasTotal,
                'total_soles'=> null,
            ];
            // Fila "S/"
            $this->rows[] = [
                'controller' => $controller,
                'stop'       => $stop,
                'type'       => 'S/',
                'days'       => $montoDays,
                'total_sal'  => null,
                'total_soles'=> $montoTotal,
            ];
        }
    }

    public function render()
    {
        $years  = range((int)date('Y')-10, (int)date('Y')+1);
        $months = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ];

        return view('livewire.departures.stats', [
            'years'  => $years,
            'months' => $months,
        ]);
    }

    public function export(){
        $route = route('exports.departures-stats-report',
            [   "year" => $this->year,
                "month" => $this->month,
            ]);

        $this->dispatch('url-open',["url" => $route]);
    }
}
