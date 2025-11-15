<?php
// app/Livewire/Owners/Create.php
namespace App\Livewire\Owners;

use App\Models\Owner;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Create extends Component
{
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

    public function mount(){
        $today = Carbon::today()->toDateString();
        $this->birthdate = $today;
        $this->document_expiration_date = $today;
    }

    public function save():void
    {
        $this->validate();

        Owner::create([
            'name' => $this->name,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'document_expiration_date' => $this->document_expiration_date,
            'birthdate' => $this->birthdate,
            'address' => $this->address,
            'district' => $this->district,
            'email' => $this->email,
            'phone' => $this->phone,
        ]);


        $this->reset(['name', 'document_type', 'document_number', 'document_expiration_date', 'birthdate', 'address', 'district', 'email', 'phone']);
        $this->dispatch('successAlert', ['message' => 'Propietario creado correctamente.']);

    }

    public function render()
    {
        return view('livewire.owners.create');
    }
}
