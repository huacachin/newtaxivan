<?php
// app/Livewire/Owners/Create.php
namespace App\Livewire\Owners;

use App\Models\Owner;
use App\Models\OwnerImage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

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
    public $new_images = [];
    public $image_files = [];

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
            'new_images'               => 'nullable|array|max:10',
            'new_images.*'             => 'image|max:5120',
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

            DB::transaction(function () use ($payload) {
                if ($this->reactivateId) {
                    $owner = Owner::findOrFail($this->reactivateId);
                    $payload['status'] = 'active';
                    $owner->update($payload);
                } else {
                    $payload['status'] = 'active';
                    $owner = Owner::create($payload);
                }

                foreach ($this->image_files as $file) {
                    $path = $file->storePublicly('owners', 'public');
                    OwnerImage::create([
                        'owner_id'   => $owner->id,
                        'image_path' => $path,
                    ]);
                }
            });

            session()->flash('owner_success', $this->reactivateId
                ? 'Propietario reactivado correctamente.'
                : 'Propietario creado correctamente.');

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
        $this->reset(['name', 'document_type', 'document_number', 'document_expiration_date', 'birthdate', 'address', 'district', 'email', 'phone', 'reactivateId', 'new_images', 'image_files']);
        $this->mount();
    }

    public function updatedNewImages(): void
    {
        foreach ($this->new_images as $file) {
            $this->image_files[] = $file;
        }
        $this->new_images = [];
    }

    public function removeNewImage(int $index): void
    {
        array_splice($this->image_files, $index, 1);
    }

    public function render()
    {
        return view('livewire.owners.create');
    }
}
