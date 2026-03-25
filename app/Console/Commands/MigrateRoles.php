<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use App\Models\User;

class MigrateRoles extends Command
{
    protected $signature = 'users:migrate-roles';

    protected $description = 'Migra usuarios de roles antiguos a los nuevos (admin→administrador, controller→controlador, superadmin→director)';

    public function handle(): int
    {
        $guard = 'web';

        // Crear nuevos roles si no existen
        Role::firstOrCreate(['name' => 'director', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'gerente', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'controlador', 'guard_name' => $guard]);

        $map = [
            'superadmin' => 'director',
            'admin'      => 'administrador',
            'controller' => 'controlador',
        ];

        $total = 0;

        foreach ($map as $oldRole => $newRole) {
            if (!Role::where('name', $oldRole)->where('guard_name', $guard)->exists()) {
                $this->line("  Rol '{$oldRole}' no existe, omitido");
                continue;
            }

            $users = User::role($oldRole)->get();

            foreach ($users as $user) {
                $user->syncRoles([$newRole]);
                $this->line("  {$user->name} ({$user->username}): {$oldRole} → {$newRole}");
                $total++;
            }
        }

        $this->info("Usuarios migrados: {$total}");

        // Eliminar roles viejos
        foreach (array_keys($map) as $oldRole) {
            $role = Role::where('name', $oldRole)->where('guard_name', $guard)->first();
            if ($role) {
                $role->delete();
                $this->line("  Rol '{$oldRole}' eliminado");
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Migración de roles completada.');

        return self::SUCCESS;
    }
}
