<?php

namespace App\Livewire\Monitoring;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActiveSessions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Monitoreo (solo Director): un bloque por usuario con sus sesiones abiertas
 * (dispositivo, navegador, IP, última actividad), sus últimos accesos y sus
 * intentos fallidos de la semana, ambos de la auditoría. El director puede
 * cerrar una sesión o todas las de un usuario, nunca la suya desde la que
 * mira; cada cierre queda en la auditoría. Solo usuarios activos: un usuario
 * desactivado aparece únicamente mientras le quede una sesión abierta, para
 * poder cerrársela.
 */
class Sessions extends Component
{
    public const RECENT_LOGINS_PER_USER = 8;

    public const FAILED_LOGIN_DAYS = 7;

    public string $search = '';

    public bool $onlyWithSession = false;

    public function mount(): void
    {
        $this->ensureDirector();
    }

    /**
     * Cierra UNA sesión de un usuario (nunca la del director que mira).
     */
    public function closeSession(string $key): void
    {
        $this->ensureDirector();

        $session = ActiveSessions::byUser()->flatten(1)->firstWhere('key', $key);
        if ($session && $session['current']) {
            $this->dispatch('errorAlert', ['message' => 'Esa es tu sesión actual: ciérrala con Salir.']);

            return;
        }
        if (! $session || ! ActiveSessions::closeByKey($key)) {
            $this->dispatch('errorAlert', ['message' => 'Esa sesión ya estaba cerrada.']);

            return;
        }

        $user = User::find($session['user_id']);
        ActiveSessions::logClosure($user, [
            'scope' => 'Una sesión (desde Monitoreo)',
            'sessions_closed' => 1,
            'device' => $session['agent']['device'],
            'os' => $session['agent']['os'],
            'browser' => $session['agent']['browser'],
            'ip' => $session['ip'],
            'last_activity' => $session['last_activity']->format('d/m/Y H:i:s'),
        ]);
        $this->dispatch('successAlert', ['message' => 'Sesión cerrada: '.($user?->username ?? '').' · '.$session['agent']['text'].'.']);
    }

    /**
     * Cierra TODAS las sesiones de un usuario (si es el propio director, todas
     * menos la que mira).
     */
    public function closeAll(int $userId): void
    {
        $this->ensureDirector();

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $closed = ActiveSessions::closeForUser($user->id, keepCurrent: true);
        if ($closed === 0) {
            $this->dispatch('errorAlert', ['message' => "{$user->username} no tenía sesiones abiertas."]);

            return;
        }

        ActiveSessions::logClosure($user, [
            'scope' => 'Todas sus sesiones (desde Monitoreo)',
            'sessions_closed' => $closed,
        ]);
        $this->dispatch('successAlert', ['message' => "Se cerraron {$closed} sesión(es) de {$user->username}."]);
    }

    /**
     * Usuarios con sus sesiones, accesos recientes e intentos fallidos:
     * primero los que tienen más sesiones abiertas, luego por nombre.
     *
     * @return Collection<int, array{user: User, role: ?string, sessions: Collection, logins: Collection, failed: int}>
     */
    private function blocks(): Collection
    {
        $sessions = ActiveSessions::byUser();
        $logins = $this->recentLogins();
        $failed = $this->failedLogins();
        $term = trim($this->search);

        return User::with('roles')
            ->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $sessions->keys()->all()))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'user' => $user,
                'role' => $user->roles->first()?->name,
                'sessions' => $sessions->get($user->id, collect()),
                'logins' => $logins->get($user->id, collect()),
                'failed' => (int) ($failed[$user->id] ?? 0),
            ])
            ->filter(fn (array $block) => ! $this->onlyWithSession || $block['sessions']->isNotEmpty())
            ->sortBy([
                fn (array $a, array $b) => $b['sessions']->count() <=> $a['sessions']->count(),
                fn (array $a, array $b) => strcasecmp((string) $a['user']->name, (string) $b['user']->name),
            ])
            ->values();
    }

    /**
     * Últimos accesos por usuario (auditoría action=login): fecha, dispositivo e IP.
     *
     * @return Collection<int, Collection<int, array{date: \Carbon\Carbon, ip: ?string, agent: string, method: ?string}>>
     */
    private function recentLogins(): Collection
    {
        return ActivityLog::query()
            ->where('action', 'login')
            ->whereNotNull('user_id')
            ->orderByDesc('id')
            ->limit(600)
            ->get(['id', 'user_id', 'ip_address', 'new_data', 'created_at'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->take(self::RECENT_LOGINS_PER_USER)->map(fn (ActivityLog $log) => [
                'date' => $log->created_at,
                'ip' => $log->ip_address,
                'agent' => collect([$log->new_data['device'] ?? null, $log->new_data['os'] ?? null, $log->new_data['browser'] ?? null])->filter()->implode(' · ') ?: '—',
                'method' => $log->new_data['method'] ?? null,
            ])->values());
    }

    /**
     * Intentos fallidos de los últimos días por usuario (auditoría action=login_failed).
     *
     * @return array<int, int>
     */
    private function failedLogins(): array
    {
        return ActivityLog::query()
            ->where('action', 'login_failed')
            ->whereNotNull('record_id')
            ->where('created_at', '>=', now()->subDays(self::FAILED_LOGIN_DAYS))
            ->groupBy('record_id')
            ->pluck(DB::raw('count(*)'), 'record_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function ensureDirector(): void
    {
        abort_unless(auth()->user()?->isDirector() ?? false, 403, 'Monitoreo es solo para el Director.');
    }

    public function render()
    {
        $blocks = $this->blocks();

        return view('livewire.monitoring.sessions', [
            'blocks' => $blocks,
            'available' => ActiveSessions::isAvailable(),
            'totalSessions' => $blocks->sum(fn (array $block) => $block['sessions']->count()),
            'usersWithSession' => $blocks->filter(fn (array $block) => $block['sessions']->isNotEmpty())->count(),
        ]);
    }
}
