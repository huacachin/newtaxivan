<?php

namespace App\Livewire\Users;

use App\Models\User;
use App\Services\ActiveSessions;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public $search = '';

    public function questionDelete(int $id, string $role = '', string $name = ''): void
    {
        $this->dispatch('questionDelete', ['id' => $id, 'role' => $role, 'name' => $name]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        $authUser = auth()->user();
        $user = User::with('roles')->findOrFail($id);

        if (! $authUser->canManageUser($user)) {
            abort(403);
        }

        $user->update(['status' => 'inactive']);

        // El login ya no lo deja entrar, pero una sesión abierta seguiría sirviendo hasta caducar.
        $closed = ActiveSessions::closeForUser($user->id, keepCurrent: true);
        if ($closed > 0) {
            ActiveSessions::logClosure($user, [
                'scope' => 'Todas sus sesiones (al desactivar el usuario)',
                'sessions_closed' => $closed,
            ]);
        }

        $this->dispatch('successAlert', ['message' => 'Usuario desactivado correctamente'.($closed > 0 ? " y {$closed} sesión(es) suya(s) cerrada(s)" : '')]);
    }

    public function export(): void
    {
        $route = route('exports.users', ['search' => $this->search]);
        $this->dispatch('url-open', ['url' => $route]);
    }

    public function render()
    {
        $term = trim((string) $this->search);
        $authUser = auth()->user();
        $authLevel = $authUser->getRoleLevel();

        $visibleRoles = collect(User::ROLE_HIERARCHY)
            ->filter(fn ($level) => $authUser->isDirector() ? $level <= $authLevel : $level < $authLevel)
            ->keys()
            ->toArray();

        $users = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $visibleRoles))
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
            ->with(['headquarter', 'headquarters', 'roles', 'permissions'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('livewire.users.index', compact('users'));
    }
}
