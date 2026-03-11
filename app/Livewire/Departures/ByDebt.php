<?php

namespace App\Livewire\Departures;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ByDebt extends Component
{
    public ?string $plate = null;
    public ?string $date  = null;

    protected $queryString = [
        'plate' => ['except' => null],
        'date'  => ['except' => null],
    ];

    public function render(): View
    {
        $rows       = collect();
        $grouped    = [];
        $userTotals = [];
        $totalSalidas = 0;

        if ($this->plate && $this->date) {
            // Agrupar por usuario + sucursal (si mismo usuario misma sucursal = 1 fila)
            $rows = DB::table('departures as d')
                ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
                ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
                ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
                ->where('v.plate', $this->plate)
                ->whereDate('d.date', $this->date)
                ->selectRaw("
                    u.name as user_name,
                    h.name as headquarter_name,
                    MIN(d.hour) as hour,
                    COUNT(*) as salidas
                ")
                ->groupBy('u.name', 'h.name')
                ->orderBy('u.name')
                ->orderBy('h.name')
                ->get();

            // Agrupar por usuario para T.S (rowspan)
            foreach ($rows as $r) {
                $user = $r->user_name ?? '-';
                if (!isset($grouped[$user])) {
                    $grouped[$user] = [];
                    $userTotals[$user] = 0;
                }
                $grouped[$user][] = $r;
                $userTotals[$user] += $r->salidas;
                $totalSalidas += $r->salidas;
            }
        }

        return view('livewire.departures.by-debt', [
            'grouped'      => $grouped,
            'userTotals'   => $userTotals,
            'totalSalidas' => $totalSalidas,
        ]);
    }
}
