<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 🚫 1️⃣ Desactivar temporalmente eventos de Restaurant
        // Restaurant::unsetEventDispatcher();

        // 👤 Crear usuario principal si no existe
        if (User::count() === 0) {
            User::factory()->create([
                'name' => 'Kevin Rivera',
                'email' => 'kevin@gmail.com',
                'password' => bcrypt('123123123'),
            ]);
        }

        // 🍽️ Crear restaurante base si no existe
        if (Restaurant::count() === 0) {
            $restaurant = Restaurant::create([
                'name'    => 'Restaurant Central',
                'address' => '123 Main St, Cityville',
                'slug'    => 'restaurant-central',
                'ruc'     => '12345678901',
            ]);

            User::first()->restaurants()->attach($restaurant);
        }

        // 🧩 Ejecutar seeders adicionales
        $this->call([
            // UnitSeeder::class,
        ]);

        // ✅ 2️⃣ Reactivar eventos solo al final (ya después de todo)
        // Restaurant::setEventDispatcher(app('events'));
    }
}
