<?php

namespace App\Observers;

use App\Models\Restaurant;
use App\Models\UnitCategory;
use Database\Seeders\ConfiguracionInicial;
use Database\Seeders\FloorSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Support\Facades\Storage;

class RestaurantObserver
{
    /**
     * Cuando se crea un nuevo restaurante,
     * se generan automáticamente sus unidades base.
     */
    public function created(Restaurant $restaurant): void
    {
        app()->instance('bypass_tenant_scope', true);
        (new UnitSeeder())->runForRestaurant($restaurant);
        (new FloorSeeder())->runForRestaurant($restaurant);
        (new TypeDocumentSeeder())->runForRestaurant($restaurant);
        (new ConfiguracionInicial())->runForRestaurant($restaurant);
        app()->forgetInstance('bypass_tenant_scope');
    }

    /**
     * Se ejecuta cuando el restaurante se está actualizando.
     */
    public function updated(Restaurant $restaurant): void
    {
        // Si el campo 'logo' cambió (se subió uno nuevo)
        if ($restaurant->isDirty('logo')) {
            $logoAntiguo = $restaurant->getOriginal('logo');

            // Si existía un logo anterior y es distinto al nuevo, lo borramos del disco
            if ($logoAntiguo && $logoAntiguo !== $restaurant->logo) {
                Storage::disk('public')->delete($logoAntiguo);
            }
        }
    }

    /**
     * Se ejecuta cuando el restaurante se elimina.
     */
    public function deleted(Restaurant $restaurant): void
    {
        // Borramos el logo si el restaurante deja de existir
        if ($restaurant->logo) {
            Storage::disk('public')->delete($restaurant->logo);
        }
    }
    public function restored(Restaurant $restaurant): void
    {
        //
    }

    public function forceDeleted(Restaurant $restaurant): void
    {
        //
    }
}
