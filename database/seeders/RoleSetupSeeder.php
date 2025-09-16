<?php

namespace Database\Seeders;

// database/seeders/RoleSetupSeeder.php
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSetupSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        // Crea roles si no existen
        Role::firstOrCreate(['name'=>'admin','guard_name'=>$guard]);
        Role::firstOrCreate(['name'=>'controller','guard_name'=>$guard]);

        // Opcional: asignar un rol a un usuario específico
        // use App\Models\User;
        // if ($u = \App\Models\User::where('username', 'TU_USERNAME')->first()) {
        //     $u->syncRoles(['admin']); // o ['controller']
        // }
    }
}
