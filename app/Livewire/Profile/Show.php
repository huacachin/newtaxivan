<?php

namespace App\Livewire\Profile;

use App\Models\User;
use App\Services\ActiveSessions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Mi perfil (cualquier usuario logueado): sus datos en solo lectura (los
 * cambia quien administra usuarios) y sus sesiones activas, con la opción de
 * cerrar todas las demás confirmando con su contraseña.
 *
 * La contraseña NO es estado del componente: llega como parámetro de la
 * acción (desde el DOM) para que nunca vuelva al navegador en el snapshot de
 * Livewire tras un intento fallido.
 */
class Show extends Component
{
    public const PASSWORD_MAX_ATTEMPTS = 5;

    public const PASSWORD_LOCK_SECONDS = 600;

    /**
     * Cierra todas las sesiones del usuario menos esta, con su contraseña.
     */
    public function closeOtherSessions(string $password = ''): void
    {
        $limiterKey = 'close-sessions:'.auth()->id();
        if (RateLimiter::tooManyAttempts($limiterKey, self::PASSWORD_MAX_ATTEMPTS)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($limiterKey) / 60));
            $this->addError('sessionsPassword', "Demasiados intentos. Espera {$minutes} minuto(s).");

            return;
        }

        $validator = validator(
            ['sessionsPassword' => $password],
            ['sessionsPassword' => ['required', 'current_password']],
            [
                'sessionsPassword.required' => 'Escribe tu contraseña.',
                'sessionsPassword.current_password' => 'La contraseña no es correcta.',
            ],
        );
        if ($validator->fails()) {
            RateLimiter::hit($limiterKey, self::PASSWORD_LOCK_SECONDS);

            throw new ValidationException($validator);
        }
        RateLimiter::clear($limiterKey);

        if (! ActiveSessions::isAvailable()) {
            $this->dispatch('errorAlert', ['message' => 'Las sesiones no se guardan en la base de datos: no se pueden cerrar desde aquí.']);

            return;
        }

        $user = $this->user();
        $closed = ActiveSessions::closeForUser($user->id, keepCurrent: true);
        if ($closed > 0) {
            ActiveSessions::logClosure($user, [
                'scope' => 'Sus otras sesiones (desde Mi perfil)',
                'sessions_closed' => $closed,
            ]);
        }

        $this->dispatch('profile-sessions-closed');
        $this->dispatch('successAlert', ['message' => $closed === 0 ? 'No había otras sesiones abiertas.' : "Se cerraron {$closed} sesión(es) más."]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render()
    {
        $user = $this->user()->load(['roles', 'headquarter', 'headquarters']);

        return view('livewire.profile.show', [
            'user' => $user,
            'role' => $user->roles->first()?->name,
            'sessions' => ActiveSessions::forUser($user->id),
            'available' => ActiveSessions::isAvailable(),
        ]);
    }
}
