<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission as Perm;

class PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        // Catálogo: módulo => [ 'label' => 'Nombre bonito', 'actions' => ['Etiqueta'=>'slug'] ]
        $catalog = [
            'dashboard' => [
                'label' => 'Dashboard',
                'actions' => ['Ver' => 'view'],
            ],
            'vehicles' => [
                'label' => 'Vehículos',
                'actions' => ['Ver'=>'view','Crear'=>'create','Editar'=>'edit','Eliminar'=>'delete'],
            ],
            'owners' => [
                'label' => 'Propietarios',
                'actions' => ['Ver'=>'view','Crear'=>'create','Editar'=>'edit','Eliminar'=>'delete'],
            ],
            'drivers' => [
                'label' => 'Conductores',
                'actions' => ['Ver'=>'view','Crear'=>'create','Editar'=>'edit','Eliminar'=>'delete'],
            ],
            'cost-per-plate' => [
                'label' => 'Costo por Placa',
                'actions' => ['Ver'=>'view','Crear'=>'create','Editar'=>'edit','Eliminar'=>'delete'],
            ],
            'users' => [
                'label' => 'Usuarios',
                'actions' => ['Ver'=>'view','Crear'=>'create','Editar'=>'edit','Eliminar'=>'delete'],
            ],
            'concepts' => [
                'label' => 'Conceptos',
                'actions' => ['Ver'=>'view','Crear'=>'create','Editar'=>'edit','Eliminar'=>'delete'],
            ],
            'departures' => [
                'label' => 'Salidas',
                'actions' => ['Ver'=>'view','Crear'=>'create','Editar'=>'edit','Eliminar'=>'delete'],
            ],
            'payments' => [
                'label' => 'Pagos',
                'actions' => ['Ver'=>'view','Crear'=>'create','Editar'=>'edit','Eliminar'=>'delete'],
            ],
            'debts' => [
                'label' => 'Deudas',
                'actions' => ['Ver'=>'view','Crear'=>'create','Editar'=>'edit','Eliminar'=>'delete','Reportes'=>'report'],
            ],
            'cash' => [
                'label' => 'Caja',
                'actions' => ['Ver'=>'view','Reportes'=>'report'],
            ]
        ];

        foreach ($catalog as $module => $cfg) {
            foreach ($cfg['actions'] as $actionLabel => $actionSlug) {
                $name = "{$module}.{$actionSlug}"; // p.ej. vehicles.view

                $p = Perm::firstOrCreate(
                    ['name' => $name, 'guard_name' => $guard],
                );

                $p->module        = $module;
                $p->module_label  = $cfg['label'];                // "Vehículos"
                $p->label         = $actionLabel;                 // "Ver"
                $p->description   = "Permite {$actionLabel} en el módulo {$cfg['label']}";
                $p->save();
            }
        }
    }
}
