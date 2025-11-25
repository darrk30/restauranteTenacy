<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UnitCategory;
use App\Models\Unit;
use App\Models\Restaurant;

class UnitSeeder extends Seeder
{
    public function run(): void {}

    public function runForRestaurant(Restaurant $restaurant): void
    {
        $restaurantId = $restaurant->id;

        // No duplicar
        if (UnitCategory::where('restaurant_id', $restaurantId)->exists()) {
            $this->command?->warn("⚠️ Las unidades ya existen para el restaurante: {$restaurant->name}");
            return;
        }

        // --------------------------------------------------------
        // 🔥 DEFINICIÓN ÚNICA (todas las unidades principales aquí)
        // --------------------------------------------------------
        $definitions = [
            'Masas' => [
                [
                    'code' => 'G',
                    'name' => 'Gramo',
                    'quantity' => 1,
                    'is_base' => true,
                ],
                [
                    'code' => 'KGM',
                    'name' => 'Kilogramo',
                    'quantity' => 1000,
                    'ref' => 'G',
                ],
            ],

            'Volúmenes' => [
                [
                    'code' => 'MLT',
                    'name' => 'Mililitro',
                    'quantity' => 1,
                    'is_base' => true,
                ],
                [
                    'code' => 'LTR',
                    'name' => 'Litro',
                    'quantity' => 1000,
                    'ref' => 'MLT',
                ],
            ],

            'Cantidades' => [
                [
                    'code' => 'NIU',
                    'name' => 'Unidad',
                    'quantity' => 1,
                    'is_base' => true,
                ],
                [
                    'code' => 'DZN',
                    'name' => 'Docena',
                    'quantity' => 12,
                    'ref' => 'NIU',
                ],
            ],

            'Servicios' => [
                [
                    'code' => 'ZZ',
                    'name' => 'Servicio',
                    'quantity' => 1,
                    'is_base' => true,
                ],
            ],
        ];

        // --------------------------------------------------------
        // 🛠 PROCESADOR AUTOMÁTICO
        // --------------------------------------------------------
        foreach ($definitions as $categoryName => $units) {

            // Crear categoría
            $category = UnitCategory::create([
                'name' => $categoryName,
                'restaurant_id' => $restaurantId,
            ]);

            // Array para almacenar ID's de unidades base
            $createdUnits = [];

            // Primera pasada → crear unidades base sin referencias
            foreach ($units as $unit) {
                $createdUnits[$unit['code']] = Unit::create([
                    'unit_category_id' => $category->id,
                    'restaurant_id' => $restaurantId,
                    'code' => $unit['code'],
                    'name' => $unit['name'],
                    'quantity' => $unit['quantity'],
                    'is_base' => $unit['is_base'] ?? false,
                ]);
            }

            // Segunda pasada → aplicar references
            foreach ($units as $unit) {
                if (isset($unit['ref'])) {
                    Unit::where('code', $unit['code'])
                        ->where('restaurant_id', $restaurantId)
                        ->update([
                            'reference_unit_id' => $createdUnits[$unit['ref']]->id,
                        ]);
                }
            }
        }

        $this->command?->info("✅ Unidades creadas para: {$restaurant->name}");
    }
}
