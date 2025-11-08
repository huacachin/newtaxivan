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

    public function mount(int $id)
    {
        $this->user =  User::with(['headquarters','roles'])->findOrFail($id);
        $this->permsUserName = $this->user->name;
        $this->roles = Role::orderBy('name')->get(['id','name']);

        $this->selectedRoleId = $this->user->roles()->value('id');
        $this->selectedPermissionNames = $this->user->permissions()->pluck('name')->toArray();

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
        ksort($groups);
        $this->aclGroups = $groups;
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
        $user = $this->user;

        // Rol único
        $roleName = null;
        if ($this->selectedRoleId) {
            $roleName = collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
        }
        $user->syncRoles($roleName ? [$roleName] : []);

        // Permisos por NOMBRE
        $names = Permission::whereIn('name', $this->selectedPermissionNames)->pluck('name')->all();
        $user->syncPermissions($names);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->dispatch('successAlert', ['message' => 'Rol & permisos actualizados']);
    }

    public function render()
    {
        return view('livewire.users.perms');
    }
}
