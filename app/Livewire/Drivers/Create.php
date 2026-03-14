<?php
// app/Livewire/Drivers/Create.php
namespace App\Livewire\Drivers;

use App\Models\Driver;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public $name;
    public $document_number;
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

            Driver::create($payload);

            session()->flash('driver_success', 'Conductor creado correctamente.');
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
        $this->reset(['name','document_number','document_expiration_date','birthdate','address','district','email','phone','license','class','category','license_issue_date','license_revalidation_date','contract_start','contract_end','condition','score','road_education','road_education_expiration_date','road_education_municipality','credential','credential_expiration_date','credential_municipality','details','image_file']);
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
