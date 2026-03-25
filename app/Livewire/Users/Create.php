<?php

namespace App\Livewire\Users;

use App\Models\Headquarter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Create extends Component
{
    public string $name = '';
    public string $username = '';
    public string $pwd = '';
    public ?string $email = null;
    public string $document_type = 'dni';
    public string $document_number = '';
    public string $phone = '';

    public $headquartes;
    public $roles = [];

    public array $selectedHeadquarters = [];
    public ?int $defaultHeadquarter = null;
    public ?int $selectedRoleId = null;

    protected function rules()
    {
        return [
            'name'            => ['required','string','max:255'],
            'username'        => ['required','string','min:3','max:64', Rule::unique('users','username')],
            'email'           => ['nullable','email','max:255', Rule::unique('users','email')],
            'pwd'             => ['required','string','min:8'],
            'document_type'   => ['required','string','max:3'],
            'document_number' => ['required','string','max:11', Rule::unique('users','document_number')->where(fn($q)=>$q->where('document_type',$this->document_type))],
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

    public function mount()
    {
        $this->headquartes = Headquarter::where('status','active')->get(['id','name']);
        $this->roles = Role::orderBy('name')->get(['id','name']);
    }

    public function save()
    {
        $this->validate();

        // Si el rol es admin, asignar todas las sedes con Huaycan como primaria
        $roleName = $this->selectedRoleId
            ? collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name
            : null;

        if ($roleName && in_array(mb_strtolower($roleName), ['director', 'gerente'])) {
            $allHqs = Headquarter::where('status', 'active')->pluck('id')->map(fn($v) => (int)$v)->toArray();
            $this->selectedHeadquarters = $allHqs;
            $huaycan = Headquarter::where('name', 'Huaycan')->value('id');
            $this->defaultHeadquarter = $huaycan ? (int)$huaycan : ($allHqs[0] ?? null);
        }

        if ($this->defaultHeadquarter && !in_array($this->defaultHeadquarter, $this->selectedHeadquarters, true)) {
            $this->selectedHeadquarters[] = $this->defaultHeadquarter;
        }

        $user = User::create([
            "name"            => $this->name,
            "username"        => $this->username,
            "email"           => $this->email,
            "password"        => Hash::make($this->pwd),
            "document_type"   => $this->document_type,
            "document_number" => $this->document_number,
            "phone"           => $this->phone,
        ]);

        $attach = collect($this->selectedHeadquarters)
            ->mapWithKeys(fn($id) => [(int)$id => ['is_default' => (int)$id === (int)$this->defaultHeadquarter]])
            ->all();
        $user->headquarters()->sync($attach);

        $user->headquarter_id = $this->defaultHeadquarter
            ?: (count($this->selectedHeadquarters) ? (int)$this->selectedHeadquarters[0] : null);
        $user->save();

        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
            if ($roleName) $user->syncRoles([$roleName]);
        }

        session()->flash('success','Usuario creado correctamente');
        return redirect()->route('settings.users.index');
    }

    public function clean(): void
    {
        $this->reset(['name', 'username', 'pwd', 'email', 'document_type', 'document_number', 'phone', 'selectedHeadquarters', 'defaultHeadquarter', 'selectedRoleId']);
    }

    public function render()
    {
        return view('livewire.users.create');
    }
}
