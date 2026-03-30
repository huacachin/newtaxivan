<?php
// app/Livewire/CostPerPlate/Calendar.php
namespace App\Livewire\CostPerPlate;

use App\Models\CostPerPlateDay as CostPerPlateDayModel;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
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
        $order = $this->vehicleId
            ? Vehicle::where('id', $this->vehicleId)->value('sort_order') ?? ''
            : '';
        return view('livewire.cost-per-plate.calendar', compact('order'));
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

        // Domingos siempre 0
        foreach ($vals as $date => $val) {
            if (Carbon::parse($date)->isSunday()) {
                $vals[$date] = 0.0;
            }
        }

        return $vals;
    }

    public function confirmChange(string $date, $value): void
    {
        $value = (float) $value;
        $this->dispatch('confirmCostChange', ['date' => $date, 'value' => $value]);
    }

    #[On('applySingleFromJs')]
    public function applySingle(string $date, float $value): void
    {
        if (!$this->vehicleId) return;
        $this->saveDay($date, $value);
        $this->refreshValues();
        $this->dispatch('successAlert', ['message' => 'Guardado']);
    }

    #[On('applyForwardFromJs')]
    public function applyForward(string $date, float $value): void
    {
        if (!$this->vehicleId) return;

        $from = Carbon::parse($date);
        $end  = Carbon::create($this->year, $this->month, 1)->endOfMonth();

        for ($d = $from->copy(); $d->lte($end); $d->addDay()) {
            if ($d->isSunday()) continue;
            $this->saveDay($d->toDateString(), $value);
        }

        $this->refreshValues();
        $this->dispatch('successAlert', ['message' => 'Guardado']);
    }

    private function saveDay(string $date, float $amount): void
    {
        $table = (new CostPerPlateDayModel)->getTable();
        $dt = Carbon::parse($date);

        $affected = DB::table($table)
            ->where('vehicle_id', $this->vehicleId)
            ->whereDate('date', $date)
            ->update([
                'amount'     => round($amount, 2),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            DB::table($table)->insert([
                'vehicle_id' => $this->vehicleId,
                'year'       => $dt->year,
                'month'      => $dt->month,
                'date'       => $date,
                'amount'     => round($amount, 2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function refreshValues(): void
    {
        $loaded = $this->fetchValuesFromDb();
        $this->values   = $loaded;
        $this->original = $loaded;
    }

    public function goBack()
    {
        $this->redirect(route('settings.cost-per-plate.cost-per-plate-day', ["year" => $this->year, "month" => $this->month]));
    }
}
