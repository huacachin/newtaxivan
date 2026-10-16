<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Sin permiso (403): a Salidas. Si tampoco puede ver Salidas (o el 403 salió de ahí), a Mi perfil
        // con un aviso; mandar siempre a Salidas dejaba en un bucle de redirecciones a quien no tiene ese permiso.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 403) {
                return $response;
            }

            $user = $request->user();
            if (! $user) {
                return redirect()->route('login');
            }
            if (! $request->routeIs('departures.index') && $user->can('departures')) {
                return redirect()->route('departures.index');
            }
            if (! $request->routeIs('profile')) {
                return redirect()->route('profile')->with('noAccess', 'No tienes permiso para entrar a esa pantalla. Pide al Director que revise tus permisos.');
            }

            return $response;
        });
    })->create();
