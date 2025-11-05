<?php

namespace App\Observers;

use App\Models\Restaurant;
use App\Models\UnitCategory;
use Database\Seeders\UnitSeeder;

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
        app()->forgetInstance('bypass_tenant_scope');
    }

    public function updated(Restaurant $restaurant): void
    {
        // Puedes agregar lógica si cambia el nombre, slug, etc.
    }

    public function deleted(Restaurant $restaurant): void
    {
        // Si deseas eliminar unidades asociadas manualmente, puedes hacerlo aquí.
        // Aunque ya está en cascada por la FK.
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
