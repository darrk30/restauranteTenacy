<?php

namespace Database\Seeders;

use App\Models\Floor;
use App\Models\Restaurant;
use App\Models\Table;
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
        $floor = Floor::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Piso 1', 'status' => true],
        );

        $estados = ['libre'];

        foreach (range(1, 5) as $i) {

            $estado = $estados[array_rand($estados)];

            Table::firstOrCreate(
                ['floor_id' => $floor->id, 'name' => "Mesa {$i}"],
                [
                    'status' => true,
                    'asientos' => 1,
                    'restaurant_id' => $restaurant->id,
                    'estado_mesa' => $estado,
                    'ocupada_desde' => null,
                ]
            );
        }

        $this->command?->info("✅ Piso y mesas creadas con estados variados para: {$restaurant->name}");
    }
}
