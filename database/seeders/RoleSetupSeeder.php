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
        Role::firstOrCreate(['name'=>'director','guard_name'=>$guard]);
        Role::firstOrCreate(['name'=>'gerente','guard_name'=>$guard]);
        Role::firstOrCreate(['name'=>'administrador','guard_name'=>$guard]);
        Role::firstOrCreate(['name'=>'supervisor','guard_name'=>$guard]);
        Role::firstOrCreate(['name'=>'controlador','guard_name'=>$guard]);
    }
}
