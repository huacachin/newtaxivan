<?php
// app/Livewire/Drivers/Create.php
namespace App\Livewire\Drivers;

use App\Models\Driver;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public $name;
    public $document_number;
    public ?int $reactivateId = null;
    public $document_expiration_date;
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
    public $road_education;
    public $road_education_expiration_date;
    public $road_education_municipality;
    public $credential; // fecha
    public $credential_expiration_date;
    public $credential_municipality;
    public $details;
    public $image_file;

    public $age;

    protected $validationAttributes = [
        'document_number' => 'documento de identidad',
    ];

    public function rules()
    {
        $uniqueRule = $this->reactivateId
            ? 'unique:drivers,document_number,' . $this->reactivateId
            : 'unique:drivers,document_number';

        return [
            'name' => 'required|string|max:255',
            'document_number' => 'required|string|max:255|' . $uniqueRule,
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
            'condition' => 'required|string|max:255',
            'score' => 'nullable|numeric|between:0,100',
            'road_education' => 'nullable|date',
            'road_education_expiration_date' => 'nullable|date',
            'road_education_municipality' => 'nullable|string|max:255',
            'credential' => 'nullable|date',
            'credential_expiration_date' => 'nullable|date',
            'credential_municipality' => 'nullable|string|max:255',
            'details' => 'nullable|string|max:1000',
            'image_file' => 'nullable|image|max:3072',
        ];
    }

    public function mount(){
        $today = Carbon::today()->toDateString();
        $this->birthdate = $today;
        $this->age = $this->calculateAge($this->birthdate);
        $this->document_expiration_date = $today;
        $this->license_issue_date = $today;
        $this->license_revalidation_date = $today;
        $this->contract_start = $today;
        $this->contract_end = $today;
        $this->credential = $today;
        $this->credential_expiration_date = $today;
    }

    public function checkDocumentNumber(): void
    {
        $docNumber = trim($this->document_number ?? '');
        if ($docNumber === '') {
            return;
        }

        $inactive = Driver::where('document_number', $docNumber)
            ->where('status', 'inactive')
            ->first();

        if ($inactive) {
            $this->dispatch('confirmReactivate', [
                'id'    => $inactive->id,
                'name'  => $inactive->name,
                'entity' => 'Conductor',
            ]);
        }
    }

    #[On('reactivateConfirmed')]
    public function reactivateConfirmed(int $id): void
    {
        $driver = Driver::where('id', $id)->where('status', 'inactive')->first();
        if (!$driver) {
            return;
        }

        $this->reactivateId = $driver->id;
        $this->name = $driver->name;
        $this->document_number = $driver->document_number;
        $this->document_expiration_date = $driver->document_expiration_date;
        $this->birthdate = $driver->birthdate;
        $this->age = $this->calculateAge($driver->birthdate);
        $this->address = $driver->address;
        $this->district = $driver->district;
        $this->email = $driver->email;
        $this->phone = $driver->phone;
        $this->license = $driver->license;
        $this->class = $driver->class;
        $this->category = $driver->category;
        $this->license_issue_date = $driver->license_issue_date;
        $this->license_revalidation_date = $driver->license_revalidation_date;
        $this->contract_start = $driver->contract_start;
        $this->contract_end = $driver->contract_end;
        $this->condition = $driver->condition;
        $this->score = $driver->score;
        $this->road_education = $driver->road_education;
        $this->road_education_expiration_date = $driver->road_education_expiration_date;
        $this->road_education_municipality = $driver->road_education_municipality;
        $this->credential = $driver->credential;
        $this->credential_expiration_date = $driver->credential_expiration_date;
        $this->credential_municipality = $driver->credential_municipality;
        $this->details = $driver->details;

        $this->dispatch('successAlert', ['message' => 'Datos del conductor cargados. Puede editarlos antes de guardar.']);
    }

    public function save()
    {
        try {
            $this->validate();

            $payload = [
                "name" => $this->name,
                "document_number" => $this->document_number,
                "document_expiration_date" => $this->document_expiration_date,
                "birthdate" => $this->birthdate,
                "address" => $this->address,
                "district" => $this->district,
                "email" => $this->email,
                "phone" => $this->phone,
                "license" => $this->license,
                "class" => $this->class,
                "category" => $this->category,
                "license_issue_date" => $this->license_issue_date,
                "license_revalidation_date" => $this->license_revalidation_date,
                "contract_start" => $this->contract_start,
                "contract_end" => $this->contract_end,
                "condition" => $this->condition,
                "score" => $this->score ?? 0,
                "road_education" => $this->road_education,
                "road_education_expiration_date" => $this->road_education_expiration_date,
                "road_education_municipality" => $this->road_education_municipality,
                "credential" => $this->credential,
                "credential_expiration_date" => $this->credential_expiration_date,
                "credential_municipality" => $this->credential_municipality,
                "details" => $this->details,
            ];

            if ($this->image_file) {
                $payload['image_path'] = $this->image_file->storePublicly('drivers', 'public');
            }

            if ($this->reactivateId) {
                $driver = Driver::findOrFail($this->reactivateId);
                $payload['status'] = 'active';
                $driver->update($payload);
                session()->flash('driver_success', 'Conductor reactivado correctamente.');
            } else {
                Driver::create($payload);
                session()->flash('driver_success', 'Conductor creado correctamente.');
            }

            $this->redirectRoute('settings.drivers.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('driver_error', 'Error al guardar: ' . $e->getMessage());
            $this->redirectRoute('settings.drivers.index');
        }
    }

    public function clean(): void
    {
        $this->reset(['name','document_number','document_expiration_date','birthdate','address','district','email','phone','license','class','category','license_issue_date','license_revalidation_date','contract_start','contract_end','condition','score','road_education','road_education_expiration_date','road_education_municipality','credential','credential_expiration_date','credential_municipality','details','image_file','reactivateId']);
        $this->mount();
    }

    protected function calculateAge(?string $date): ?int
    {
        if (empty($date)) {
            return null;
        }

        try {
            $dob = \Carbon\Carbon::parse($date);
            return $dob->age; // Carbon te da la edad al vuelo
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function updatedBirthdate($value)
    {
        $this->age = $this->calculateAge($value);
    }

    public function render()
    {
        return view('livewire.drivers.create');
    }
}
