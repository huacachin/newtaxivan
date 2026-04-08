<?php
// app/Livewire/Owners/Create.php
namespace App\Livewire\Owners;

use App\Models\Owner;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

class Create extends Component
{
    public $name;
    public $document_type = '';
    public $document_number;
    public ?int $reactivateId = null;
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

    public function rules()
    {
        $uniqueRule = $this->reactivateId
            ? 'unique:owners,document_number,' . $this->reactivateId
            : 'unique:owners,document_number';

        return [
            'name'                     => 'required|string|max:255',
            'document_type'            => 'required|string|max:255',
            'document_number'          => 'required|string|max:255|' . $uniqueRule,
            'document_expiration_date' => 'nullable|date',
            'birthdate'                => 'nullable|date',
            'address'                  => 'nullable|string|max:255',
            'district'                 => 'nullable|string|max:255',
            'email'                    => 'nullable|string|email|max:255',
            'phone'                    => 'nullable|string|max:255',
        ];
    }

    public function mount(){
        $today = Carbon::today()->toDateString();
        $this->birthdate = $today;
        $this->document_expiration_date = $today;
    }

    public function checkDocumentNumber(): void
    {
        $docNumber = trim($this->document_number ?? '');
        if ($docNumber === '') {
            return;
        }

        $inactive = Owner::where('document_number', $docNumber)
            ->where('status', 'inactive')
            ->first();

        if ($inactive) {
            $this->dispatch('confirmReactivate', [
                'id'    => $inactive->id,
                'name'  => $inactive->name,
                'entity' => 'Propietario',
            ]);
        }
    }

    #[On('reactivateConfirmed')]
    public function reactivateConfirmed(int $id): void
    {
        $owner = Owner::where('id', $id)->where('status', 'inactive')->first();
        if (!$owner) {
            return;
        }

        $this->reactivateId = $owner->id;
        $this->name = $owner->name;
        $this->document_type = $owner->document_type;
        $this->document_number = $owner->document_number;
        $this->document_expiration_date = optional($owner->document_expiration_date)->format('Y-m-d');
        $this->birthdate = optional($owner->birthdate)->format('Y-m-d');
        $this->address = $owner->address;
        $this->district = $owner->district;
        $this->email = $owner->email;
        $this->phone = $owner->phone;

        $this->dispatch('successAlert', ['message' => 'Datos del propietario cargados. Puede editarlos antes de guardar.']);
    }

    public function save(): void
    {
        try {
            $this->validate();

            $payload = [
                'name'                     => $this->name,
                'document_type'            => $this->document_type,
                'document_number'          => $this->document_number,
                'document_expiration_date' => $this->document_expiration_date,
                'birthdate'                => $this->birthdate,
                'address'                  => $this->address,
                'district'                 => $this->district,
                'email'                    => $this->email,
                'phone'                    => $this->phone,
            ];

            if ($this->reactivateId) {
                $owner = Owner::findOrFail($this->reactivateId);
                $payload['status'] = 'active';
                $owner->update($payload);
                session()->flash('owner_success', 'Propietario reactivado correctamente.');
            } else {
                $payload['status'] = 'active';
                Owner::create($payload);
                session()->flash('owner_success', 'Propietario creado correctamente.');
            }

            $this->redirectRoute('settings.owners.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('owner_error', 'Error al guardar: ' . $e->getMessage());
            $this->redirectRoute('settings.owners.index');
        }
    }

    public function clean(): void
    {
        $this->reset(['name', 'document_type', 'document_number', 'document_expiration_date', 'birthdate', 'address', 'district', 'email', 'phone', 'reactivateId']);
        $this->mount();
    }

    public function render()
    {
        return view('livewire.owners.create');
    }
}
