<?php

/**
 * Helpers de assets con cache-busting automático.
 *
 * Para assets servidos directo desde public/ (los que NO pasan por Vite).
 * Vite ya inyecta su propio hash en el filename, así que NO uses esto para
 * archivos que estén en el entry point @vite([...]).
 *
 * Uso típico (custom.js, script.js, etc.):
 *   <script src="{{ versioned_asset('assets/js/custom.js') }}"></script>
 *   → /assets/js/custom.js?v=1715739012
 *
 * Cuando el archivo se modifica, filemtime cambia, el query string cambia
 * y el navegador descarga la versión nueva. Si el archivo no existe se
 * cae a un fallback con APP_VERSION (o 'dev') para no romper el HTML.
 */

if (!function_exists('versioned_asset')) {
    function versioned_asset(string $path): string
    {
        $clean = ltrim($path, '/');
        $full  = public_path($clean);

        if (is_file($full)) {
            $v = filemtime($full);
        } else {
            // Fallback estable si el archivo aún no existe en el filesystem
            // (por ejemplo en CI antes del deploy).
            $v = config('app.version', env('APP_VERSION', 'dev'));
        }

        return asset($clean) . '?v=' . $v;
    }
}
