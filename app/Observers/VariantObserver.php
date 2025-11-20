<?php

namespace App\Observers;

use App\Models\Variant;
use App\Models\Warehouse;
use App\Models\WarehouseStock;

class VariantObserver
{
    /**
     * Handle the Variant "created" event.
     */
    public function created(Variant $variant): void
    {
        // Verificar si el producto controla stock
        if ($variant->product->control_stock == true) {
            // Obtener el almacén ordenado por "order" ASC
            $warehouse = Warehouse::orderBy('order', 'asc')->first();

            if (!$warehouse) {
                return; // No hay almacén, no hacemos nada
            }

            // Crear registro en warehouse_stock
            WarehouseStock::create([
                'warehouse_id' => $warehouse->id,
                'variant_id'   => $variant->id,
                'stock_real'   => 0,
                'stock_reserva' => 0,
                'min_stock'    => 0,
            ]);
        }
    }

    /**
     * Handle the Variant "updated" event.
     */
    public function updated(Variant $variant): void
    {
        //
    }

    /**
     * Handle the Variant "deleted" event.
     */
    public function deleted(Variant $variant): void
    {
        //
    }

    /**
     * Handle the Variant "restored" event.
     */
    public function restored(Variant $variant): void
    {
        //
    }

    /**
     * Handle the Variant "force deleted" event.
     */
    public function forceDeleted(Variant $variant): void
    {
        //
    }
}
