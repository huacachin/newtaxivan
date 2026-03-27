<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Roles nuevos (deben existir vía RoleSetupSeeder)
        Role::firstOrCreate(['name' => 'director',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'gerente',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supervisor',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'controlador',   'guard_name' => 'web']);

        // Permisos completos del director
        $directorPerms = [
            'dashboard', 'departures', 'payments',
            'configuracion.vehicles', 'configuracion.owners', 'configuracion.drivers',
            'configuracion.cost-per-plate', 'configuracion.users', 'configuracion.concepts',
            'configuracion.headquarters',
            'debts.days', 'debts.monthly',
            'cash.incomes', 'cash.expenses',
            'cash.report-general', 'cash.report-draco', 'cash.report-sal-pag-cont', 'cash.report-caja-ma',
        ];

        // ----------------------------------------------------------------
        // Usuarios  (contraseñas: hashes reales de producción)
        // ----------------------------------------------------------------
        $users = [
            [
                'id'              => 1,
                'name'            => 'Guilmer',
                'username'        => 'Guilmer',
                'email'           => 'guilmer@taxivan.local',
                'password'        => '$2y$12$Sx0beico44HX4svmoMFlpON8iWV5KM2geCDIxLYxNB4VWnvY6Sv6m',
                'document_type'   => 'CE',
                'document_number' => '395754107',
                'phone'           => '909532712',
                'headquarter_id'  => 1,
                'status'          => 'active',
                'role'            => 'director',
                'direct_perms'    => $directorPerms,
            ],
            [
                'id'              => 2,
                'name'            => 'Elmer',
                'username'        => 'Elmer',
                'email'           => 'elmer@taxivan.local',
                'password'        => '$2y$12$iJo4Kv8MY7eUz7cgLRZCLOkC1P0cGSEzyS9q.wWOLO/kj4bSIGNJy',
                'document_type'   => 'CE',
                'document_number' => '189887939',
                'phone'           => '976347989',
                'headquarter_id'  => 2,
                'status'          => 'active',
                'role'            => 'controlador',
                'direct_perms'    => [
                    'departures', 'payments',
                    'configuracion.vehicles', 'configuracion.owners', 'configuracion.drivers',
                    'cash.expenses',
                ],
            ],
            [
                'id'              => 3,
                'name'            => 'Felix',
                'username'        => 'Felix',
                'email'           => 'felix@taxivan.local',
                'password'        => '$2y$12$hokOMqxW./F3j0h73pwNkez.3Sb2CagZnt63PYRdFtSPdgPrCiy6i',
                'document_type'   => 'DNI',
                'document_number' => '28627493',
                'phone'           => '923531660',
                'headquarter_id'  => 1,
                'status'          => 'inactive',
                'role'            => null,
                'direct_perms'    => [],
            ],
            [
                'id'              => 4,
                'name'            => 'Ivan',
                'username'        => 'Ivan',
                'email'           => 'ivan@taxivan.local',
                'password'        => '$2y$12$ifpuC5rZe0llrSGagQ9QuepMyQS7p5q5BpNZdGMOq3FHLore3b0he',
                'document_type'   => 'CE',
                'document_number' => '101403061',
                'phone'           => '916910976',
                'headquarter_id'  => 1,
                'status'          => 'inactive',
                'role'            => null,
                'direct_perms'    => [],
            ],
            [
                'id'              => 5,
                'name'            => 'Jhoseph',
                'username'        => 'Jhoseph',
                'email'           => 'jhoseph@taxivan.local',
                'password'        => '$2y$12$i.iYUdCx0JB2zH8mTLcvXugMsKBe4MkkE8Q9xffFkCntBCesD.596',
                'document_type'   => 'CE',
                'document_number' => '642543665',
                'phone'           => '950604096',
                'headquarter_id'  => 1,
                'status'          => 'inactive',
                'role'            => null,
                'direct_perms'    => [],
            ],
            [
                'id'              => 6,
                'name'            => 'Luis',
                'username'        => 'Luis',
                'email'           => 'luis@taxivan.local',
                'password'        => '$2y$12$t5JdAqGZRCtYWc5nu6JkHeqM8FT.23MYP48lymn.2cS9LBsevXxUG',
                'document_type'   => 'CE',
                'document_number' => '736856248',
                'phone'           => '983955785',
                'headquarter_id'  => 1,
                'status'          => 'active',
                'role'            => 'controlador',
                'direct_perms'    => [
                    'departures', 'payments',
                    'configuracion.vehicles', 'configuracion.owners', 'configuracion.drivers',
                    'cash.incomes', 'cash.expenses',
                ],
            ],
            [
                'id'              => 7,
                'name'            => 'Marko',
                'username'        => 'Marko',
                'email'           => 'marko@taxivan.local',
                'password'        => '$2y$12$G.du5/YaBisH4n83q7560OG/XYleHrGj9naNtx661Gl7N4JkPR4EK',
                'document_type'   => 'CE',
                'document_number' => '152241805',
                'phone'           => '903052133',
                'headquarter_id'  => 1,
                'status'          => 'inactive',
                'role'            => null,
                'direct_perms'    => [],
            ],
            [
                'id'              => 8,
                'name'            => 'Nancy',
                'username'        => 'Nancy',
                'email'           => 'nancy@taxivan.local',
                'password'        => '$2y$12$QAsIY4b5bFmD.Fsmyl/DAeUnpHwssBDkaSjpBFPI8PVp38Xx/N48.',
                'document_type'   => 'CE',
                'document_number' => '568586891',
                'phone'           => '980010794',
                'headquarter_id'  => 3,
                'status'          => 'active',
                'role'            => 'controlador',
                'direct_perms'    => [
                    'departures', 'payments', 'cash.expenses',
                ],
            ],
            [
                'id'              => 9,
                'name'            => 'Licet',
                'username'        => 'Licet',
                'email'           => 'licet@taxivan.local',
                'password'        => '$2y$12$yci1wv3ak/e22a/XW2oNBugMf.mxA8tVKBNX.8ON8dNSsbvnJDQLa',
                'document_type'   => 'DNI',
                'document_number' => '12017970',
                'phone'           => '915239213',
                'headquarter_id'  => 1,
                'status'          => 'active',
                'role'            => 'director',
                'direct_perms'    => $directorPerms,
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['id' => $data['id']],
                [
                    'name'            => $data['name'],
                    'username'        => $data['username'],
                    'email'           => $data['email'],
                    'password'        => $data['password'],
                    'document_type'   => $data['document_type'],
                    'document_number' => $data['document_number'],
                    'phone'           => $data['phone'],
                    'headquarter_id'  => $data['headquarter_id'],
                    'status'          => $data['status'],
                ]
            );

            // Rol
            if ($data['role']) {
                $user->syncRoles([$data['role']]);
            } else {
                $user->syncRoles([]);
            }

            // Permisos directos
            $user->syncPermissions($data['direct_perms']);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
