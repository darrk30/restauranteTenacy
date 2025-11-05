<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UnitCategory;
use App\Models\Unit;
use App\Models\Restaurant;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        // $restaurant = Restaurant::first();

        // if (!$restaurant) {
        //     $this->command->warn('⚠️ No hay restaurantes creados. Ejecute primero el seeder de restaurantes.');
        //     return;
        // }

        // $this->runForRestaurant(2);
    }

    public function runForRestaurant(Restaurant $restaurant): void
    {
        $restaurantId = $restaurant->id;
        // dd($restaurantId);


        // ✅ Evita duplicación si ya existen
        if (UnitCategory::where('restaurant_id', $restaurantId)->exists()) {
            $this->command?->warn("⚠️ Las unidades ya existen para el restaurante: {$restaurant->name}");
            return;
        }

        // -----------------------
        // 1️⃣ MASA / Weight
        // -----------------------
        $massCategory = UnitCategory::create([
            'name' => 'Masa',
            'restaurant_id' => $restaurantId,
        ]);

        // dd($massCategory);

        $gram = Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'G',
        ], [
            'unit_category_id' => $massCategory->id,
            'name' => 'Gramo',
            'is_base' => true,
            'quantity' => 1,
        ]);

        Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'KGM',
        ], [
            'unit_category_id' => $massCategory->id,
            'name' => 'Kilogramo',
            'quantity' => 1000,
            'reference_unit_id' => $gram->id,
        ]);

        Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'MGM',
        ], [
            'unit_category_id' => $massCategory->id,
            'name' => 'Miligramo',
            'quantity' => 0.001,
            'reference_unit_id' => $gram->id,
        ]);

        // -----------------------
        // 2️⃣ VOLUMEN / Volume
        // -----------------------
        $volumeCategory = UnitCategory::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'name' => 'Volumen',
        ]);

        $ml = Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'MLT',
        ], [
            'unit_category_id' => $volumeCategory->id,
            'name' => 'Mililitro',
            'is_base' => true,
            'quantity' => 1,
        ]);

        Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'LTR',
        ], [
            'unit_category_id' => $volumeCategory->id,
            'name' => 'Litro',
            'quantity' => 1000,
            'reference_unit_id' => $ml->id,
        ]);

        // -----------------------
        // 3️⃣ LONGITUD / Length
        // -----------------------
        $lengthCategory = UnitCategory::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'name' => 'Longitud',
        ]);

        $cm = Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'CMT',
        ], [
            'unit_category_id' => $lengthCategory->id,
            'name' => 'Centímetro',
            'is_base' => true,
            'quantity' => 1,
        ]);

        Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'MTR',
        ], [
            'unit_category_id' => $lengthCategory->id,
            'name' => 'Metro',
            'quantity' => 100,
            'reference_unit_id' => $cm->id,
        ]);

        Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'MMT',
        ], [
            'unit_category_id' => $lengthCategory->id,
            'name' => 'Milímetro',
            'quantity' => 0.1,
            'reference_unit_id' => $cm->id,
        ]);

        // -----------------------
        // 4️⃣ CANTIDAD / Count
        // -----------------------
        $countCategory = UnitCategory::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'name' => 'Cantidad',
        ]);

        $piece = Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'NIU',
        ], [
            'unit_category_id' => $countCategory->id,
            'name' => 'Unidad',
            'quantity' => 1,
            'is_base' => true,
        ]);

        Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'DZN',
        ], [
            'unit_category_id' => $countCategory->id,
            'name' => 'Docena',
            'quantity' => 12,
            'reference_unit_id' => $piece->id,
        ]);

        // -----------------------
        // 5️⃣ TIEMPO / Time
        // -----------------------
        $timeCategory = UnitCategory::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'name' => 'Tiempo',
        ]);

        $hour = Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'HUR',
        ], [
            'unit_category_id' => $timeCategory->id,
            'name' => 'Hora',
            'quantity' => 1,
            'is_base' => true,
        ]);

        Unit::firstOrCreate([
            'restaurant_id' => $restaurantId,
            'code' => 'DAY',
        ], [
            'unit_category_id' => $timeCategory->id,
            'name' => 'Día laboral',
            'quantity' => 8,
            'reference_unit_id' => $hour->id,
        ]);

        $this->command?->info('✅ Unidades creadas/verificadas para: ' . $restaurant->name);
    }
}
