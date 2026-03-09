<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\Permission;

class RolesAdminSeeder extends Seeder
{
    public function run()
    {
        // ==========================================
        // ROLES DEL SAAS (Globales)
        // ==========================================
        
        // 1. Super Admin (Dueño Absoluto del Sistema Kipu)
        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin', 
            'guard_name' => 'web', 
            'restaurant_id' => null
        ]);
        
        // Obtiene todos los permisos que sean del scope 'global'
        $permisosGlobales = Permission::where('scope', 'global')->get();
        $superAdmin->syncPermissions($permisosGlobales);

        // 2. Admin SaaS (Ej. Un gerente de ventas de tu sistema)
        $adminSaaS = Role::firstOrCreate([
            'name' => 'Admin SaaS', 
            'guard_name' => 'web', 
            'restaurant_id' => null
        ]);
        
        // Aquí asignarás los permisos globales cuando creemos el PermissionsAdminSeeder
        // $adminSaaS->syncPermissions(['ver_panel_saas', 'ver_restaurantes_admin', ...]);
    }
}