<?php

namespace App\Livewire\Users;

use App\Models\Permission;
use App\Models\User;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Perms extends Component
{
    public User $user;

    public ?string $permsUserName = null;
    public ?int $selectedRoleId = null;
    public array $selectedPermissionNames = [];
    public array $aclGroups = [];
    public $roles = [];
    public bool $canEdit = false;

    public function mount(int $id)
    {
        $this->user = User::with(['headquarters','roles'])->findOrFail($id);

        $authUser = auth()->user();
        if (!$authUser->canManageUser($this->user)) {
            abort(403);
        }

        $this->canEdit = $authUser->isDirector();

        $this->permsUserName = $this->user->name;
        $this->roles = Role::all(['id','name'])->sortBy(fn($r) => User::ROLE_HIERARCHY[$r->name] ?? 0)->values();

        $this->selectedRoleId = $this->user->roles()->value('id');

        $editedUserRole = $this->user->roles->first()?->name;
        if ($editedUserRole === 'director') {
            $this->selectedPermissionNames = Permission::where('guard_name', 'web')->pluck('name')->toArray();
            $this->canEdit = false;
        } else {
            $this->selectedPermissionNames = $this->user->permissions()->pluck('name')->toArray();
        }

        $this->buildAclGroups();
    }

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
                $groups[$parent] ??= ['type'=>'group','title'=>$this->humanize($parent),'items'=>[]];
                $groups[$parent]['items'][] = ['key'=>$name,'label'=>$p->label ?: ($p->module_label ?: $this->humanize($rest))];
            } else {
                $groups[$name] ??= ['type'=>'single','title'=>$label,'items'=>[]];
                $groups[$name]['items'][] = ['key'=>$name,'label'=>$label];
            }
        }
        $sidebarOrder = ['dashboard', 'configuracion', 'departures', 'payments', 'debts', 'cash'];
        $itemsOrder = [
            'configuracion' => [
                'configuracion.vehicles',
                'configuracion.owners',
                'configuracion.drivers',
                'configuracion.cost-per-plate',
                'configuracion.users',
                'configuracion.concepts',
                'configuracion.headquarters',
            ],
            'debts' => ['debts.days', 'debts.monthly'],
            'cash'  => ['cash.incomes', 'cash.expenses', 'cash.report-general', 'cash.report-draco', 'cash.report-sal-pag-cont', 'cash.report-caja-ma'],
        ];

        // Ordenar items dentro de cada grupo
        foreach ($itemsOrder as $groupKey => $order) {
            if (!isset($groups[$groupKey])) continue;
            $items = $groups[$groupKey]['items'];
            $sorted = [];
            foreach ($order as $key) {
                foreach ($items as $item) {
                    if ($item['key'] === $key) {
                        $sorted[] = $item;
                        break;
                    }
                }
            }
            foreach ($items as $item) {
                if (!in_array($item['key'], $order)) {
                    $sorted[] = $item;
                }
            }
            $groups[$groupKey]['items'] = $sorted;
        }

        // Ordenar grupos según sidebar
        $ordered = [];
        foreach ($sidebarOrder as $key) {
            if (isset($groups[$key])) {
                $ordered[$key] = $groups[$key];
            }
        }
        foreach ($groups as $key => $group) {
            if (!isset($ordered[$key])) {
                $ordered[$key] = $group;
            }
        }
        $this->aclGroups = $ordered;
    }

    private function humanize(string $val): string
    {
        $val = str_replace(['_', '-'], ' ', $val);
        $parts = explode('.', $val);
        return implode(' · ', array_map(fn($x)=>mb_convert_case($x, MB_CASE_TITLE, "UTF-8"), $parts));
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
        $authUser = auth()->user();
        if (!$authUser->isDirector() || !$authUser->canManageUser($this->user)) {
            abort(403);
        }

        $user = $this->user;

        // Rol único
        $roleName = null;
        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
        }

        // Si el rol cambió, limpiar permisos y sucursales
        $currentRole = $user->roles->first()?->name;
        if ($roleName !== $currentRole) {
            $user->headquarters()->sync([]);
            $user->update(['headquarter_id' => null]);

            if ($roleName === 'director') {
                $allPerms = Permission::where('guard_name', 'web')->pluck('name')->toArray();
                $user->syncPermissions($allPerms);
            } else {
                $user->syncPermissions([]);
            }
        }

        $user->syncRoles($roleName ? [$roleName] : []);

        // Permisos por NOMBRE (si el rol no cambió, aplica los seleccionados; si cambió, ya se asignaron)
        if ($roleName === $currentRole) {
            $names = Permission::whereIn('name', $this->selectedPermissionNames)->pluck('name')->all();
            $user->syncPermissions($names);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->dispatch('successAlert', ['message' => 'Rol & permisos actualizados']);
    }

    public function render()
    {
        return view('livewire.users.perms');
    }
}
