<?php

namespace App\Livewire\Users;

use App\Models\Headquarter;
use App\Models\User;
use App\Models\Permission; // tu modelo que extiende Spatie
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

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
    public $headquarter;
    public $headquartes;

    /** Permisos seleccionados (SOLO en EDITAR) */
    public array $selectedPermissions = [];

    protected function rules()
    {
        $id = $this->userId; // null = crear, number = editar

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
            'headquarter'     => ['required', 'integer', 'exists:headquarters,id'],

            // Permisos (solo validan si llegan en editar)
            'selectedPermissions'   => ['array'],
            'selectedPermissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    protected $validationAttributes = [
        "document_type"       => "Tipo de Documento",
        "pwd"                 => "Contraseña",
        "headquarter"         => "Sede",
        "document_number"     => "Número de Documento",
        "selectedPermissions" => "Permisos",
    ];

    public function mount()
    {
        $this->headquartes = Headquarter::where('status', 'active')->get();
    }

    /** Seleccionar todos los permisos de un módulo (para EDITAR) */
    public function selectModule(string $module): void
    {
        $ids = Permission::where('module', $module)->pluck('id')->all();
        $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $ids)));
    }

    /** Desmarcar todos los permisos de un módulo (para EDITAR) */
    public function deselectModule(string $module): void
    {
        $ids = Permission::where('module', $module)->pluck('id')->all();
        $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $ids));
    }

    public function save()
    {
        // Crear SIN gestionar permisos aquí (se otorgan en EDITAR)
        $this->validate();

        User::create([
            "name"            => $this->name,
            "username"        => $this->username,
            "email"           => $this->email,
            "password"        => Hash::make($this->pwd),
            "document_type"   => $this->document_type,
            "document_number" => $this->document_number,
            "phone"           => $this->phone,
            "headquarter_id"  => (int) $this->headquarter,
        ]);

        $this->resetForm();
        $this->dispatch('modal-close', ["name" => "modalAddUser"]);
        $this->dispatch('successAlert', ["message" => "Usuario creado correctamente"]);
    }

    public function update()
    {
        $this->validate();

        $user = User::findOrFail($this->userId);

        $payload = [
            "name"            => $this->name,
            "username"        => $this->username,
            "email"           => $this->email,
            "document_type"   => $this->document_type,
            "document_number" => $this->document_number,
            "phone"           => $this->phone,
            "headquarter_id"  => (int) $this->headquarter,
        ];
        if (!empty($this->pwd)) {
            $payload["password"] = Hash::make($this->pwd);
        }

        $user->update($payload);

        // ⚙️ Tomar IDs seleccionados -> traer NOMBRES y sincronizar por nombre
        $ids    = collect($this->selectedPermissions)->map(fn($v) => (int) $v)->filter()->values();
        $names  = \App\Models\Permission::whereIn('id', $ids)->pluck('name')->all();

        // Spatie acepta array de nombres y se encarga del guard
        $user->syncPermissions($names);

        // (Opcional) limpiar cache de permisos por si acaso
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

        $user = User::findOrFail($id);
        $this->userId          = $id;
        $this->name            = $user->name;
        $this->username        = $user->username;
        $this->email           = $user->email;
        $this->document_type   = $user->document_type;
        $this->document_number = $user->document_number;
        $this->phone           = $user->phone;
        $this->headquarter     = $user->headquarter_id;

        // Preseleccionar permisos del usuario para EDITAR
        $this->selectedPermissions = $user->permissions()->pluck('id')->toArray();

        $this->dispatch('open-modal', ['name' => 'modalEditUser', 'focus' => 'name']);
    }

    private function resetForm(): void
    {
        $this->reset([
            'userId','name','username','pwd','email',
            'document_type','document_number','phone','headquarter',
            'selectedPermissions',
        ]);
        $this->document_type = 'dni';
        $this->selectedPermissions = [];
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
            ->with(['headquarter','permissions'])
            ->get();

        // Permisos agrupados por módulo (para EDITAR)
        $permissionGroups = Permission::query()
            ->orderBy('module')->orderBy('name')
            ->get()
            ->groupBy('module');

        return view('livewire.users.index', compact('users', 'permissionGroups'));
    }
}
