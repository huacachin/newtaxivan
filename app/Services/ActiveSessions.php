<?php

namespace App\Services;

use App\Listeners\LogSuccessfulLogin;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sesiones abiertas leídas de la tabla `sessions` (SESSION_DRIVER=database).
 * Las usan Mi perfil (las propias) y Monitoreo (todas, solo director). Con
 * otro driver no hay lista: isAvailable() avisa.
 *
 * Cada sesión se entrega sin su id real (con el id y la clave de la app se
 * puede suplantar la sesión): `key` es su hash y sirve para cerrarla.
 * `current` marca la sesión del navegador que consulta y `active` la que tuvo
 * actividad en los últimos ACTIVE_MINUTES minutos.
 *
 * Cerrar sesiones también borra el remember_token del usuario: si no, la
 * cookie "Recordarme" volvería a abrirla en el siguiente clic.
 */
final class ActiveSessions
{
    public const ACTIVE_MINUTES = 5;

    public static function isAvailable(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Sesiones de un usuario: la actual primero y luego por última actividad.
     *
     * @return Collection<int, array{key: string, user_id: int, current: bool, ip: ?string, agent: array{device: string, os: string, browser: string, text: string}, last_activity: Carbon, active: bool}>
     */
    public static function forUser(int $userId): Collection
    {
        return self::read(fn (Builder $q) => $q->where('user_id', $userId));
    }

    /**
     * Todas las sesiones con usuario, agrupadas por user_id.
     *
     * @return Collection<int, Collection<int, array{key: string, user_id: int, current: bool, ip: ?string, agent: array{device: string, os: string, browser: string, text: string}, last_activity: Carbon, active: bool}>>
     */
    public static function byUser(): Collection
    {
        return self::read(fn (Builder $q) => $q->whereNotNull('user_id'))->groupBy('user_id');
    }

    /**
     * Cierra las sesiones de un usuario (todas, o todas menos la del navegador
     * que consulta). Devuelve cuántas cerró.
     */
    public static function closeForUser(int $userId, bool $keepCurrent = true): int
    {
        if (! self::isAvailable()) {
            return 0;
        }

        $query = self::table()->where('user_id', $userId);
        if ($keepCurrent) {
            $query->where('id', '!=', (string) session()->getId());
        }

        $closed = $query->delete();
        self::forgetRememberToken($userId);

        return $closed;
    }

    /**
     * Cierra UNA sesión por su key (nunca la del navegador que consulta).
     * true si existía y se cerró.
     */
    public static function closeByKey(string $key): bool
    {
        if (! self::isAvailable()) {
            return false;
        }

        $row = self::table()
            ->whereNotNull('user_id')
            ->where('id', '!=', (string) session()->getId())
            ->get(['id', 'user_id'])
            ->first(fn ($session) => hash_equals(self::keyFor((string) $session->id), $key));

        if (! $row || self::table()->where('id', $row->id)->delete() === 0) {
            return false;
        }

        self::forgetRememberToken((int) $row->user_id);

        return true;
    }

    /**
     * Deja el cierre en la auditoría (módulo Sesiones, action session_closed),
     * con el usuario afectado como registro.
     *
     * @param  array<string, mixed>  $data
     */
    public static function logClosure(?User $target, array $data): void
    {
        try {
            $author = auth()->user();

            ActivityLog::create([
                'user_id' => $author?->id,
                'user_name' => $author?->name,
                'user_role' => $author?->roles?->first()?->name,
                'action' => 'session_closed',
                'module' => LogSuccessfulLogin::MODULE,
                'record_id' => $target?->id,
                'old_data' => null,
                'new_data' => ['username' => $target?->username, 'name' => $target?->name] + $data,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('Session closure audit log failed: '.$e->getMessage());
        }
    }

    private static function keyFor(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    /**
     * @param  callable(Builder): Builder  $filter
     */
    private static function read(callable $filter): Collection
    {
        if (! self::isAvailable()) {
            return collect();
        }

        $currentId = (string) session()->getId();
        $activeSince = now()->subMinutes(self::ACTIVE_MINUTES)->timestamp;

        return $filter(self::table())
            ->orderByDesc('last_activity')
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($session) => [
                'key' => self::keyFor((string) $session->id),
                'user_id' => (int) $session->user_id,
                'current' => (string) $session->id === $currentId,
                'ip' => $session->ip_address,
                'agent' => BrowserAgent::describe($session->user_agent),
                'last_activity' => Carbon::createFromTimestamp((int) $session->last_activity, config('app.timezone')),
                'active' => (int) $session->last_activity >= $activeSince,
            ])
            ->sortByDesc(fn (array $session) => [$session['current'], $session['last_activity']->timestamp])
            ->values();
    }

    /**
     * Sin pasar por el modelo: no es un cambio de datos del usuario que deba
     * quedar en la auditoría ni tocar updated_at.
     */
    private static function forgetRememberToken(int $userId): void
    {
        DB::table('users')->where('id', $userId)->update(['remember_token' => null]);
    }

    private static function table(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }
}
