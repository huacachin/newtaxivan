<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Services\BrowserAgent;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

/**
 * Registra en auditoria (action='login') cada inicio de sesion exitoso.
 *
 * Laravel dispara Login tanto en el formulario (Auth::attempt) como cuando la
 * sesion expiro y el usuario vuelve a entrar por la cookie "Recordarme"; ambos
 * casos son un acceso y se distinguen en el campo 'method'.
 *
 * Se descubre automaticamente (event discovery de Laravel 11): NO registrarlo
 * tambien con Event::listen o se guardaria dos veces.
 */
class LogSuccessfulLogin
{
    public const MODULE = 'Sesiones';

    public function handle(Login $event): void
    {
        try {
            $user = $event->user;
            $request = request();
            $userAgent = (string) $request->userAgent();

            $viaRemember = Auth::guard($event->guard)->viaRemember();
            $agent = BrowserAgent::describe($userAgent);

            ActivityLog::create([
                'user_id'    => $user->id,
                'user_name'  => $user->name,
                'user_role'  => $user->roles?->first()?->name,
                'action'     => 'login',
                'module'     => self::MODULE,
                'record_id'  => $user->id,
                'old_data'   => null,
                'new_data'   => [
                    'username'        => $user->username,
                    'name'            => $user->name,
                    'email'           => $user->email,
                    'document_type'   => $user->document_type,
                    'document_number' => $user->document_number,
                    'phone'           => $user->phone,
                    'role'            => $user->roles?->first()?->name,
                    'headquarter'     => $user->headquarter?->name,
                    'headquarters'    => $user->headquarters()->orderBy('name')->pluck('name')->implode(', ') ?: null,
                    'status'          => $user->status,
                    'method'          => $viaRemember ? 'Recordarme (automático)' : 'Formulario',
                    'remember'        => $event->remember ? 'Sí' : 'No',
                    'device'          => $agent['device'],
                    'os'              => $agent['os'],
                    'browser'         => $agent['browser'],
                ],
                'changed_fields' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $userAgent ?: null,
            ]);
        } catch (\Throwable $e) {
            // Nunca bloquear el login por un fallo de auditoria
            logger()->error('Login audit log failed: ' . $e->getMessage());
        }
    }
}
