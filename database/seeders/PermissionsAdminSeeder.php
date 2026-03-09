<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsAdminSeeder extends Seeder
{
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $estructura = [
            'panel_saas' => [
                'label' => 'Dashboard SaaS Principal',
                'permissions' => [
                    'ver_panel_saas' => 'Acceso al dashboard global',
                ]
            ],
            'restaurantes' => [
                'label' => 'Administración de Restaurantes (Clientes)',
                'permissions' => [
                    'ver_restaurantes_admin'      => 'Ver listado de restaurantes',
                    'crear_restaurantes_admin'    => 'Registrar nuevos restaurantes',
                    'editar_restaurantes_admin'   => 'Editar datos de restaurantes',
                    'eliminar_restaurantes_admin' => 'Suspender/Eliminar restaurantes',
                    'ver_suscripciones_admin'     => 'Ver pagos y suscripciones de restaurantes',
                ]
            ],
            'roles_permisos' => [
                'label' => 'Gestión de Roles',
                'permissions' => [
                    'listar_roles_admin' => 'Listar roles',
                    'crear_rol_admin' => 'Crear Rol',
                    'editar_rol_admin' => 'Editar Rol',
                    'anular_rol_admin' => 'Anular Rol',
                ]
            ],
        ];

        foreach ($estructura as $keyModule => $data) {
            $moduleLabel = $data['label'];
            $permissions = $data['permissions'];

            foreach ($permissions as $permissionName => $permissionDesc) {
                Permission::updateOrCreate(
                    ['name' => $permissionName],
                    [
                        'description'  => $permissionDesc,
                        'module'       => $keyModule,
                        'module_label' => $moduleLabel,
                        'scope'        => 'global',
                        'guard_name'   => 'web'
                    ]
                );
            }
        }
    }
}