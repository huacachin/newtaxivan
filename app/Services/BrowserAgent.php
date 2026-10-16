<?php

namespace App\Services;

/**
 * Lectura del user agent: tipo de dispositivo, sistema operativo y navegador
 * con su versión mayor. La usan la auditoría de accesos (LogSuccessfulLogin,
 * LogFailedLogin), Mi perfil y Monitoreo. Sin dependencias.
 */
final class BrowserAgent
{
    /**
     * @return array{device: string, os: string, browser: string, text: string}
     */
    public static function describe(?string $userAgent): array
    {
        $ua = (string) $userAgent;
        $os = self::os($ua);
        $browser = self::browser($ua);

        return [
            'device' => self::device($ua),
            'os' => $os,
            'browser' => $browser,
            'text' => "{$browser} · {$os}",
        ];
    }

    private static function device(string $ua): string
    {
        if ($ua === '') {
            return 'Desconocido';
        }
        if (preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua)) {
            return 'Tablet';
        }
        if (preg_match('/Mobile|iPhone|iPod|Android|BlackBerry|Opera Mini|IEMobile/i', $ua)) {
            return 'Celular';
        }

        return 'Computadora';
    }

    private static function os(string $ua): string
    {
        // iOS antes que macOS ("like Mac OS X") y Android antes que Linux.
        if (preg_match('/Android ([\d.]+)/i', $ua, $m)) {
            return 'Android '.$m[1];
        }
        if (preg_match('/(?:iPhone|iPad|iPod).*? OS ([\d_]+)/i', $ua, $m)) {
            return 'iOS '.str_replace('_', '.', $m[1]);
        }
        if (preg_match('/Windows NT 10/i', $ua)) {
            return 'Windows 10/11';
        }
        if (stripos($ua, 'Windows') !== false) {
            return 'Windows';
        }
        if (stripos($ua, 'CrOS') !== false) {
            return 'ChromeOS';
        }
        if (stripos($ua, 'Mac OS X') !== false) {
            return 'macOS';
        }
        if (stripos($ua, 'Linux') !== false) {
            return 'Linux';
        }

        return 'Desconocido';
    }

    private static function browser(string $ua): string
    {
        // El orden importa: Edge/Opera/Samsung tambien contienen "Chrome" y
        // Chrome contiene "Safari".
        $patterns = [
            '/Edg(?:e|A|iOS)?\/([\d]+)/i' => 'Edge',
            '/(?:OPR|Opera)\/([\d]+)/i' => 'Opera',
            '/SamsungBrowser\/([\d]+)/i' => 'Samsung Internet',
            '/(?:Chrome|CriOS)\/([\d]+)/i' => 'Chrome',
            '/(?:Firefox|FxiOS)\/([\d]+)/i' => 'Firefox',
            '/Version\/([\d]+).*Safari/i' => 'Safari',
        ];

        foreach ($patterns as $regex => $name) {
            if (preg_match($regex, $ua, $m)) {
                return $name.' '.$m[1];
            }
        }

        return 'Desconocido';
    }
}
