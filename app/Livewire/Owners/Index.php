<?php

namespace App\Livewire\Owners;

use App\Models\Owner;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    public $owners;
    public $ownersFree;
    public $search;
    public $filter = "plate";

    public $ownerId;
    public $name;
    public $document_type = '';
    public $document_number;
    public $document_expiration_date;
    public $birthdate;
    public $address;
    public $district;
    public $email;
    public $phone;


    protected $rules = [
        'name' => 'required|string|max:255',
        'document_type' => 'required|string|max:255',
        'document_number' => 'required|string|max:255|unique:owners,document_number',
        'document_expiration_date' => 'nullable|date',
        'birthdate' => 'nullable|date',
        'address' => 'nullable|string|max:255',
        'district' => 'nullable|string|max:255',
        'email' => 'nullable|string|email|max:255',
        'phone' => 'nullable|string|max:255',
    ];

    public function mount()
    {

        $search = trim($this->search);
        // escapamos caracteres especiales de LIKE
        $like = $search === ''
            ? null
            : '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';

        $this->owners = DB::table('owners as o')
            // Trae solo placas ACTIVAS al join (si no tiene activa, v.* será NULL)
            ->leftJoin('vehicles as v', function ($join) {
                $join->on('v.owner_id', '=', 'o.id')
                    ->whereIn(DB::raw("LOWER(TRIM(v.status))"), ['active', 'activo']);
            })
            // search selectivo según $filterBy
            ->when($like !== null, function ($q) use ($like) {
                $q->when($this->filter === 'name', fn($qq) => $qq->where('o.name', 'like', $like))
                    ->when($this->filter === 'code', fn($qq) => $qq->where('o.id', 'like', $like))
                    ->when($this->filter === 'plate', fn($qq) => $qq->where('v.plate', 'like', $like));
            })
            // Si el filtro es plate y NO hay término de búsqueda,
            // tiene sentido listar solo owners que tengan al menos una placa activa:
            ->when($this->filter === 'plate' && $like === null, fn($q) => $q->whereNotNull('v.id')
            )
            ->select(
                'o.id',
                'o.name',
                'o.document_number',
                'o.phone',
                'v.plate' // puede venir NULL si el owner no tiene placa activa (LEFT JOIN)
            )
            ->orderBy('o.name')
            ->orderByRaw('v.plate IS NULL, v.plate') // NULLs al final y luego ordena por placa
            ->get();

        $this->ownersFree = DB::table('owners as o')
            ->leftJoin('vehicles as v', function ($join) {
                $join->on('v.owner_id', '=', 'o.id')
                    ->where('v.status', 'active');
            })
            ->whereNull('v.owner_id')
            ->select('o.id as id', 'o.name as name', 'o.document_number as document_number', 'o.phone as phone')
            ->orderBy('o.name')
            ->get();
    }

    public function openAddModal()
    {
        $this->reset(['name', 'document_type', 'document_number', 'document_expiration_date', 'birthdate', 'address', 'district', 'email', 'phone']);
        $this->dispatch('open-modal', ['name' => 'modalAddOwner', 'focus' => 'name']);
    }

    public function openEditModal($id){
        $owner = Owner::find($id);
        $this->ownerId = $id;
        $this->name = $owner->name;
        $this->document_type = $owner->document_type;
        $this->document_number = $owner->document_number;
        $this->document_expiration_date = optional($owner->document_expiration_date)->format('Y-m-d');
        $this->birthdate = optional($owner->birthdate)->format('Y-m-d');
        $this->address = $owner->address;
        $this->district = $owner->district;
        $this->email = $owner->email;
        $this->phone = $owner->phone;
        $this->dispatch('open-modal', ['name' => 'modalEditOwner', 'focus' => 'name']);
    }

    public function updatedFilter()
    {
        $this->mount();
    }

    public function updatedSearch()
    {
        $this->mount();
    }

    public function save(){
        $this->validate();
        Owner::create([
            "name" => $this->name,
            "document_type" => $this->document_type,
            "document_number" => $this->document_number,
            "document_expiration_date" => $this->document_expiration_date,
            "birthdate" => $this->birthdate,
            "address" => $this->address,
            "district" => $this->district,
            "email" => $this->email,
            "phone" => $this->phone,
        ]);

        $this->reset(['name','document_type','document_number','document_expiration_date','birthdate','address','district','email','phone']);
        $this->mount();
        $this->dispatch('modal-close',["name" => "modalAddOwner"]);
        $this->dispatch('successAlert',["message" => "Propietario creado correctamente"]);
    }

    public function update(){
        $this->validate([
            'name' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
            'document_number' => 'required|string|max:255|unique:owners,document_number,' .$this->ownerId,
            'document_expiration_date' => 'nullable|date',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        $owner = Owner::find($this->ownerId);
        $owner->update([
            "name" => $this->name,
            "document_type" => $this->document_type,
            "document_number" => $this->document_number,
            "document_expiration_date" => $this->document_expiration_date,
            "birthdate" => $this->birthdate,
            "address" => $this->address,
            "district" => $this->district,
            "email" => $this->email,
            "phone" => $this->phone,
        ]);

        $this->reset(['name','document_type','document_number','document_expiration_date','birthdate','address','district','email','phone']);
        $this->mount();
        $this->dispatch('modal-close',["name" => "modalEditOwner"]);
        $this->dispatch('successAlert',["message" => "Propietario actualizado correctamente"]);
    }

    public function render()
    {
        return view('livewire.owners.index');
    }

    public function export(){
        $route = route('exports.owners',["search" => $this->search, "filter" => $this->filter]);
        $this->dispatch('url-open',["url" => $route]);
    }
}
