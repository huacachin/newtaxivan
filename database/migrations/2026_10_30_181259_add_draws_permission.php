<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso del módulo Sorteos. Va por migración y no por PermissionCatalogSeeder
 * porque ese seeder vacía todos los permisos (y los asignados a cada usuario).
 * Director y gerente ya lo tienen por el Gate::before; al resto se le da desde
 * Usuarios > Permisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'draws',
            'guard_name' => 'web',
            'module' => 'draws',
            'module_label' => 'Sorteos',
            'label' => 'Sorteos',
            'description' => 'Acceso a Sorteos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'draws')->where('guard_name', 'web')->value('id');
        if ($id) {
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
