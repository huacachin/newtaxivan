<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission as Perm;

class PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        // 1) Limpia caché de Spatie
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 2) Borrar permisos antiguos (con pivotes) de forma segura
        Schema::disableForeignKeyConstraints();
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('permissions')->truncate();
        Schema::enableForeignKeyConstraints();

        // 3) Definir NUEVO catálogo (sin CRUD)
        //    - Padres (solo título): Configuración, Deudas, Caja
        //    - Permisos "single": dashboard, departures, payments
        //    - Hijos:
        //        configuracion.*  | debts.*        | cash.*
        $items = [
            // Singles
            ['name' => 'dashboard',  'label' => 'Dashboard', 'module' => 'dashboard', 'module_label' => 'Dashboard', 'description' => 'Acceso al Dashboard'],
            ['name' => 'departures', 'label' => 'Salidas',   'module' => 'departures','module_label' => 'Salidas',   'description' => 'Acceso a Salidas'],
            ['name' => 'payments',   'label' => 'Pagos',     'module' => 'payments',  'module_label' => 'Pagos',     'description' => 'Acceso a Pagos'],

            // Configuración (hijos)
            ['name' => 'configuracion.vehicles',        'label' => 'Vehículos',     'module' => 'configuracion','module_label' => 'Configuración', 'description' => 'Acceso a Vehículos'],
            ['name' => 'configuracion.owners',          'label' => 'Propietarios',  'module' => 'configuracion','module_label' => 'Configuración', 'description' => 'Acceso a Propietarios'],
            ['name' => 'configuracion.drivers',         'label' => 'Conductores',   'module' => 'configuracion','module_label' => 'Configuración', 'description' => 'Acceso a Conductores'],
            ['name' => 'configuracion.cost-per-plate',  'label' => 'Costo por Placa','module' => 'configuracion','module_label' => 'Configuración','description' => 'Acceso a Costo por Placa'],
            ['name' => 'configuracion.users',           'label' => 'Usuarios',      'module' => 'configuracion','module_label' => 'Configuración', 'description' => 'Acceso a Usuarios'],
            ['name' => 'configuracion.concepts',        'label' => 'Conceptos',     'module' => 'configuracion','module_label' => 'Configuración', 'description' => 'Acceso a Conceptos'],
            ['name' => 'configuracion.headquarters',    'label' => 'Sucursales',    'module' => 'configuracion','module_label' => 'Configuración', 'description' => 'Acceso a Sucursales'],

            // Deudas (hijos)
            ['name' => 'debts.days',    'label' => 'Deuda x Días',  'module' => 'debts', 'module_label' => 'Deudas', 'description' => 'Acceso a Deuda por Días'],
            ['name' => 'debts.monthly', 'label' => 'Deuda Mensual', 'module' => 'debts', 'module_label' => 'Deudas', 'description' => 'Acceso a Deuda Mensual'],

            // Caja (hijos)
            ['name' => 'cash.incomes',  'label' => 'Ingreso',  'module' => 'cash', 'module_label' => 'Caja', 'description' => 'Acceso a Ingresos'],
            ['name' => 'cash.expenses', 'label' => 'Egreso',   'module' => 'cash', 'module_label' => 'Caja', 'description' => 'Acceso a Egresos'],
            ['name' => 'cash.reports',  'label' => 'Reportes', 'module' => 'cash', 'module_label' => 'Caja', 'description' => 'Acceso a Reportes de Caja'],
        ];

        // 4) Crear permisos nuevos
        foreach ($items as $it) {
            $p = new Perm();
            $p->name         = $it['name'];
            $p->guard_name   = $guard;
            $p->module       = $it['module'] ?? null;
            $p->module_label = $it['module_label'] ?? null;
            $p->label        = $it['label'] ?? null;
            $p->description  = $it['description'] ?? null;
            $p->save();
        }

        // 5) Limpiar caché otra vez por si acaso
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
