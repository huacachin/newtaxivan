<?php
// app/Livewire/Vehicles/Index.php
namespace App\Livewire\Vehicles;

use App\Models\Driver;
use App\Models\Headquarter;
use App\Models\Owner;
use App\Models\Vehicle;
use Livewire\Component;
use Livewire\Attributes\Url;

class Index extends Component
{
    public $vehicles;
    public $owners;
    public $drivers;
    public $listDrivers;
    public $listOwners;
    public $listHeadquarters;

    #[Url(except: '')]       public $search = '';
    #[Url(except: 'plate')]  public $filter = 'plate';
    #[Url(except: 'active')] public $status = 'active';

    public function export(){
        $route = route('exports.vehicles',[
            "search" => $this->search,
            "filter" => $this->filter,
            "status" => $this->status
        ]);
        $this->dispatch('url-open',["url" => $route]);
    }

    public function mount()
    {
        $search = trim($this->search);
        $filter = $this->filter;
        $status = strtolower(trim($this->status));

        $this->listOwners = Owner::all();
        $this->listDrivers = Driver::all();
        $this->listHeadquarters = Headquarter::all();

        if (!in_array($filter, ['plate','brand','year','owner','driver','condition','company','category','code'], true)) {
            $filter = $this->filter = 'plate';
        }
        if (!in_array($status, ['active','inactive'], true)) {
            $status = $this->status = 'active';
        }

        $query = Vehicle::query()
            ->when(in_array($status, ['active', 'inactive'], true),
                fn($q) => $q->whereRaw('LOWER(TRIM(status)) = ?', [$status])
            )
            ->when($status === 'inactive',
                fn($q) => $q->whereNotNull('termination_date')
            )
            ->when($search !== '' && $filter !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->where('plate', 'like', "%{$search}%"),
                    'brand' => $q->where('brand', 'like', "%{$search}%"),
                    'category' => $q->where('class', 'like', "%{$search}%"),
                    'year' => ctype_digit($search)
                        ? $q->where('year', (int)$search)
                        : $q->where('year', 'like', "%{$search}%"),
                    'owner' => $q->whereHas('owner', fn($r) => $r->where('name', 'like', "%{$search}%")),
                    'driver' => $q->whereHas('driver', fn($r) => $r->where('name', 'like', "%{$search}%")),
                    'condition' => $q->where('condition', 'like', "%{$search}%"),
                    'company' => $q->where('affiliated_company', 'like', "%{$search}%"),
                    'code' => $q->where('id', $search),
                    default => $q,
                };
            })
            ->orderBy('sort_order')
            ->with(['owner:id,name','driver:id,name']);

        $this->vehicles = $query->get([
            'sort_order','id','owner_id','driver_id','plate','status','year','condition',
            'affiliated_company','termination_date','brand','class','type','fuel',
            'soat_date','certificate_date','technical_review','detail'
        ]);

        $this->totals();
    }

    public function openAddWindow(): void
    {
        $route = route('settings.vehicles.create');;

        $this->dispatch('url-open',["url" => $route]);
    }

    public function openEditWindow(int $id): void
    {
        $route = route('settings.vehicles.edit',["id" => $id]);
        $this->dispatch('url-open',["url" => $route]);
    }

    public function updatedSearch() { $this->mount(); }
    public function updatedFilter() { $this->mount(); }
    public function updatedStatus() { $this->mount(); }

    public function totals(): void
    {
        $this->owners = Vehicle::query()
            ->whereRaw('LOWER(TRIM(vehicles.status)) = ?', ['active'])
            ->whereNotNull('owner_id')
            ->join('owners', 'vehicles.owner_id', '=', 'owners.id')
            ->distinct('owners.document_number')
            ->count('owners.document_number');

        $this->drivers = Vehicle::query()
            ->whereRaw('LOWER(TRIM(vehicles.status)) = ?', ['active'])
            ->whereNotNull('driver_id')
            ->join('drivers', 'vehicles.driver_id', '=', 'drivers.id')
            ->distinct('drivers.document_number')
            ->count('drivers.document_number');
    }

    public function render()
    {
        return view('livewire.vehicles.index');
    }
}
