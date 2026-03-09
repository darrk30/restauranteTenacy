<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Primero se crean todos los permisos en la tabla
            PermissionsAdminSeeder::class,
            PermissionsRestSeeder::class,

            // 2. Luego se crean los roles y se enlazan con los permisos
            RolesAdminSeeder::class,

            // 3. Finalmente, creas los usuarios y les asignas los roles
            UserSeeder::class,
        ]);
    }
}
