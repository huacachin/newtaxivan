<?php
// app/Livewire/CostPerPlate/Calendar.php
namespace App\Livewire\CostPerPlate;

use App\Models\CostPerPlateDay as CostPerPlateDayModel;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Calendar extends Component
{
    public ?string $plate = null;
    public ?int $vehicleId = null;
    public int $year = 0;
    public int $month = 0;

    /** 'Y-m-d' => amount (editado en memoria) */
    public array $values = [];
    /** snapshot de BD para comparar en save */
    public array $original = [];
    /** grilla de semanas */
    public array $weeks = [];

    public ?float $bulk = null;

    public function mount($plate = null, $year = null, $month = null): void
    {
        $this->plate = $plate !== null ? strtoupper(trim((string)$plate)) : null;
        $this->year  = (int)($year ?: Carbon::now('America/Lima')->year);
        $this->month = (int)($month ?: Carbon::now('America/Lima')->month);

        $this->resolveVehicleId();
        $this->buildCalendar();

        // Carga inicial desde BD (una sola vez)
        $loaded = $this->fetchValuesFromDb();
        $this->values   = $loaded;
        $this->original = $loaded;
    }

    public function render()
    {
        // NO tocar $values aquí
        return view('livewire.cost-per-plate.calendar');
    }

    private function resolveVehicleId(): void
    {
        if ($this->vehicleId) return;
        if (!$this->plate) return;

        $needle = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($this->plate)));
        $this->vehicleId = Vehicle::whereRaw(
            "REPLACE(REPLACE(REPLACE(UPPER(TRIM(plate)),'-',''),' ',''),'.','') = ?",
            [$needle]
        )->value('id');
    }

    private function buildCalendar(): void
    {
        $start = Carbon::create($this->year, $this->month, 1);
        $end   = $start->copy()->endOfMonth();
        $dow   = $start->dayOfWeekIso; // 1..7

        $weeks=[]; $week=[];
        for ($i=1; $i<$dow; $i++) $week[] = null;
        for ($d=$start->copy(); $d->lte($end); $d->addDay()) {
            $week[] = $d->toDateString(); // Y-m-d
            if (count($week) === 7) { $weeks[] = $week; $week = []; }
        }
        if ($week) { while (count($week) < 7) $week[] = null; $weeks[] = $week; }
        $this->weeks = $weeks;
    }

    private function fetchValuesFromDb(): array
    {
        // Prepopular a 0 todos los días del mes
        $vals = [];
        foreach ($this->weeks as $week) foreach ($week as $d) if ($d) $vals[$d] = 0.0;

        if (!$this->vehicleId) return $vals;

        $table = (new CostPerPlateDayModel)->getTable();
        $rows = DB::table($table)
            ->selectRaw('`date`, SUM(amount) AS amount')
            ->where('vehicle_id', $this->vehicleId)
            ->where('year',  $this->year)
            ->where('month', $this->month)
            ->groupBy('date')
            ->pluck('amount', 'date');

        foreach ($rows as $date => $amount) $vals[$date] = (float)$amount;
        return $vals;
    }

    /** Aplica monto a todo el mes en memoria */
    public function fillAll(): void
    {
        if ($this->bulk === null) return;
        $v = (float)$this->bulk;
        foreach ($this->weeks as $week) {
            foreach ($week as $date) if ($date) $this->values[$date] = $v;
        }
    }

    public function goBack()
    {
        $this->dispatch('go-back', ["fallback" => route('settings.cost-per-plate.cost-per-plate-day',["year" => $this->year, "month" => $this->month])]);;
    }

    /** Guarda SOLO los días que cambiaron vs $original */
    public function saveAll(): void
    {
        if (!$this->vehicleId) return;

        $table = (new \App\Models\CostPerPlateDay)->getTable();

        // Construimos la lista de días QUE CAMBIARON vs $original
        $changes = [];
        foreach ($this->values as $date => $newVal) {
            // Valida clave fecha
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;

            // Ignora nulos/vacíos (evita escribir 0 por accidente)
            if ($newVal === '' || $newVal === null) continue;

            $new = round((float)$newVal, 2);
            $old = round((float)($this->original[$date] ?? 0.0), 2);
            if ($new === $old) continue; // no cambió → no tocar

            $changes[$date] = $new;
        }

        if (empty($changes)) {
            $this->dispatch('toast', body: 'No hubo cambios');
            return;
        }

        \DB::transaction(function () use ($table, $changes) {
            foreach ($changes as $date => $amount) {
                $dt = \Carbon\Carbon::parse($date);

                // 1) UPDATE por (vehicle_id, date)
                $affected = \DB::table($table)
                    ->where('vehicle_id', $this->vehicleId)
                    ->whereDate('date', $date)
                    ->update([
                        'year'       => $dt->year,
                        'month'      => $dt->month,
                        'amount'     => $amount,
                        'updated_at' => now(),
                    ]);

                // 2) Si no existía, INSERT
                if ($affected === 0) {
                    \DB::table($table)->insert([
                        'vehicle_id' => $this->vehicleId,
                        'year'       => $dt->year,
                        'month'      => $dt->month,
                        'date'       => $date,
                        'amount'     => $amount,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        // Refresca snapshot para que no se vuelvan a escribir en la próxima
        $this->original = $this->fetchValuesFromDb();
        $this->values   = $this->original;

        $this->dispatch('successAlert', ["message" => 'Guardado']);
    }
}
