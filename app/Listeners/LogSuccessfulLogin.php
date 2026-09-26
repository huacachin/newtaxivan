<?php

namespace App\Listeners;

use App\Models\ActivityLog;
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
                    'device'          => $this->device($userAgent),
                    'os'              => $this->os($userAgent),
                    'browser'         => $this->browser($userAgent),
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

    protected function device(string $ua): string
    {
        if ($ua === '') return 'Desconocido';
        if (preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua)) return 'Tablet';
        if (preg_match('/Mobile|iPhone|iPod|Android|BlackBerry|Opera Mini|IEMobile/i', $ua)) return 'Celular';
        return 'Computadora';
    }

    protected function os(string $ua): string
    {
        // iOS antes que macOS ("like Mac OS X") y Android antes que Linux.
        if (preg_match('/Android ([\d.]+)/i', $ua, $m)) return 'Android ' . $m[1];
        if (preg_match('/(?:iPhone|iPad|iPod).*? OS ([\d_]+)/i', $ua, $m)) return 'iOS ' . str_replace('_', '.', $m[1]);
        if (preg_match('/Windows NT 10/i', $ua)) return 'Windows 10/11';
        if (stripos($ua, 'Windows') !== false) return 'Windows';
        if (stripos($ua, 'CrOS') !== false) return 'ChromeOS';
        if (stripos($ua, 'Mac OS X') !== false) return 'macOS';
        if (stripos($ua, 'Linux') !== false) return 'Linux';
        return 'Desconocido';
    }

    protected function browser(string $ua): string
    {
        // El orden importa: Edge/Opera/Samsung tambien contienen "Chrome" y
        // Chrome contiene "Safari".
        $patterns = [
            '/Edg(?:e|A|iOS)?\/([\d]+)/i'   => 'Edge',
            '/(?:OPR|Opera)\/([\d]+)/i'     => 'Opera',
            '/SamsungBrowser\/([\d]+)/i'    => 'Samsung Internet',
            '/(?:Chrome|CriOS)\/([\d]+)/i'  => 'Chrome',
            '/(?:Firefox|FxiOS)\/([\d]+)/i' => 'Firefox',
            '/Version\/([\d]+).*Safari/i'   => 'Safari',
        ];

        foreach ($patterns as $regex => $name) {
            if (preg_match($regex, $ua, $m)) {
                return $name . ' ' . $m[1];
            }
        }

        return 'Desconocido';
    }
}
