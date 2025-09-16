<?php

namespace App\Livewire\Cash;

use App\Models\Income;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Incomes extends Component
{
    use WithPagination;

    public $search = '';
    /** 1=A (reason), 2=Motivo (detail), 3=Usuario (user.name) */
    public $filterType = 1;

    public $date_start;
    public $date_end;

    public $page = 1;

    protected $queryString = [
        'search'     => ['except' => ''],
        'filterType' => ['except' => 1],
        'date_start' => ['except' => null],
        'date_end'   => ['except' => null],
        'page'       => ['except' => 1],
    ];

    public function mount(): void
    {
        $today = Carbon::today()->toDateString();
        $this->date_start = $this->date_start ?: $today;
        $this->date_end   = $this->date_end   ?: $today;
    }

    public function render()
    {
        $q = Income::query()
            ->with(['user:id,name'])
            ->orderBy('date')
            ->orderBy('id');

        // Filtro por rango de fechas
        if ($this->date_start && $this->date_end) {
            $q->whereBetween('date', [$this->date_start, $this->date_end]);
        } elseif ($this->date_start) {
            $q->where('date', '>=', $this->date_start);
        } elseif ($this->date_end) {
            $q->where('date', '<=', $this->date_end);
        }

        // Búsqueda
        $s = trim((string)$this->search);
        if ($s !== '') {
            if ($this->filterType == 1) {
                $q->where('reason', 'like', "%{$s}%");
            } elseif ($this->filterType == 2) {
                $q->where('detail', 'like', "%{$s}%");
            } elseif ($this->filterType == 3) {
                $q->whereHas('user', function ($qq) use ($s) {
                    $qq->where('name', 'like', "%{$s}%");
                });
            } else {
                $q->where('reason', 'like', "%{$s}%");
            }
        }

        // Totales
        $totalGeneral = (clone $q)->sum('total');

        // Paginado
        $incomes = $q->paginate(20000);
        $pageSum = $incomes->getCollection()->sum('total');

        return view('livewire.cash.incomes', [
            'incomes'      => $incomes,
            'pageSum'      => $pageSum,
            'totalGeneral' => $totalGeneral,
        ]);
    }

    public function openEditModal($id){
        $this->dispatch('open-modal', ['name' => 'modalEditIncome', 'focus' => 'income']);
    }
}
