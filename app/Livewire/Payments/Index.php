<?php

namespace App\Livewire\Payments;

use App\Models\Headquarter;
use App\Models\Payment;
use Livewire\Component;

class Index extends Component
{
    // Filtros (mismos nombres que tu blade)
    public string $search = '';
    public string $filter = '';          // 1=Placa, 2=Usuario, 3=Serie
    public string $date_start = '';
    public string $date_end   = '';
    public string $headquarter_id = '';  // '' = todos
    public string $type = '';            // '' = todos

    public $headquarters = [];

    public function mount(){
        // fechas por defecto = hoy
        $today = now()->toDateString();
        $this->date_start = $this->date_start ?: $today;
        $this->date_end   = $this->date_end   ?: $today;

        $this->headquarters = Headquarter::where('status','active')->orderBy('name')->get(['id','name']);
    }

    public function updatedDateStart($v): void
    {
        if (empty($this->date_end)) {
            $this->date_end = $v ?: now()->toDateString();
        }
    }

    public function updatedDateEnd($v): void
    {
        if (empty($this->date_start)) {
            $this->date_start = $v ?: now()->toDateString();
        }
    }

    public function render()
    {
        $payments = Payment::query()
            ->with([
                'vehicle:id,plate',
                'user:id,name',
                'headquarter:id,name',
            ])

            // Rango de fechas por date_register
            ->when($this->date_start && $this->date_end, function ($q) {
                $q->whereBetween('date_register', [$this->date_start, $this->date_end]);
            }, function ($q) {
                // fallback por si algo viene vacío: hoy
                $today = now()->toDateString();
                $q->whereBetween('date_register', [$today, $today]);
            })

            // Sucursal exacta (opcional)
            ->when($this->headquarter_id !== '' && $this->headquarter_id !== null, function ($q) {
                $q->where('headquarter_id', $this->headquarter_id);
            })

            // Tipo exacto (opcional)
            ->when($this->type !== '', function ($q) {
                $q->where('type', $this->type);
            })

            // Búsqueda según filtro seleccionado
            ->when(trim($this->search) !== '', function ($q) {
                $term = trim($this->search);

                switch ($this->filter) {
                    // 1 = Placa (legacy o vehicle.plate)
                    case '1':
                        $plate = strtoupper($term);
                        $q->where(function ($qq) use ($plate) {
                            $qq->where('legacy_plate', 'like', '%'.$plate.'%')
                                ->orWhereHas('vehicle', fn($v) => $v->where('plate', 'like', '%'.$plate.'%'));
                        });
                        break;

                    // 2 = Usuario (users.name)
                    case '2':
                        $q->whereHas('user', function ($u) use ($term) {
                            $u->where('name', 'like', '%'.$term.'%');
                        });
                        break;

                    // 3 = Serie
                    case '3':
                        $q->where('serie', 'like', '%'.$term.'%');
                        break;

                    // Sin filtro: buscar amplio en serie, user.name, placas
                    default:
                        $plate = strtoupper($term);
                        $q->where(function ($qq) use ($term, $plate) {
                            $qq->where('serie', 'like', '%'.$term.'%')
                                ->orWhere('legacy_plate', 'like', '%'.$plate.'%')
                                ->orWhereHas('vehicle', fn($v) => $v->where('plate', 'like', '%'.$plate.'%'))
                                ->orWhereHas('user', fn($u) => $u->where('name', 'like', '%'.$term.'%'));
                        });
                        break;
                }
            })

            ->orderBy('date_register')
            ->orderBy('hour')
            ->get();



        return view('livewire.payments.index', [
            'payments'     => $payments,
            'headquarters' => $this->headquarters,
        ]);
    }
}
