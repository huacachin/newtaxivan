<?php

namespace App\Livewire\Drivers;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{

    public $drivers;
    public $driversFree;

    public $search;
    public $filter = "plate";

    #[Url(except: 'active')] public $status = 'active';

    //Formulario
    public $driverId;
    public $name;
    public $document_number;
    public $document_expiration_date;
    public $document_expiration_date_dos;
    public $birthdate;
    public $address;
    public $district;
    public $email;
    public $phone;
    public $license;
    public $class;
    public $category;
    public $license_issue_date;
    public $license_revalidation_date;
    public $contract_start;
    public $contract_end;
    public $condition;
    public $score;
    public $credential;
    public $credential_expiration_date;
    public $credential_municipality;

    protected $validationAttributes = [
        'document_number' => 'documento de identidad',
    ];

    protected $rules = [
        'name' => 'required|string|max:255',
        'document_number' => 'required|string|max:255|unique:drivers,document_number',
        'document_expiration_date' => 'nullable|date',
        'birthdate' => 'nullable|date',
        'address' => 'nullable|string|max:255',
        'district' => 'nullable|string|max:255',
        'email' => 'nullable|string|email|max:255',
        'phone' => 'nullable|string|max:255',
        'license' => 'nullable|string|max:255',
        'class' => 'nullable|string|max:255',
        'category' => 'nullable|string|max:255',
        'license_issue_date' => 'nullable|date',
        'license_revalidation_date' => 'nullable|date',
        'contract_start' => 'nullable|date',
        'contract_end' => 'nullable|date',
        'condition' => 'nullable|string|max:255',
        'score' => 'nullable|numeric|between:0,100',
        'credential' => 'nullable|string|max:255',
        'credential_expiration_date' => 'nullable|date',
        'credential_municipality' => 'nullable|string|max:255',
    ];

    public function save(){
        $this->validate();
        $driver = Driver::create([
            "name" => $this->name,
            "document_number" => $this->document_number,
            "document_expiration_date" => $this->document_expiration_date,
            "birthdate" => $this->birthdate,
            "address" => $this->address,
            "district" => $this->district,
            "email" => $this->email,
            "phone" => $this->phone,
            "license"=>$this->license,
            "class"=>$this->class,
            "category"=>$this->category,
            "license_issue_date"=>$this->license_issue_date,
            "license_revalidation_date"=>$this->license_revalidation_date,
            "contract_start"=>$this->contract_start,
            "contract_end"=>$this->contract_end,
            "condition"=>$this->condition,
            "score"=>$this->score ?? "0",
            "credential"=>$this->credential,
            "credential_expiration_date"=>$this->credential_expiration_date,
            "credential_municipality"=>$this->credential_municipality
        ]);

        $this->reset();
        $this->mount();
        $this->dispatch('modal-close',["name" => "modalAddDriver"]);
        $this->dispatch('successAlert',["message" => "Conductor creado correctamente."]);

    }

    public function mount()
    {

        $filter = $this->filter;
        $search = $this->search;
        $status = strtolower(trim((string) $this->status));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = $this->status = 'active';
        }

        if ($status === 'inactive') {
            $this->drivers = Driver::query()
                ->whereRaw("LOWER(TRIM(drivers.status)) = 'inactive'")
                ->select('id', 'name', 'document_number', 'phone', 'contract_start', 'contract_end', 'condition', 'document_expiration_date')
                ->with(['images:id,driver_id,image_path'])
                ->when($filter && $search, function ($query) use ($filter, $search) {
                    $search = trim($search);
                    if ($filter === 'name') {
                        $query->where('name', 'like', "%{$search}%");
                    } elseif ($filter === 'plate' || $filter === 'code') {
                        // Inactivos no tienen vehiculo activo asociado; buscamos por nombre como fallback.
                        $query->where('name', 'like', "%{$search}%");
                    }
                })
                ->orderBy('name', 'asc')
                ->get();

            $this->driversFree = collect();
            return;
        }

        $this->drivers = Driver::query()
            // solo drivers activos
            ->whereRaw("LOWER(TRIM(drivers.status)) = 'active'")

            // columnas del driver
            ->select('id', 'name', 'document_number', 'phone', 'contract_start', 'contract_end', 'condition', 'document_expiration_date')

            // subquery para obtener el sort_order del vehículo activo
            ->addSelect([
                'vehicle_sort_order' => Vehicle::select('sort_order')
                    ->whereColumn('vehicles.driver_id', 'drivers.id')
                    ->whereRaw("LOWER(TRIM(status)) = 'active'")
                    ->orderBy('sort_order')
                    ->limit(1),
            ])

            // solo drivers con vehículos activos
            ->whereHas('vehicles', fn ($q) =>
                $q->whereRaw("LOWER(TRIM(status)) = 'active'")
            )

            // eager load de vehículos activos + imágenes
            ->with([
                'vehicles' => fn ($q) => $q->whereRaw("LOWER(TRIM(status)) = 'active'")
                    ->select('id', 'driver_id', 'plate', 'status', 'sort_order')
                    ->orderBy('sort_order', 'asc'),
                'images:id,driver_id,image_path',
            ])

            // filtros
            ->when($filter && $search, function ($query) use ($filter, $search) {
                $search = trim($search);

                if ($filter === 'plate') {
                    $query->whereHas('vehicles', fn ($q) =>
                    $q->where('plate', 'like', "%{$search}%")
                    );
                } elseif ($filter === 'name') {
                    $query->where('name', 'like', "%{$search}%");
                } elseif ($filter === 'code') {
                    $query->whereHas('vehicles', fn ($q) =>
                    $q->where('sort_order', $search)
                    );
                }
            })

            // ordenar por el sort_order del vehículo activo
            ->orderBy('vehicle_sort_order', 'asc')
            ->get();

        $this->driversFree = Driver::whereRaw("LOWER(TRIM(status)) = 'active'")
            ->whereDoesntHave('vehicles', function ($q) {
                $q->whereRaw("LOWER(TRIM(status)) = 'active'");
            })
            ->with(['images:id,driver_id,image_path'])
            ->get();

    }

    public function updatedFilter()
    {
        $this->mount();
    }

    public function updatedSearch()
    {
        $this->mount();
    }

    public function updatedStatus()
    {
        $this->mount();
    }

    public function questionActivate(int $id): void
    {
        $driver = Driver::find($id);
        if (!$driver) {
            return;
        }
        $this->dispatch('questionActivate', [
            'id'   => $id,
            'role' => 'conductor',
            'name' => $driver->name,
        ]);
    }

    #[On('register_activate')]
    public function activate(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director', 'gerente', 'administrador')) {
            abort(403);
        }
        Driver::findOrFail($id)->update(['status' => 'active']);
        $this->mount();
        $this->dispatch('successAlert', ['message' => 'Conductor activado correctamente.']);
    }

    public function openAddWindow(): void
    {
        $route = route('settings.drivers.create');;

        $this->dispatch('url-open',["url" => $route]);
    }

    public function openEditWindow(int $id): void
    {
        $route = route('settings.drivers.edit',["id" => $id]);
        $this->dispatch('url-open',["url" => $route]);
    }


    public function render()
    {
        return view('livewire.drivers.index');
    }

    public function export(){
        $route = route('exports.drivers',[
            "search" => $this->search,
            "filter" => $this->filter,
            "status" => $this->status,
        ]);
        $this->dispatch('url-open',["url" => $route]);
    }
}
