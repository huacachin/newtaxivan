<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\BrowserAgent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Str;

/**
 * Registra en auditoria (action='login_failed') cada intento de acceso fallido:
 * usuario escrito, motivo, dispositivo e IP. Si el usuario existe queda como
 * record_id, y Monitoreo cuenta sus intentos de la semana.
 *
 * Se descubre automaticamente (event discovery de Laravel 11): NO registrarlo
 * tambien con Event::listen o se guardaria dos veces.
 */
class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        try {
            $request = request();
            $userAgent = (string) $request->userAgent();
            $agent = BrowserAgent::describe($userAgent);
            $username = Str::limit((string) ($event->credentials['username'] ?? ''), 100);
            $target = $username !== '' ? User::where('username', $username)->first(['id', 'status']) : null;

            ActivityLog::create([
                'user_id'    => null,
                'user_name'  => null,
                'user_role'  => null,
                'action'     => 'login_failed',
                'module'     => LogSuccessfulLogin::MODULE,
                'record_id'  => $target?->id,
                'old_data'   => null,
                'new_data'   => [
                    'username' => $username,
                    'reason'   => match (true) {
                        $target === null => 'El usuario no existe',
                        $target->status !== 'active' => 'Usuario desactivado',
                        default => 'Contraseña incorrecta',
                    },
                    'device'   => $agent['device'],
                    'os'       => $agent['os'],
                    'browser'  => $agent['browser'],
                ],
                'changed_fields' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $userAgent ?: null,
            ]);
        } catch (\Throwable $e) {
            // Nunca bloquear el login por un fallo de auditoria
            logger()->error('Failed login audit log failed: '.$e->getMessage());
        }
    }
}
