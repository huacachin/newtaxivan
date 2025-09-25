<?php
// app/Livewire/Reports/DeparturesMonthlyByStop.php

namespace App\Livewire\Departures;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class Rmp extends Component
{
    #[Url(except: null)]
    public ?int $year  = null;

    #[Url(except: null)]
    public ?int $month = null; // 1..12

    public int $daysInMonth = 30;

    /** Filas del grid */
    public array $rows = [];

    /** Totales por día (columnas) */
    public array $totalsTE = []; // Empresa (vehículo existe en 'vehicles')
    public array $totalsTA = []; // Apoyo   (vehículo NO existe en 'vehicles')
    public array $totalsVT = []; // TE + TA

    /** Totales generales (última col) */
    public int $grandTE = 0;
    public int $grandTA = 0;
    public int $grandVT = 0;

    public function mount()
    {
        $now = CarbonImmutable::now();
        $this->year  = $this->year  ?: (int) $now->year;
        $this->month = $this->month ?: (int) $now->month;

        $this->loadData();
    }

    public function updated($prop)
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
         * Reglas:
         * - Emp.  = vehículo EXISTE en 'vehicles' (por id o por plate normalizado). No importa si está inactivo.
         * - Apoyo = vehículo NO existe en 'vehicles'.
         * - Conteo DISTINCT por vehículo/día: vkey basado en vehicle_id o legacy_plate (upper/trim). Fallback id.
         */
        $sql = <<<SQL
WITH RECURSIVE days(d) AS (
  SELECT 1
  UNION ALL
  SELECT d+1 FROM days WHERE d < DAY(LAST_DAY(:start_date))
),
d0 AS (
  SELECT
    id,
    `date`,
    user_id,
    headquarter_id,
    vehicle_id,
    legacy_plate,
    CASE
      WHEN vehicle_id IS NOT NULL THEN CONCAT('v#', vehicle_id)
      WHEN legacy_plate IS NOT NULL AND legacy_plate <> '' THEN CONCAT('p#', UPPER(TRIM(legacy_plate)))
      ELSE CONCAT('x#', id)
    END AS vkey
  FROM departures
  WHERE `date` BETWEEN :start_ts AND :end_ts
),
base AS (
  SELECT
    u.name          AS controller,
    h.name          AS stop,
    DATE(d0.`date`) AS ddate,

    COUNT(DISTINCT CASE
      WHEN (v1.id IS NOT NULL OR v2.id IS NOT NULL)
      THEN d0.vkey END
    ) AS emp_distinct,

    COUNT(DISTINCT CASE
      WHEN (v1.id IS NULL AND v2.id IS NULL)
      THEN d0.vkey END
    ) AS apoyo_distinct

  FROM d0
  JOIN users        u ON u.id = d0.user_id
  JOIN headquarters h ON h.id = d0.headquarter_id

  LEFT JOIN vehicles v1 ON v1.id = d0.vehicle_id
  LEFT JOIN vehicles v2
    ON UPPER(TRIM(v2.plate)) = UPPER(TRIM(d0.legacy_plate))

  GROUP BY u.name, h.name, DATE(d0.`date`)
),
per_day AS (
  SELECT
    controller,
    stop,
    DAY(ddate) AS day,
    emp_distinct   AS emp,
    apoyo_distinct AS apoyo
  FROM base
)
SELECT
  d.d AS day,
  c.controller,
  c.stop,
  COALESCE(SUM(CASE WHEN cday.day = d.d THEN cday.emp   END), 0) AS emp,
  COALESCE(SUM(CASE WHEN cday.day = d.d THEN cday.apoyo END), 0) AS apoyo
FROM days d
LEFT JOIN (SELECT DISTINCT controller, stop FROM per_day) c ON 1=1
LEFT JOIN per_day cday
  ON cday.controller = c.controller AND cday.stop = c.stop
GROUP BY c.controller, c.stop, d.d
ORDER BY c.controller, c.stop, d.d;
SQL;

        $bindings = [
            'start_date' => $start->format('Y-m-d'),
            'start_ts'   => $start->toDateTimeString(),
            'end_ts'     => $end->toDateTimeString(),
        ];

        $raw = collect(DB::select($sql, $bindings));

        // Reagrupar por controller/stop
        $grouped = $raw->groupBy(fn($r) => ($r->controller ?? '—').'||'.($r->stop ?? '—'));

        $this->rows = [];
        $this->totalsTE = array_fill(1, $this->daysInMonth, 0);
        $this->totalsTA = array_fill(1, $this->daysInMonth, 0);
        $this->totalsVT = array_fill(1, $this->daysInMonth, 0);
        $this->grandTE = $this->grandTA = $this->grandVT = 0;

        foreach ($grouped as $key => $items) {
            [$controller, $stop] = explode('||', $key);

            $empDays = array_fill(1, $this->daysInMonth, 0);
            $apoDays = array_fill(1, $this->daysInMonth, 0);

            foreach ($items as $row) {
                $d = (int) $row->day;
                $empDays[$d] = (int) $row->emp;
                $apoDays[$d] = (int) $row->apoyo;
            }

            $empTotal = array_sum($empDays);
            $apoTotal = array_sum($apoDays);

            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $this->totalsTE[$d] += $empDays[$d];
                $this->totalsTA[$d] += $apoDays[$d];
                $this->totalsVT[$d] += $empDays[$d] + $apoDays[$d];
            }

            $this->grandTE += $empTotal;
            $this->grandTA += $apoTotal;
            $this->grandVT += $empTotal + $apoTotal;

            // Fila Empresa
            $this->rows[] = [
                'controller' => $controller,
                'stop'       => $stop,
                'type'       => 'Emp',
                'days'       => $empDays,
                'total'      => $empTotal,
            ];
            // Fila Apoyo
            $this->rows[] = [
                'controller' => $controller,
                'stop'       => $stop,
                'type'       => 'Apoyo',
                'days'       => $apoDays,
                'total'      => $apoTotal,
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

        return view('livewire.departures.rmp', [
            'years'  => $years,
            'months' => $months,
        ]);
    }

    public function export(){
        $route = route('exports.departures-rmp-report',
            [   "year" => $this->year,
                "month" => $this->month,
            ]);

        $this->dispatch('url-open',["url" => $route]);
    }
}
