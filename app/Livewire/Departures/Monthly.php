<?php

namespace App\Livewire\Departures;


use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Monthly extends Component
{
    public string $selectedDate = '';
    public int $year = 0;
    public int $month = 0;
    public int $daysInMonth = 0;
    public array $days = [];
    public array $rows = [];
    public array $totalPerDay = [];
    public array $vehiclesWorkedPerDay = [];

    /** Si es null, se usa COUNT(*) */
    protected ?string $countColumn = null;

    /** Cambia si tu campo de fecha en departures tiene otro nombre (p. ej. 'fecha') */
    protected string $dateColumn  = 'date';

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->setupMonth();
        $this->detectCountColumn();
        $this->recalc();
    }

    public function updatedSelectedDate(): void
    {
        $this->setupMonth();
        $this->recalc();
    }

    public function prevMonth(): void
    {
        $d = Carbon::parse($this->selectedDate)->startOfMonth()->subMonth();
        $this->selectedDate = $d->toDateString();
        $this->setupMonth();
        $this->recalc();
    }

    public function nextMonth(): void
    {
        $d = Carbon::parse($this->selectedDate)->startOfMonth()->addMonth();
        $this->selectedDate = $d->toDateString();
        $this->setupMonth();
        $this->recalc();
    }

    public function render()
    {
        return view('livewire.departures.monthly');
    }

    /* ===================== Core ===================== */

    protected function setupMonth(): void
    {
        $d = Carbon::parse($this->selectedDate);
        $this->year        = (int) $d->year;
        $this->month       = (int) $d->month;
        $this->daysInMonth = (int) $d->daysInMonth;
        $this->days        = range(1, $this->daysInMonth);
    }

    protected function detectCountColumn(): void
    {
        if (!Schema::hasTable('departures')) {
            $this->countColumn = null;
            return;
        }

        if (!Schema::hasColumn('departures', $this->dateColumn)) {
            $this->dateColumn = 'date'; // ajusta aquí si tu columna real es 'fecha'
        }

        // Preferencias conocidas; si ninguna existe, contaremos filas (COUNT(*))
        $candidates = ['num', 'quantity', 'laps', 'vueltas', 'count', 'total_turns'];
        foreach ($candidates as $c) {
            if (Schema::hasColumn('departures', $c)) {
                $this->countColumn = $c;
                return;
            }
        }
        $this->countColumn = null;
    }

    protected function recalc(): void
    {
        $this->rows = [];
        $this->totalPerDay = array_fill(1, $this->daysInMonth, 0);
        $this->vehiclesWorkedPerDay = array_fill(1, $this->daysInMonth, 0);

        if (!Schema::hasTable('vehicles') || !Schema::hasTable('departures')) {
            return;
        }

        $start = Carbon::create($this->year, $this->month, 1)->toDateString();
        $end   = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();

        // Vehículos
        $orderCol = Schema::hasColumn('vehicles', 'order')
            ? 'order'
            : (Schema::hasColumn('vehicles', 'orden') ? 'orden' : 'plate');

        $vehicles = DB::table('vehicles')
            ->select('id', 'plate')
            ->orderBy($orderCol)
            ->get();

        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'plate' => (string)$v->plate,
                'daily' => array_fill(1, $this->daysInMonth, 0),
                'total' => 0,
            ];
        }

        $dateCol = $this->dateColumn;

        // Agregados por día/vehículo
        if ($this->countColumn) {
            // SUM(columna)
            $selectRaw = "vehicle_id, DAY($dateCol) as d, SUM({$this->countColumn}) as s";
        } else {
            // COUNT(*)
            $selectRaw = "vehicle_id, DAY($dateCol) as d, COUNT(*) as s";
        }

        $aggregates = DB::table('departures')
            ->selectRaw($selectRaw)
            ->whereBetween($dateCol, [$start, $end])
            ->groupBy('vehicle_id', 'd')
            ->get();

        foreach ($aggregates as $r) {
            $vid = (int) $r->vehicle_id;
            $d   = (int) $r->d;
            $s   = (float) $r->s;

            if (!isset($this->rows[$vid])) continue;

            // ÷2 + redondeo (half-up). El valor mostrado/guardado es ya dividido.
            $halvedRounded = (int) round($s / 2, 0, PHP_ROUND_HALF_UP);

            $this->rows[$vid]['daily'][$d] = $halvedRounded;
        }

        // Totales por fila y por día (sobre los valores ya divididos)
        foreach ($this->rows as &$row) {
            $row['total'] = array_sum($row['daily']);
            foreach ($row['daily'] as $d => $val) {
                $this->totalPerDay[$d] += $val;
            }
        }
        unset($row);

        // “Vehículos trabajados” por día: cuenta cuántos tienen valor > 0 (ya dividido)
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $worked = 0;
            foreach ($this->rows as $row) {
                if (($row['daily'][$d] ?? 0) > 0) $worked++;
            }
            $this->vehiclesWorkedPerDay[$d] = $worked;
        }
    }
}
