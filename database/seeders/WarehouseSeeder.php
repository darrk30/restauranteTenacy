<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
    }

    public function runForRestaurant(Restaurant $restaurant): void
    {
        $restaurantId = $restaurant->id;

        // ✅ Evita duplicación si ya existen
        if (Warehouse::where('restaurant_id', $restaurantId)->exists()) {
            $this->command?->warn("⚠️ Los almacenes ya existen para el restaurante: {$restaurant->name}");
            return;
        }

        Warehouse::create([
            'name'      => 'Almacén Principal',
            'code'      => 'AP001',
            'direccion' => $restaurant->address ?: 'Sin dirección',
            'order'     => 1,
            'restaurant_id' => $restaurantId,
        ]);



        $this->command?->info('✅ Almacén creado/verificado para: ' . $restaurant->name);
    }
}
