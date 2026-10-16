<?php

namespace App\Livewire\Users;

use App\Models\Headquarter;
use App\Models\User;
use App\Traits\ProtectsDirectorRole;
use App\Traits\ValidatesUserAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Edit extends Component
{
    use ProtectsDirectorRole;
    use ValidatesUserAccount;

    public User $user;

    public string $name = '';

    public string $username = '';

    public ?string $email = null;

    public string $pwd = '';

    public string $document_type = 'dni';

    public string $document_number = '';

    public string $phone = '';

    public ?int $sort_order = 0;

    public $headquartes;

    public $roles = [];

    public array $selectedHeadquarters = [];

    public ?int $defaultHeadquarter = null;

    public ?int $selectedRoleId = null;

    public bool $canFullEdit = false;

    public string $editedUserRoleName = '';

    public function mount(int $id)
    {
        $this->user = User::with(['headquarters', 'roles'])->findOrFail($id);

        $authUser = auth()->user();
        if (! $authUser->canManageUser($this->user)) {
            abort(403);
        }

        $this->canFullEdit = $authUser->isDirector();
        $this->editedUserRoleName = $this->user->roles->first()?->name ?? '';

        $this->headquartes = Headquarter::where('status', 'active')->get(['id', 'name']);
        $this->roles = Role::all(['id', 'name'])->sortBy(fn ($r) => User::ROLE_HIERARCHY[$r->name] ?? 0)->values();

        $this->name = $this->user->name;
        $this->username = $this->user->username;
        $this->email = $this->user->email;
        $this->document_type = mb_strtolower((string) $this->user->document_type);
        $this->document_number = $this->user->document_number;
        $this->phone = $this->user->phone;
        $this->sort_order = $this->user->sort_order ?? 0;

        $this->selectedHeadquarters = $this->user->headquarters->pluck('id')->map(fn ($v) => (int) $v)->toArray();
        $this->prevSelectedHeadquarters = $this->selectedHeadquarters;
        $this->defaultHeadquarter = optional($this->user->headquarters->firstWhere('pivot.is_default', true))->id
            ?? $this->user->headquarter_id;

        $this->selectedRoleId = $this->user->roles()->value('id');
    }

    /** Copia del estado anterior de sucursales para detectar cuál se acaba de marcar */
    public array $prevSelectedHeadquarters = [];

    public function updatedSelectedHeadquarters(): void
    {
        $selected = array_values(array_map('intval', (array) $this->selectedHeadquarters));
        $added = array_values(array_diff($selected, array_map('intval', $this->prevSelectedHeadquarters)));
        $this->prevSelectedHeadquarters = $selected;

        // Se desmarcó la sucursal primaria: queda pendiente hasta el próximo check
        if ($this->defaultHeadquarter && ! in_array((int) $this->defaultHeadquarter, $selected, true)) {
            $this->defaultHeadquarter = null;
        }

        // Sin primaria y se acaba de marcar una sucursal: esa nueva es la primaria
        if (! $this->defaultHeadquarter && $added) {
            $this->defaultHeadquarter = (int) end($added);
        }
    }

    protected function rules()
    {
        $id = $this->user->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:64', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'pwd' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'document_type' => $this->documentTypeRules(),
            'document_number' => ['required', 'string', 'max:12', ...$this->documentNumberFormatRules(), Rule::unique('users', 'document_number')->ignore($id)->where(fn ($q) => $q->where('document_type', $this->document_type))],
            'phone' => ['required', 'string', 'max:15'],
            'sort_order' => ['nullable', 'integer'],
            // Al cambiar de rol las sedes se reinician (ver update), por eso solo se exigen si el rol se mantiene
            'selectedHeadquarters' => [Rule::requiredIf(fn () => ! $this->selectedRoleGetsAllHeadquarters() && $this->selectedRoleName() === $this->editedUserRoleName), 'array'],
            'selectedHeadquarters.*' => ['integer', 'exists:headquarters,id'],
            'defaultHeadquarter' => ['nullable', 'integer', 'exists:headquarters,id'],
            'selectedRoleId' => ['required', 'integer', 'exists:roles,id'],
        ];
    }

    protected $validationAttributes = [
        'document_type' => 'tipo de documento',
        'document_number' => 'número de documento',
        'pwd' => 'contraseña',
        'selectedRoleId' => 'rol',
        'selectedHeadquarters' => 'sucursales',
    ];

    public function update(string $directorKey = '')
    {
        $authUser = auth()->user();
        if (! $authUser->canManageUser($this->user)) {
            abort(403);
        }

        if ($this->canFullEdit) {
            $this->validate();

            // Dar o quitar el rol Director exige la clave de seguridad
            $currentRole = $this->user->roles()->value('name');
            $roleName = $this->selectedRoleName();
            if ($this->directorRoleChangeBlocked($currentRole, $roleName, $this->user, $directorKey)) {
                return;
            }

            // Si el rol es admin, asignar todas las sedes con Huaycan como primaria
            if ($this->selectedRoleGetsAllHeadquarters()) {
                $allHqs = Headquarter::where('status', 'active')->pluck('id')->map(fn ($v) => (int) $v)->toArray();
                $this->selectedHeadquarters = $allHqs;
                $huaycan = Headquarter::where('name', 'Huaycan')->value('id');
                $this->defaultHeadquarter = $huaycan ? (int) $huaycan : ($allHqs[0] ?? null);
            }

            if ($this->defaultHeadquarter && ! in_array($this->defaultHeadquarter, $this->selectedHeadquarters, true)) {
                $this->selectedHeadquarters[] = $this->defaultHeadquarter;
            }

            $payload = [
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'document_type' => $this->document_type,
                'document_number' => $this->document_number,
                'phone' => $this->phone,
                'sort_order' => $this->sort_order ?? 0,
            ];
            if (! empty($this->pwd)) {
                $payload['password'] = Hash::make($this->pwd);
            }
            $this->user->update($payload);

            $attach = collect($this->selectedHeadquarters)
                ->mapWithKeys(fn ($id) => [(int) $id => ['is_default' => (int) $id === (int) $this->defaultHeadquarter]])
                ->all();
            $this->user->headquarters()->sync($attach);

            $this->user->headquarter_id = $this->defaultHeadquarter
                ?: (count($this->selectedHeadquarters) ? (int) $this->selectedHeadquarters[0] : null);
            $this->user->save();

            // Si el rol cambió, limpiar permisos y sucursales
            if ($roleName !== $currentRole) {
                $this->user->headquarters()->sync([]);
                $this->user->update(['headquarter_id' => null]);

                if ($roleName === 'director') {
                    $allPerms = \App\Models\Permission::where('guard_name', 'web')->pluck('name')->toArray();
                    $this->user->syncPermissions($allPerms);
                } else {
                    $this->user->syncPermissions([]);
                }
            }

            $this->user->syncRoles($roleName ? [$roleName] : []);
            $this->logRoleChange($this->user, $currentRole, $roleName);
        } else {
            // No-Director: solo password y sucursales (sucursales solo si el editado es controlador)
            $rules = ['pwd' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()]];

            $isControlador = $this->editedUserRoleName === 'controlador';
            if ($isControlador) {
                $rules['selectedHeadquarters'] = ['required', 'array'];
                $rules['selectedHeadquarters.*'] = ['integer', 'exists:headquarters,id'];
                $rules['defaultHeadquarter'] = ['nullable', 'integer', 'exists:headquarters,id'];
            }

            $this->validate($rules);

            if (! empty($this->pwd)) {
                $this->user->update(['password' => Hash::make($this->pwd)]);
            }

            if ($isControlador) {
                if ($this->defaultHeadquarter && ! in_array($this->defaultHeadquarter, $this->selectedHeadquarters, true)) {
                    $this->selectedHeadquarters[] = $this->defaultHeadquarter;
                }

                $attach = collect($this->selectedHeadquarters)
                    ->mapWithKeys(fn ($id) => [(int) $id => ['is_default' => (int) $id === (int) $this->defaultHeadquarter]])
                    ->all();
                $this->user->headquarters()->sync($attach);

                $this->user->headquarter_id = $this->defaultHeadquarter
                    ?: (count($this->selectedHeadquarters) ? (int) $this->selectedHeadquarters[0] : null);
                $this->user->save();
            }
        }

        session()->flash('success', 'Usuario actualizado');

        return redirect()->route('settings.users.index');
    }

    public function render()
    {
        return view('livewire.users.edit');
    }
}
