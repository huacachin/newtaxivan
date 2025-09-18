<?php

namespace App\Livewire\Drivers;

use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{

    public $drivers;
    public $driversFree;

    public $search;
    public $filter = "plate";

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
        $this->dispatch('successAlert',["message" => "Conductor creado correctamente"]);

    }

    public function mount()
    {

        $filter = $this->filter;
        $search = $this->search;

        $this->drivers = Driver::query()
            ->whereHas('vehicles', fn($q) => $q->whereRaw("LOWER(TRIM(status)) = 'active'") // o 'activo'
            )
            ->with(['vehicles' => fn($q) => $q->whereRaw("LOWER(TRIM(status)) = 'active'")
                ->select('id', 'driver_id', 'plate', 'status')
            ])
            ->when($filter && $search, function ($query) use ($filter, $search) {
                $search = trim($search);

                if ($filter === 'plate') {
                    // Buscar por placa en la relación
                    $query->whereHas('vehicles', fn($q) => $q->where('plate', 'like', "%{$search}%")
                    );
                } elseif ($filter === 'name') {
                    // Buscar por nombre del driver
                    $query->where('name', 'like', "%{$search}%");
                } elseif ($filter === 'code') {
                    // Buscar por nombre del driver
                    $query->whereHas('vehicles', fn($q) => $q->where('id', $search)
                    );
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'document_number', 'phone', 'contract_start', 'contract_end', 'condition']);

        $this->driversFree = Driver::whereDoesntHave('vehicles', function ($q) {
            $q->whereRaw("LOWER(TRIM(status)) = 'active'"); // o 'activo'
        })->get();

    }

    public function updatedFilter()
    {
        $this->mount();
    }

    public function updatedSearch()
    {
        $this->mount();
    }

    public function openAddModal(){
        $this->reset([
            'name',
            'document_number',
            'document_expiration_date',
            'birthdate',
            'district',
            'condition',
            'address',
            'phone',
            'email',
            'license',
            'class',
            'category',
            'license_issue_date',
            'license_revalidation_date',
            'contract_start',
            'contract_end',
            'score',
            'credential',
            'credential_expiration_date',
            'credential_municipality',
            'driverId'
        ]);
        $this->dispatch('open-modal', ['name' => 'modalAddDriver', 'focus' => 'name']);
    }

    public function openEditModal($id){
        $driver = Driver::find($id);
        $this->driverId = $id;
        $this->name = $driver->name;
        $this->document_number = $driver->document_number;
        $this->address = $driver->address;
        $this->district = $driver->district;
        $this->email = $driver->email;
        $this->phone = $driver->phone;
        $this->license = $driver->license;
        $this->class = $driver->class;
        $this->category = $driver->category;
        $this->condition = $driver->condition;

        $this->document_expiration_date   = optional($driver->document_expiration_date)->format('Y-m-d');
        $this->birthdate                  = optional($driver->birthdate)->format('Y-m-d');
        $this->license_issue_date         = optional($driver->license_issue_date)->format('Y-m-d');
        $this->license_revalidation_date  = optional($driver->license_revalidation_date)->format('Y-m-d');
        $this->contract_start             = optional($driver->contract_start)->format('Y-m-d');
        $this->contract_end               = optional($driver->contract_end)->format('Y-m-d');
        $this->credential                 = optional($driver->credential)->format('Y-m-d');
        $this->credential_expiration_date = optional($driver->credential_expiration_date)->format('Y-m-d');

        $this->score = $driver->score;
        $this->credential_municipality = $driver->credential_municipality;

        $this->dispatch('open-modal', ['name' => 'modalEditDriver', 'focus' => 'name']);
    }

    public function update(){
        $this->validate([
            'name' => 'required|string|max:255',
            'document_number' => [
                'required','string','max:255',
                Rule::unique('drivers', 'document_number')->ignore($this->driverId)
            ],
            // Si quieres ser estricto, añade date_format:Y-m-d a las fechas
            'document_expiration_date'   => ['nullable','date','date_format:Y-m-d'],
            'birthdate'                  => ['nullable','date','date_format:Y-m-d'],
            'license_issue_date'         => ['nullable','date','date_format:Y-m-d'],
            'license_revalidation_date'  => ['nullable','date','date_format:Y-m-d'],
            'contract_start'             => ['nullable','date','date_format:Y-m-d'],
            'contract_end'               => ['nullable','date','date_format:Y-m-d'],
            'credential'                 => ['nullable','date','date_format:Y-m-d'],
            'credential_expiration_date' => ['nullable','date','date_format:Y-m-d'],
            'address' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        $driver = Driver::findOrFail($this->driverId);
        $driver->update([
            "name" => $this->name,
            "document_number" => $this->document_number,
            "document_expiration_date" => $this->document_expiration_date,   // string Y-m-d
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
            "score"=> $this->score ?? "0",
            "credential"=>$this->credential,
            "credential_expiration_date"=>$this->credential_expiration_date,
            "credential_municipality"=>$this->credential_municipality,
        ]);

        $this->mount();

        $this->dispatch('modal-close',["name" => "modalEditDriver"]);
        $this->dispatch('successAlert',["message" => "Conductor actualizado correctamente"]);
    }


    public function render()
    {
        return view('livewire.drivers.index');
    }

    public function export(){
        $route = route('exports.drivers',["search" => $this->search, "filter" => $this->filter]);
        $this->dispatch('url-open',["url" => $route]);
    }
}
