<?php

namespace App\Livewire\Monitoring;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActiveSessions;
use App\Services\IpColor;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Monitoreo (solo Director): una tarjeta por usuario con sus sesiones
 * abiertas (dispositivo, navegador, IP, última actividad), todos sus accesos
 * y sus intentos fallidos de la semana, ambos de la auditoría. Cada IP lleva
 * su propio color para ver a primera vista si alguien entró desde IPs
 * distintas. El director puede cerrar una sesión o todas las de un usuario,
 * nunca la suya desde la que mira; cada cierre queda en la auditoría. Solo
 * usuarios activos: un usuario desactivado aparece únicamente mientras le
 * quede una sesión abierta, para poder cerrársela.
 */
class Sessions extends Component
{
    public const FAILED_LOGIN_DAYS = 7;

    public const REFRESH_SECONDS = 30;

    /** Tope de accesos leídos de la auditoría por petición (en producción son unos cientos). */
    private const LOGIN_ROWS_LIMIT = 5000;

    /** Usuario elegido en el desplegable de activos ('' = todos). */
    public string $selectedUserId = '';

    public bool $onlyWithSession = false;

    /** Rango de fechas de los accesos (Y-m-d, vacío = sin límite). */
    public string $dateFrom = '';

    public string $dateTo = '';

    /** Accesos de la auditoría, leídos una sola vez por petición. */
    private ?Collection $loginRowsCache = null;

    public function mount(): void
    {
        $this->ensureDirector();
    }

    public function clearDates(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
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
     * Usuarios con sus sesiones, accesos e intentos fallidos: primero los que
     * tienen más sesiones abiertas, luego por nombre.
     *
     * @return Collection<int, array{user: User, role: ?string, sessions: Collection, last_activity: ?Carbon, logins: Collection, ips: Collection<string, int>, failed: int}>
     */
    private function blocks(): Collection
    {
        $sessions = ActiveSessions::byUser();
        $logins = $this->logins();
        $failed = $this->failedLogins();

        return User::with('roles')
            ->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $sessions->keys()->all()))
            ->when($this->selectedUserId !== '', fn ($q) => $q->where('id', (int) $this->selectedUserId))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($sessions, $logins, $failed) {
                $userSessions = $sessions->get($user->id, collect());
                $userLogins = $logins->get($user->id, collect());

                return [
                    'user' => $user,
                    'role' => $user->roles->first()?->name,
                    'sessions' => $userSessions,
                    'last_activity' => $userSessions->sortByDesc(fn (array $session) => $session['last_activity']->timestamp)->first()['last_activity'] ?? null,
                    'logins' => $userLogins,
                    // IPs distintas de sus accesos con cuántas veces entró desde cada una (la más usada primero).
                    'ips' => $userLogins->pluck('ip')->filter()->countBy()->sortDesc(),
                    'failed' => (int) ($failed[$user->id] ?? 0),
                ];
            })
            ->filter(fn (array $block) => ! $this->onlyWithSession || $block['sessions']->isNotEmpty())
            ->sortBy([
                fn (array $a, array $b) => $b['sessions']->count() <=> $a['sessions']->count(),
                fn (array $a, array $b) => strcasecmp((string) $a['user']->name, (string) $b['user']->name),
            ])
            ->values();
    }

    /**
     * Todos los accesos por usuario dentro del rango de fechas (auditoría
     * action=login), del más reciente al más viejo: fecha, dispositivo e IP.
     *
     * @return Collection<int, Collection<int, array{date: Carbon, ip: ?string, agent: string, method: ?string}>>
     */
    private function logins(): Collection
    {
        [$from, $to] = $this->dateRange();

        return $this->loginRows()
            ->when($from, fn (Collection $rows) => $rows->filter(fn (ActivityLog $log) => $log->created_at?->gte($from)))
            ->when($to, fn (Collection $rows) => $rows->filter(fn (ActivityLog $log) => $log->created_at?->lte($to)))
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->map(fn (ActivityLog $log) => [
                'date' => $log->created_at,
                'ip' => $log->ip_address,
                'agent' => collect([$log->new_data['device'] ?? null, $log->new_data['os'] ?? null, $log->new_data['browser'] ?? null])->filter()->implode(' · ') ?: '—',
                'method' => $log->new_data['method'] ?? null,
            ])->values());
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    private function loginRows(): Collection
    {
        return $this->loginRowsCache ??= ActivityLog::query()
            ->where('action', 'login')
            ->whereNotNull('user_id')
            ->orderByDesc('id')
            ->limit(self::LOGIN_ROWS_LIMIT)
            ->get(['id', 'user_id', 'ip_address', 'new_data', 'created_at']);
    }

    /**
     * Un color por IP distinta, asignado por orden de primera aparición (la
     * más antigua primero) sobre TODOS los accesos, no solo los del rango:
     * así una IP nueva no cambia los colores de las de antes y cada IP
     * conserva su color al cambiar las fechas.
     *
     * @return array<string, array{bg: string, border: string, text: string}>
     */
    private function ipPalette(): array
    {
        return IpColor::palette($this->loginRows()->reverse()->pluck('ip_address'));
    }

    /**
     * Rango de los accesos [desde 00:00, hasta 23:59:59] en hora de Lima; un
     * extremo vacío o mal escrito no filtra y, si vienen al revés, se invierten.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dateRange(): array
    {
        $from = $this->parseDate($this->dateFrom)?->startOfDay();
        $to = $this->parseDate($this->dateTo)?->endOfDay();

        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        // Una fecha imposible (2026-02-31) se desborda al mes siguiente: no filtra.
        return $date && $date->format('Y-m-d') === $value ? $date : null;
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
            'ipPalette' => $this->ipPalette(),
            'activeUsers' => User::where('status', 'active')->orderBy('username')->get(['id', 'username', 'name']),
            'range' => $this->dateRange(),
            'available' => ActiveSessions::isAvailable(),
            'totalSessions' => $blocks->sum(fn (array $block) => $block['sessions']->count()),
            'usersWithSession' => $blocks->filter(fn (array $block) => $block['sessions']->isNotEmpty())->count(),
            'activeNow' => $blocks->sum(fn (array $block) => $block['sessions']->where('active', true)->count()),
            'failedThisWeek' => $blocks->sum(fn (array $block) => $block['failed']),
        ]);
    }
}
