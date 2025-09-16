<?php

namespace App\Livewire\Cash;

use App\Models\Expense;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Expenses extends Component
{
    use WithPagination;

    public $search = '';
    /** 1=A (reason), 2=Motivo (detail), 3=Usuario (user.name), 4=Respons. (in_charge) */
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
        $q = Expense::query()
            ->with(['user:id,name'])
            ->orderBy('date')
            ->orderBy('id');

        // Rango de fechas
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
            switch ((int)$this->filterType) {
                case 1: // A
                    $q->where('reason', 'like', "%{$s}%");
                    break;
                case 2: // Motivo
                    $q->where('detail', 'like', "%{$s}%");
                    break;
                case 3: // Usuario
                    $q->whereHas('user', function ($qq) use ($s) {
                        $qq->where('name', 'like', "%{$s}%");
                    });
                    break;
                case 4: // Respons.
                    $q->where('in_charge', 'like', "%{$s}%");
                    break;
                default:
                    $q->where('reason', 'like', "%{$s}%");
            }
        }

        // Totales
        $totalGeneral = (clone $q)->sum('total');

        // Paginado
        $expenses = $q->paginate(20000);
        $pageSum  = $expenses->getCollection()->sum('total');

        return view('livewire.cash.expenses', [
            'expenses'      => $expenses,
            'pageSum'       => $pageSum,
            'totalGeneral'  => $totalGeneral,
        ]);
    }


    public function openEditModal($id){
        $this->dispatch('open-modal', ['name' => 'modalEditExpense', 'focus' => 'expense']);
    }
}
