<?php

namespace App\Livewire\Users;

use App\Models\Headquarter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Edit extends Component
{
    public User $user;

    public string $name = '';
    public string $username = '';
    public ?string $email = null;
    public string $pwd = '';
    public string $document_type = 'dni';
    public string $document_number = '';
    public string $phone = '';

    public $headquartes;
    public $roles = [];

    public array $selectedHeadquarters = [];
    public ?int $defaultHeadquarter = null;
    public ?int $selectedRoleId = null;

    public function mount(int $id)
    {
        $this->user = User::with(['headquarters','roles'])->findOrFail($id);
        $this->headquartes = Headquarter::where('status','active')->get(['id','name']);
        $this->roles = Role::orderBy('name')->get(['id','name']);

        $this->name = $this->user->name;
        $this->username = $this->user->username;
        $this->email = $this->user->email;
        $this->document_type = $this->user->document_type;
        $this->document_number = $this->user->document_number;
        $this->phone = $this->user->phone;

        $this->selectedHeadquarters = $this->user->headquarters->pluck('id')->map(fn($v)=>(int)$v)->toArray();
        $this->defaultHeadquarter   = optional($this->user->headquarters->firstWhere('pivot.is_default', true))->id
            ?? $this->user->headquarter_id;

        $this->selectedRoleId = $this->user->roles()->value('id');
    }

    protected function rules()
    {
        $id = $this->user->id;

        return [
            'name'            => ['required','string','max:255'],
            'username'        => ['required','string','min:3','max:64', Rule::unique('users','username')->ignore($id)],
            'email'           => ['nullable','email','max:255', Rule::unique('users','email')->ignore($id)],
            'pwd'             => ['nullable','string','min:8'],
            'document_type'   => ['required','string','max:3'],
            'document_number' => ['required','string','max:11', Rule::unique('users','document_number')->ignore($id)->where(fn($q)=>$q->where('document_type',$this->document_type))],
            'phone'           => ['required','string','max:15'],
            'selectedHeadquarters'   => ['array'],
            'selectedHeadquarters.*' => ['integer','exists:headquarters,id'],
            'defaultHeadquarter'     => ['nullable','integer','exists:headquarters,id'],
            'selectedRoleId'         => ['nullable','integer','exists:roles,id'],
        ];
    }

    protected $validationAttributes = [
        'document_number' => 'número de documento',
        'pwd' => 'contraseña'
    ];

    public function update()
    {
        $this->validate();

        // Si el rol es admin, asignar todas las sedes con Huaycan como primaria
        $roleName = $this->selectedRoleId
            ? collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name
            : null;

        if ($roleName && mb_strtolower($roleName) === 'admin') {
            $allHqs = Headquarter::where('status', 'active')->pluck('id')->map(fn($v) => (int)$v)->toArray();
            $this->selectedHeadquarters = $allHqs;
            $huaycan = Headquarter::where('name', 'Huaycan')->value('id');
            $this->defaultHeadquarter = $huaycan ? (int)$huaycan : ($allHqs[0] ?? null);
        }

        if ($this->defaultHeadquarter && !in_array($this->defaultHeadquarter, $this->selectedHeadquarters, true)) {
            $this->selectedHeadquarters[] = $this->defaultHeadquarter;
        }

        $payload = [
            "name"            => $this->name,
            "username"        => $this->username,
            "email"           => $this->email,
            "document_type"   => $this->document_type,
            "document_number" => $this->document_number,
            "phone"           => $this->phone,
        ];
        if (!empty($this->pwd)) {
            $payload["password"] = Hash::make($this->pwd);
        }
        $this->user->update($payload);

        $attach = collect($this->selectedHeadquarters)
            ->mapWithKeys(fn($id) => [(int)$id => ['is_default' => (int)$id === (int)$this->defaultHeadquarter]])
            ->all();
        $this->user->headquarters()->sync($attach);

        $this->user->headquarter_id = $this->defaultHeadquarter
            ?: (count($this->selectedHeadquarters) ? (int)$this->selectedHeadquarters[0] : null);
        $this->user->save();

        $roleName = null;
        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
        }
        $this->user->syncRoles($roleName ? [$roleName] : []);

        session()->flash('success','Usuario actualizado');
        return redirect()->route('settings.users.index');
    }

    public function render()
    {
        return view('livewire.users.edit');
    }
}
