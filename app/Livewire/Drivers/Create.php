<?php
// app/Livewire/Drivers/Create.php
namespace App\Livewire\Drivers;

use App\Models\Driver;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Create extends Component
{
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
    public $credential; // fecha
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
        'condition' => 'required|string|max:255',
        'score' => 'nullable|numeric|between:0,100',
        'credential' => 'nullable|date',
        'credential_expiration_date' => 'nullable|date',
        'credential_municipality' => 'nullable|string|max:255',
    ];

    public function mount(){
        $today = Carbon::today()->toDateString();
        $this->birthdate = $today;
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
        $this->validate();

        Driver::create([
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
            "credential" => $this->credential,
            "credential_expiration_date" => $this->credential_expiration_date,
            "credential_municipality" => $this->credential_municipality
        ]);

        $this->reset('name','document_number','document_expiration_date','birthdate','address','district','email','phone','license','class','category','license_issue_date','license_revalidation_date','contract_start','contract_end','condition','score','credential','credential_expiration_date','credential_municipality');

        $this->dispatch('successAlert',["message" => "Conductor creado correctamente"]);
    }

    public function render()
    {
        return view('livewire.drivers.create');
    }
}
