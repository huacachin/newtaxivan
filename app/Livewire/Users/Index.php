<?php

namespace App\Livewire\Users;

use App\Models\Headquarter;
use App\Models\User;
use App\Models\Permission; // tu modelo que extiende Spatie (con module, label, etc.)
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    public $userId;
    public $search = '';

    public $name;
    public $username;
    public $pwd;
    public $email;
    public $document_type = 'dni';
    public $document_number;
    public $phone;

    /** Catálogos */
    public $headquartes;                 // sedes (activo)
    public $roles = [];                  // roles (catálogo)

    /** Selecciones del formulario */
    public array $selectedHeadquarters = []; // sedes múltiples
    public ?int  $defaultHeadquarter   = null; // sede primaria
    public ?int  $selectedRoleId       = null; // un solo rol
    public array $selectedPermissions  = [];   // permisos directos (solo editar)

    protected function rules()
    {
        $id = $this->userId;

        $uniqueUsername = Rule::unique('users', 'username');
        if ($id) $uniqueUsername = $uniqueUsername->ignore($id);

        $uniqueEmail = Rule::unique('users', 'email');
        if ($id) $uniqueEmail = $uniqueEmail->ignore($id);

        $uniqueDoc = Rule::unique('users', 'document_number')
            ->where(fn($q) => $q->where('document_type', $this->document_type));
        if ($id) $uniqueDoc = $uniqueDoc->ignore($id);

        return [
            'name'            => ['required', 'string', 'max:255'],
            'username'        => ['required', 'string', 'min:3', 'max:64', $uniqueUsername],
            'email'           => ['nullable', 'email', 'max:255', $uniqueEmail],
            'pwd'             => [$id ? 'nullable' : 'required', 'string', 'min:8'],
            'document_type'   => ['required', 'string', 'max:3'],
            'document_number' => ['required', 'string', 'max:11', $uniqueDoc],
            'phone'           => ['required', 'string', 'max:15'],

            // sedes (N:N)
            'selectedHeadquarters'   => ['array'],
            'selectedHeadquarters.*' => ['integer', 'exists:headquarters,id'],
            'defaultHeadquarter'     => ['nullable', 'integer', 'exists:headquarters,id'],

            // rol único
            'selectedRoleId'         => ['nullable', 'integer', 'exists:roles,id'],

            // permisos directos (editar)
            'selectedPermissions'   => ['array'],
            'selectedPermissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    protected $validationAttributes = [
        "document_type"       => "Tipo de Documento",
        "pwd"                 => "Contraseña",
        "document_number"     => "Número de Documento",
        "selectedPermissions" => "Permisos",
        "selectedHeadquarters"=> "Sucursales",
        "defaultHeadquarter"  => "Sucursal primaria",
        "selectedRoleId"      => "Rol",
    ];

    public function mount()
    {
        $this->headquartes = Headquarter::where('status', 'active')->get(['id','name']);
        $this->roles       = Role::orderBy('name')->get(['id','name']);
    }

    /** Marcar todos los permisos de un módulo (editar) */
    public function selectModule(string $module): void
    {
        $ids = Permission::where('module', $module)->pluck('id')->all();
        $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $ids)));
    }

    /** Desmarcar todos los permisos de un módulo (editar) */
    public function deselectModule(string $module): void
    {
        $ids = Permission::where('module', $module)->pluck('id')->all();
        $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $ids));
    }

    public function save()
    {
        $this->validate();

        // Si hay primaria y no está en el set, agrégala
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
            // headquarter_id se setea luego como primaria
        ]);

        // Sincronizar sedes (N:N) con flag is_default
        $attach = collect($this->selectedHeadquarters)
            ->mapWithKeys(fn($id) => [(int)$id => ['is_default' => (int)$id === (int)$this->defaultHeadquarter]])
            ->all();
        $user->headquarters()->sync($attach);

        // Guardar primaria en users.headquarter_id
        $user->headquarter_id = $this->defaultHeadquarter
            ?: (count($this->selectedHeadquarters) ? (int)$this->selectedHeadquarters[0] : null);
        $user->save();

        // Rol único (si se eligió)
        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
            if ($roleName) $user->syncRoles([$roleName]);
        }

        $this->resetForm();
        $this->dispatch('modal-close', ["name" => "modalAddUser"]);
        $this->dispatch('successAlert', ["message" => "Usuario creado correctamente"]);
    }

    public function update()
    {
        $this->validate();

        if ($this->defaultHeadquarter && !in_array($this->defaultHeadquarter, $this->selectedHeadquarters, true)) {
            $this->selectedHeadquarters[] = $this->defaultHeadquarter;
        }

        $user = User::findOrFail($this->userId);

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
        $user->update($payload);

        // Sync sedes
        $attach = collect($this->selectedHeadquarters)
            ->mapWithKeys(fn($id) => [(int)$id => ['is_default' => (int)$id === (int)$this->defaultHeadquarter]])
            ->all();
        $user->headquarters()->sync($attach);

        $user->headquarter_id = $this->defaultHeadquarter
            ?: (count($this->selectedHeadquarters) ? (int)$this->selectedHeadquarters[0] : null);
        $user->save();

        // Rol único
        $roleName = null;
        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
        }
        $user->syncRoles($roleName ? [$roleName] : []);

        // Permisos directos
        $ids   = collect($this->selectedPermissions)->map(fn($v) => (int)$v)->filter()->values();
        $names = Permission::whereIn('id', $ids)->pluck('name')->all();
        $user->syncPermissions($names);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->resetForm();
        $this->dispatch('modal-close', ["name" => "modalEditUser"]);
        $this->dispatch('successAlert', ["message" => "Usuario actualizado correctamente"]);
    }

    public function openAddModal()
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('open-modal', ["name" => "modalAddUser", "focus" => "name"]);
    }

    public function openEditModal($id)
    {
        $this->resetValidation();

        $user = User::with(['headquarters','roles','permissions'])->findOrFail($id);

        $this->userId          = $id;
        $this->name            = $user->name;
        $this->username        = $user->username;
        $this->email           = $user->email;
        $this->document_type   = $user->document_type;
        $this->document_number = $user->document_number;
        $this->phone           = $user->phone;

        // sedes seleccionadas + primaria
        $this->selectedHeadquarters = $user->headquarters->pluck('id')->map(fn($v)=>(int)$v)->toArray();
        $this->defaultHeadquarter   = optional($user->headquarters->firstWhere('pivot.is_default', true))->id
            ?? $user->headquarter_id;

        // rol único (tomamos el primero)
        $this->selectedRoleId = $user->roles()->value('id');

        // permisos directos
        $this->selectedPermissions = $user->permissions()->pluck('id')->toArray();

        $this->dispatch('open-modal', ['name' => 'modalEditUser', 'focus' => 'name']);
    }

    private function resetForm(): void
    {
        $this->reset([
            'userId','name','username','pwd','email',
            'document_type','document_number','phone',
            'selectedHeadquarters','defaultHeadquarter',
            'selectedRoleId','selectedPermissions',
        ]);
        $this->document_type       = 'dni';
        $this->selectedHeadquarters = [];
        $this->defaultHeadquarter   = null;
        $this->selectedRoleId       = null;
        $this->selectedPermissions  = [];
    }

    public function render()
    {
        $term = trim((string) $this->search);

        $users = User::query()
            ->where('status', 'active')
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    if (str_contains($term, '@')) {
                        $w->where('email', $term);
                    } else {
                        $w->where('username', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('name', 'like', "%{$term}%");
                    }
                });
            })
            ->with(['headquarter','headquarters','roles','permissions'])
            ->get();

        $permissionGroups = Permission::query()
            ->orderBy('module')->orderBy('name')
            ->get()
            ->groupBy('module');

        $roles = $this->roles;

        return view('livewire.users.index', compact('users', 'permissionGroups', 'roles'));
    }
}
