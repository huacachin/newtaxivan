<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User; // ajusta si tu modelo vive en otro namespace

class GrantAdminToUserOneSeeder extends Seeder
{
    public function run(): void
    {
        // Limpia la caché de permisos/roles
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web'; // ajusta si usas otro guard

        // Asegura que exista el rol admin con el guard correcto
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => $guard],
            []
        );

        // Obtén todos los permisos (del mismo guard)
        $allPermissions = Permission::where('guard_name', $guard)->get();

        // Sincroniza todos los permisos al rol admin
        $adminRole->syncPermissions($allPermissions);

        // Busca al usuario ID=1
        $user = User::find(1);
        if (! $user) {
            $this->command->warn('Usuario con ID 1 no existe.');
            return;
        }

        // Asegura que el guard del usuario sea compatible
        // (normalmente 'web'; si usas multi-guard, verifica)
        // Asigna el rol admin al usuario
        if (! $user->hasRole($adminRole->name)) {
            $user->assignRole($adminRole->name);
        }

        // Dale también todos los permisos directos (opcional)
        // Si solo quieres que los herede por rol, puedes omitir esto.
        $user->syncPermissions($allPermissions);

        // Limpia nuevamente la caché
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Usuario 1 ahora es admin y tiene todos los permisos.');
    }
}
