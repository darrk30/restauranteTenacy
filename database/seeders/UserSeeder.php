<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Restaurant;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run()
    {
        // ============================
        // 1. SUPER ADMIN DEL SAAS
        // ============================
        app(PermissionRegistrar::class)->setPermissionsTeamId(null); // Contexto Global

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@kipu.cloud'],
            [
                'name'     => 'Super Admin Kevin',
                'password' => Hash::make('123123123'),
            ]
        );

        $roleSuperAdmin = Role::where('name', 'Super Admin')->first();
        if ($roleSuperAdmin && !$superAdmin->hasRole($roleSuperAdmin)) {
            $superAdmin->assignRole($roleSuperAdmin);
        }

        // ============================
        // 2. CREACIÓN DE RESTAURANTE Y DUEÑO
        // ============================
        $restaurant = Restaurant::firstOrCreate(
            ['ruc' => '12345678901'],
            [
                'name'    => 'Restaurant Central',
                'address' => 'Av. Principal 123',
                'slug'    => 'restaurant-central',
            ]
        );

        // 🟢 1. Creamos al usuario SIN la columna restaurant_id
        $propietarioUser = User::firstOrCreate(
            ['email' => 'kevin@gmail.com'],
            [
                'name'     => 'Kevin Rivera (Dueño)',
                'password' => Hash::make('123123123'),
            ]
        );

        // 🟢 2. Vinculamos el usuario al restaurante usando la relación muchos a muchos
        // Usamos syncWithoutDetaching para que no se duplique si corres el seeder varias veces
        $propietarioUser->restaurants()->syncWithoutDetaching([$restaurant->id]);


        // ============================
        // 3. ASIGNACIÓN DE ROL SPATIE
        // ============================
        // Le indicamos a Spatie en qué restaurante vamos a asignar el rol
        app(PermissionRegistrar::class)->setPermissionsTeamId($restaurant->id);

        // 🟢 Nota: En el RolesRestSeeder anterior lo llamamos 'Administrador'. 
        // Si tienes 'Propietario', cámbialo aquí.
        $rolePropietario = Role::where('name', 'Administrador')->first(); 
        
        if ($rolePropietario && !$propietarioUser->hasRole($rolePropietario)) {
            // Esto guardará en la BD: role_id = X, model_id = Y, restaurant_id = 1
            $propietarioUser->assignRole($rolePropietario); 
        }
    }
}