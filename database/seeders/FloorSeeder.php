<?php

namespace Database\Seeders;

use App\Models\Floor;
use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FloorSeeder extends Seeder
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
        // Crear piso único
        $floor = Floor::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Piso 1', 'status' => true],
        );

        // Crear 5 mesas usando foreach
        foreach (range(1, 5) as $i) {
            Table::firstOrCreate(
                ['floor_id' => $floor->id, 'name' => "Mesa {$i}"],
                ['status' => true, 'asientos' => 1, 'restaurant_id' => $restaurant->id]
            );
        }

        $this->command?->info("✅ Piso y mesas creadas para: {$restaurant->name}");
    }
}
