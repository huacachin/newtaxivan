<?php

namespace App\Traits;

use App\Models\ActivityLog;
use App\Models\SecurityKey;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Para los componentes que asignan roles (crear y editar usuario): dar o quitar
 * el rol Director exige la clave de seguridad (SecurityKey::DIRECTOR_ROLE).
 * Antes de guardar, con el rol actual, el nuevo y la clave tal como llegó:
 *
 *     if ($this->directorRoleChangeBlocked($currentRole, $newRole, $user, $directorKey)) { return; }
 *
 * Sin clave configurada, vacía o incorrecta avisa en el campo directorKey, deja
 * el intento fallido en la auditoría y frena tras cinco fallos en diez minutos.
 * Los cambios entre otros roles no la piden. Después de guardar,
 * logRoleChange() deja el cambio de rol en la auditoría.
 *
 * La clave NO es estado del componente: llega como parámetro de la acción
 * (desde el DOM) para que nunca vuelva al navegador en el snapshot de Livewire.
 */
trait ProtectsDirectorRole
{
    public const DIRECTOR_KEY_MAX_ATTEMPTS = 5;

    public const DIRECTOR_KEY_LOCK_SECONDS = 600;

    /**
     * El cambio entra o sale de Director (null = sin rol).
     */
    public static function isProtectedRoleChange(?string $currentRole, ?string $newRole): bool
    {
        return $currentRole !== $newRole && ($currentRole === 'director' || $newRole === 'director');
    }

    /**
     * true (y ya avisó) cuando el cambio necesita la clave y no se dio la correcta.
     */
    protected function directorRoleChangeBlocked(?string $currentRole, ?string $newRole, ?User $target, string $directorKey): bool
    {
        if (! self::isProtectedRoleChange($currentRole, $newRole)) {
            return false;
        }

        if (! SecurityKey::isDefined(SecurityKey::DIRECTOR_ROLE)) {
            $this->addError('directorKey', 'No hay clave de seguridad configurada para dar o quitar el rol Director. Hay que crearla con el comando users:director-key.');

            return true;
        }

        $limiterKey = 'director-key:'.(auth()->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($limiterKey, self::DIRECTOR_KEY_MAX_ATTEMPTS)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($limiterKey) / 60));
            $this->addError('directorKey', "Demasiados intentos con la clave de seguridad. Espera {$minutes} minuto(s).");

            return true;
        }

        $attempt = trim($directorKey);
        if ($attempt === '') {
            $this->addError('directorKey', 'Para dar o quitar el rol Director hace falta la clave de seguridad.');

            return true;
        }

        if (! SecurityKey::verify(SecurityKey::DIRECTOR_ROLE, $attempt)) {
            RateLimiter::hit($limiterKey, self::DIRECTOR_KEY_LOCK_SECONDS);
            $this->writeRoleAudit('role_change_denied', $target, [
                'username' => $target?->username ?? $this->username ?? null,
                'role_before' => $currentRole,
                'role_after' => $newRole,
                'attempts' => RateLimiter::attempts($limiterKey),
            ]);
            $this->addError('directorKey', 'Clave de seguridad incorrecta.');

            return true;
        }

        RateLimiter::clear($limiterKey);

        return false;
    }

    /**
     * Deja el cambio de rol en la auditoría (nada si el rol no cambió).
     */
    protected function logRoleChange(User $user, ?string $currentRole, ?string $newRole): void
    {
        if ($currentRole === $newRole) {
            return;
        }

        $this->writeRoleAudit('role_changed', $user, [
            'username' => $user->username,
            'role_before' => $currentRole,
            'role_after' => $newRole,
            'security_key' => self::isProtectedRoleChange($currentRole, $newRole) ? 'Sí' : 'No',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeRoleAudit(string $action, ?User $target, array $data): void
    {
        try {
            $author = auth()->user();

            ActivityLog::create([
                'user_id' => $author?->id,
                'user_name' => $author?->name,
                'user_role' => $author?->roles?->first()?->name,
                'action' => $action,
                'module' => 'Usuarios',
                'record_id' => $target?->id,
                'old_data' => null,
                'new_data' => $data,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('Role audit log failed: '.$e->getMessage());
        }
    }
}
