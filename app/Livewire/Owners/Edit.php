<?php
// app/Livewire/Owners/Edit.php
namespace App\Livewire\Owners;

use App\Models\Owner;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Edit extends Component
{
    public Owner $owner;

    public $name;
    public $document_type = '';
    public $document_number;
    public $document_expiration_date;
    public $birthdate;
    public $address;
    public $district;
    public $email;
    public $phone;

    public function mount(int $id)
    {
        $this->owner = Owner::find($id);

        $this->name = $this->owner->name;
        $this->document_type = $this->owner->document_type;
        $this->document_number = $this->owner->document_number;
        $this->document_expiration_date = optional($this->owner->document_expiration_date)?->format('Y-m-d');
        $this->birthdate = optional($this->owner->birthdate)?->format('Y-m-d');
        $this->address = $this->owner->address;
        $this->district = $this->owner->district;
        $this->email = $this->owner->email;
        $this->phone = $this->owner->phone;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
            'document_number' => 'required|string|max:255|unique:owners,document_number,' . $this->owner->id,
            'document_expiration_date' => 'nullable|date',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'phone' => 'nullable|string|max:255',
        ];
    }

    public function update()
    {
        $this->validate();

        $this->owner->update([
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

        $this->dispatch('successAlert', ['message' => 'Propietario actualizado correctamente.']);
    }

    public function render()
    {
        return view('livewire.owners.edit');
    }
}
