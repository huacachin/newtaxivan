<?php

namespace App\Livewire\Owners;

use App\Models\Owner;
use App\Models\OwnerImage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    public $owners;
    public $ownersFree;
    public array $ownersImages = []; // owner_id => [url, ...]
    public $search;
    public $filter = "plate";

    #[Url(except: 'active')] public $status = 'active';

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


    protected $validationAttributes = [
        'document_type'   => 'tipo de documento',
        'document_number' => 'documento de identidad',
    ];

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

        $status = strtolower(trim((string) $this->status));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = $this->status = 'active';
        }

        if ($status === 'inactive') {
            $this->owners = DB::table('owners as o')
                ->whereRaw("LOWER(TRIM(o.status)) = 'inactive'")
                ->when($like !== null, function ($q) use ($like) {
                    $q->where('o.name', 'like', $like);
                })
                ->select(
                    'o.id',
                    DB::raw('NULL as sort_order'),
                    'o.name',
                    'o.document_number',
                    'o.document_expiration_date',
                    'o.phone',
                    DB::raw('NULL as plate')
                )
                ->orderBy('o.name')
                ->get();

            $this->ownersFree = collect();

            $ownerIds = $this->owners->pluck('id')->unique()->all();

            $this->ownersImages = OwnerImage::whereIn('owner_id', $ownerIds)
                ->get(['owner_id', 'image_path'])
                ->groupBy('owner_id')
                ->map(fn ($rows) => $rows->map(fn ($r) => asset('storage/' . $r->image_path))->values()->all())
                ->all();

            return;
        }

        $this->owners = DB::table('owners as o')
            // Trae solo placas ACTIVAS al join (si no tiene activa, v.* será NULL)
            ->leftJoin('vehicles as v', function ($join) {
                $join->on('v.owner_id', '=', 'o.id')
                    ->whereIn(DB::raw("LOWER(TRIM(v.status))"), ['active', 'activo']);
            })
            ->whereRaw("LOWER(TRIM(o.status)) = 'active'")
            // search selectivo según $filterBy
            ->when($like !== null, function ($q) use ($like, $search) {
                $q->when($this->filter === 'name', fn($qq) => $qq->where('o.name', 'like', $like))
                    ->when($this->filter === 'code', fn($qq) => $qq->where('v.sort_order', '=', $search))
                    ->when($this->filter === 'plate', fn($qq) => $qq->where('v.plate', 'like', $like));
            })
            // Solo owners que tengan al menos una placa activa
            ->whereNotNull('v.id')
            ->select(
                'o.id',
                'v.sort_order',
                'o.name',
                'o.document_number',
                'o.document_expiration_date', // <-- NUEVO
                'o.phone',
                'v.plate' // puede venir NULL si el owner no tiene placa activa (LEFT JOIN)
            )
            ->orderBy('v.sort_order', 'asc')
            ->orderByRaw('v.plate IS NULL, v.plate') // NULLs al final y luego ordena por placa
            ->get();

        $this->ownersFree = DB::table('owners as o')
            ->leftJoin('vehicles as v', function ($join) {
                $join->on('v.owner_id', '=', 'o.id')
                    ->where('v.status', 'active');
            })
            ->whereRaw("LOWER(TRIM(o.status)) = 'active'")
            ->whereNull('v.owner_id')
            ->select(
                'o.id as id',
                'o.name as name',
                'o.document_number as document_number',
                'o.document_expiration_date as document_expiration_date', // <-- NUEVO
                'o.phone as phone'
            )
            ->orderBy('o.name')
            ->get();

        $ownerIds = $this->owners->pluck('id')
            ->merge($this->ownersFree->pluck('id'))
            ->unique()
            ->all();

        $this->ownersImages = OwnerImage::whereIn('owner_id', $ownerIds)
            ->get(['owner_id', 'image_path'])
            ->groupBy('owner_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => asset('storage/' . $r->image_path))->values()->all())
            ->all();
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
        $owner = Owner::find($id);
        if (!$owner) {
            return;
        }
        $this->dispatch('questionActivate', [
            'id'   => $id,
            'role' => 'propietario',
            'name' => $owner->name,
        ]);
    }

    #[On('register_activate')]
    public function activate(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director', 'gerente', 'administrador')) {
            abort(403);
        }
        Owner::findOrFail($id)->update(['status' => 'active']);
        $this->mount();
        $this->dispatch('successAlert', ['message' => 'Propietario activado correctamente.']);
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
            'document_number' => 'required|string|max:255|unique:owners,document_number,' . $this->ownerId,
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

    public function openAddWindow(): void
    {
        $route = route('settings.owners.create');;;
        $this->dispatch('url-open',["url" => $route]);
    }

    public function openEditWindow(int $id): void
    {
        $route = route('settings.owners.edit',["id" => $id]);
        $this->dispatch('url-open',["url" => $route]);
    }

    public function export(){
        $route = route('exports.owners',[
            "search" => $this->search,
            "filter" => $this->filter,
            "status" => $this->status,
        ]);
        $this->dispatch('url-open',["url" => $route]);
    }
}
