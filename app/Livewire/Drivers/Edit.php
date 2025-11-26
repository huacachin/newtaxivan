<?php
// app/Livewire/Drivers/Edit.php
namespace App\Livewire\Drivers;

use App\Models\Driver;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Driver $driver;

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

    public $age;

    public function mount(int $id)
    {
        $this->driver = Driver::find($id);

        $this->name = $this->driver->name;
        $this->document_number = $this->driver->document_number;
        $this->document_expiration_date = optional($this->driver->document_expiration_date)?->format('Y-m-d');
        $this->birthdate = optional($this->driver->birthdate)?->format('Y-m-d');
        $this->age = $this->calculateAge($this->birthdate);
        $this->address = $this->driver->address;
        $this->district = $this->driver->district;
        $this->email = $this->driver->email;
        $this->phone = $this->driver->phone;
        $this->license = $this->driver->license;
        $this->class = $this->driver->class;
        $this->category = $this->driver->category;
        $this->license_issue_date = optional($this->driver->license_issue_date)?->format('Y-m-d');
        $this->license_revalidation_date = optional($this->driver->license_revalidation_date)?->format('Y-m-d');
        $this->contract_start = optional($this->driver->contract_start)?->format('Y-m-d');
        $this->contract_end = optional($this->driver->contract_end)?->format('Y-m-d');
        $this->condition = $this->driver->condition;
        $this->score = $this->driver->score;
        $this->road_education = optional($this->driver->road_education)?->format('Y-m-d');
        $this->road_education_expiration_date = optional($this->driver->road_education_expiration_date)?->format('Y-m-d');
        $this->road_education_municipality = $this->driver->road_education_municipality;
        $this->credential = optional($this->driver->credential)?->format('Y-m-d');
        $this->credential_expiration_date = optional($this->driver->credential_expiration_date)?->format('Y-m-d');
        $this->credential_municipality = $this->driver->credential_municipality;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'document_number' => ['required','string','max:255', Rule::unique('drivers','document_number')->ignore($this->driver->id)],
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
            'road_education' => 'nullable|date',
            'road_education_expiration_date' => 'nullable|date',
            'road_education_municipality' => 'nullable|string|max:255',
            'credential' => 'nullable|date',
            'credential_expiration_date' => 'nullable|date',
            'credential_municipality' => 'nullable|string|max:255',
        ];
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


    public function update()
    {
        $this->validate();

        $this->driver->update([
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
        ]);

        $this->dispatch('successAlert',["message" => "Conductor actualizado correctamente"]);


    }

    public function render()
    {
        return view('livewire.drivers.edit');
    }
}
