<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignSuperAdmin extends Command
{
    protected $signature = 'users:assign-director {username : Username del usuario}';

    protected $description = 'Asigna el rol director a un usuario existente';

    public function handle(): int
    {
        $username = $this->argument('username');

        $user = User::where('username', $username)->first();

        if (!$user) {
            $this->error("Usuario '{$username}' no encontrado.");
            return self::FAILURE;
        }

        Role::firstOrCreate(['name' => 'director', 'guard_name' => 'web']);

        $user->syncRoles(['director']);

        // Asignar todos los permisos
        $allPermissions = \App\Models\Permission::where('guard_name', 'web')->pluck('name')->toArray();
        $user->syncPermissions($allPermissions);

        $this->info("Rol director asignado a '{$user->name}' ({$username}) con todos los permisos.");

        return self::SUCCESS;
    }
}
