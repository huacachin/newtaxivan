<?php

namespace App\Livewire\Users;

use App\Models\Headquarter;
use App\Models\User;
use App\Models\Permission; // TU modelo (importante)
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
    public $headquartes; // sedes (activo)
    public $roles = [];  // roles (catálogo)

    /** Selecciones del formulario (TU LÓGICA) */
    public array $selectedHeadquarters = []; // sedes múltiples
    public ?int  $defaultHeadquarter   = null; // sede primaria
    public ?int  $selectedRoleId       = null; // un solo rol
    public array $selectedPermissions  = [];   // (IDs) - usado en tu lógica original de update()

    /** === NUEVO: Modal de permisos/rol (dinámico con NOMBRES) === */
    public ?int $permsUserId = null;
    public ?string $permsUserName = null;

    /** Permisos marcados (por NOMBRE) en el modal nuevo */
    public array $selectedPermissionNames = [];

    /** Grupos construidos dinámicamente desde BD:
     *  $aclGroups = [
     *    'configuracion' => ['type'=>'group','title'=>'Configuración','items'=>[['key'=>'configuracion.users','label'=>'Usuarios'], ...]],
     *    'dashboard'     => ['type'=>'single','title'=>'Dashboard','items'=>[['key'=>'dashboard','label'=>'Dashboard']]],
     *  ];
     */
    public array $aclGroups = [];

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

            // (tu lógica original) permisos como IDs
            'selectedPermissions'   => ['array'],

            // (modal nuevo) permisos por NOMBRE
            'selectedPermissionNames' => ['array'],
        ];
    }

    protected $validationAttributes = [
        "document_type"           => "Tipo de Documento",
        "pwd"                     => "Contraseña",
        "document_number"         => "Número de Documento",
        "selectedPermissions"     => "Permisos",
        "selectedPermissionNames" => "Permisos",
        "selectedHeadquarters"    => "Sucursales",
        "defaultHeadquarter"      => "Sucursal primaria",
        "selectedRoleId"          => "Rol",
    ];

    public function mount()
    {
        $this->headquartes = Headquarter::where('status', 'active')->get(['id','name']);
        $this->roles       = Role::orderBy('name')->get(['id','name']);

        $this->buildAclGroups(); // dinámico desde BD
    }

    /** ==== DINÁMICO: construir grupos desde BD ====
     * Reglas:
     * - Si el permiso NO tiene punto => es "single".
     * - Si tiene punto => se agrupa por el primer segmento (padre).
     * - Los padres NO son permisos, solo títulos; cada hijo es un permiso ÚNICO (sin CRUD).
     * - Usa label/module_label si existen; si no, genera un label legible.
     */
    private function buildAclGroups(): void
    {
        $perms = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id','name','label','module','module_label']);

        $groups = [];

        foreach ($perms as $p) {
            $name = $p->name;
            $label = $p->label ?: ($p->module_label ?: $this->humanize($name));

            if (str_contains($name, '.')) {
                [$parent, $rest] = explode('.', $name, 2);
                if (!isset($groups[$parent])) {
                    $groups[$parent] = [
                        'type'  => 'group',
                        'title' => $this->humanize($parent),
                        'items' => [],
                    ];
                }
                $groups[$parent]['items'][] = [
                    'key'   => $name,
                    'label' => $p->label ?: ($p->module_label ?: $this->humanize($rest)),
                ];
            } else {
                // Single
                if (!isset($groups[$name])) {
                    $groups[$name] = [
                        'type'  => 'single',
                        'title' => $label,
                        'items' => [],
                    ];
                }
                $groups[$name]['items'][] = [
                    'key'   => $name,
                    'label' => $label,
                ];
            }
        }

        // Orden opcional: singles primero por nombre, luego grupos por nombre
        ksort($groups);

        $this->aclGroups = $groups;
    }

    private function humanize(string $val): string
    {
        $val = str_replace(['_', '-'], ' ', $val);
        $parts = explode('.', $val);
        $val = implode(' · ', array_map(fn($x) => mb_convert_case($x, MB_CASE_TITLE, "UTF-8"), $parts));
        return $val;
    }

    /** ====== Modal de permisos (NOMBRES) ====== */
    public function openPermsModal(int $id): void
    {
        $this->resetValidation();

        $user = User::with(['roles','permissions'])->findOrFail($id);

        $this->permsUserId      = $id;
        $this->permsUserName    = $user->name;

        // Rol único
        $this->selectedRoleId = $user->roles()->value('id');

        // Permisos directos por NOMBRE para el modal
        $this->selectedPermissionNames = $user->permissions()->pluck('name')->toArray();

        // Reconstruye por si hubo cambios
        $this->buildAclGroups();

        $this->dispatch('open-modal', ['name' => 'modalPerms', 'focus' => null]);
    }

    public function selectGroup(string $groupKey): void
    {
        if (!isset($this->aclGroups[$groupKey])) return;
        $keys = array_column($this->aclGroups[$groupKey]['items'], 'key');
        $this->selectedPermissionNames = array_values(array_unique(array_merge($this->selectedPermissionNames, $keys)));
    }

    public function deselectGroup(string $groupKey): void
    {
        if (!isset($this->aclGroups[$groupKey])) return;
        $keys = array_column($this->aclGroups[$groupKey]['items'], 'key');
        $this->selectedPermissionNames = array_values(array_diff($this->selectedPermissionNames, $keys));
    }

    public function savePerms(): void
    {
        $this->validate([
            'permsUserId'             => ['required','integer','exists:users,id'],
            'selectedRoleId'          => ['nullable','integer','exists:roles,id'],
            'selectedPermissionNames' => ['array'],
        ]);

        $user = User::findOrFail($this->permsUserId);

        // Rol único
        $roleName = null;
        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
        }
        $user->syncRoles($roleName ? [$roleName] : []);

        // Permisos directos por NOMBRE
        $names = Permission::whereIn('name', $this->selectedPermissionNames)->pluck('name')->all();
        $user->syncPermissions($names);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->permsUserId = null;
        $this->permsUserName = null;

        $this->dispatch('modal-close', ['name' => 'modalPerms']);
        $this->dispatch('successAlert', ['message' => 'Rol & permisos actualizados']);
    }

    /** ====== TU LÓGICA ORIGINAL (no tocada) ====== */

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

        // (Tu original) Permisos directos por ID si fuera el caso:
        $ids   = collect($this->selectedPermissions)->map(fn($v) => (int)$v)->filter()->values();
        $names = Permission::whereIn('id', $ids)->pluck('name')->all();
        if (!empty($names)) {
            $user->syncPermissions($names);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->resetForm();
        $this->dispatch('modal-close', ["name" => "modalEditUser"]);
        $this->dispatch('successAlert', ["message" => "Usuario actualizado correctamente"]);
    }

    public function openAddWindow(): void
    {

        $route = route('settings.users.create');

        $this->dispatch('url-open',["url" => $route]);
    }

    public function openEditWindow(int $id): void
    {
        $route = route('settings.users.edit',["user" => $id]);

        $this->dispatch('url-open',["url" => $route]);
    }

    public function openPermsWindow(int $id): void
    {
        $route = route('settings.users.perms',["user" => $id]);

        $this->dispatch('url-open',["url" => $route]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'userId','name','username','pwd','email',
            'document_type','document_number','phone',
            'selectedHeadquarters','defaultHeadquarter',
            'selectedRoleId','selectedPermissions',
            'selectedPermissionNames',
        ]);
        $this->document_type        = 'dni';
        $this->selectedHeadquarters = [];
        $this->defaultHeadquarter   = null;
        $this->selectedRoleId       = null;
        $this->selectedPermissions  = [];
        $this->selectedPermissionNames = [];
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

        return view('livewire.users.index', compact('users', 'permissionGroups', 'roles'))
            ->with('aclGroups', $this->aclGroups);
    }
}
