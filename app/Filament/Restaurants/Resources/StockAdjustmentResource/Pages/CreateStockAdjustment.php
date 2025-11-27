<?php

namespace App\Filament\Restaurants\Resources\StockAdjustmentResource\Pages;

use App\Filament\Restaurants\Resources\StockAdjustmentResource;
use App\Models\Unit;
use App\Models\WarehouseStock;
use App\Traits\ManjoStockProductos;
use Filament\Resources\Pages\CreateRecord;

class CreateStockAdjustment extends CreateRecord
{
    use ManjoStockProductos;
    protected static string $resource = StockAdjustmentResource::class;

    protected function afterCreate(): void
    {
        $this->applyAdjustment($this->record);
    }

    // protected function afterCreate(): void
    // {
    //     $adjustment = $this->record;

    //     foreach ($adjustment->items as $item) {
    //         $this->processItem($item, $adjustment);
    //     }
    // }

    // private function processItem($item, $adjustment): void
    // {
    //     $tipo = $adjustment->tipo;

    //     // Primer almacén del restaurante
    //     $warehouse = $adjustment->warehouse;
    //     if (! $warehouse) {
    //         return;
    //     }
    //     $variant = $item->variant;
    //     $unitProduct = $item->product->unit;
    //     $unitSelected = $item->unit;
    //     $cantidadIngresada = $item->cantidad;

    //     // Convertimos la cantidad ingresada a unidad base del producto
    //     $cantidadFinal = $this->convertirCantidad($unitSelected, $unitProduct, $cantidadIngresada);
    //     // Obtener o crear registro de stock
    //     $stock = WarehouseStock::firstOrCreate(
    //         [
    //             'variant_id'   => $variant->id,
    //             'warehouse_id' => $warehouse->id,
    //         ],
    //         [
    //             'stock_real' => 0
    //         ]
    //     );
    //     // dd($stock);
    //     // ENTRADA → incrementa
    //     if ($tipo === 'entrada') {
    //         $stock->increment('stock_real', $cantidadFinal);
    //     }

    //     // SALIDA → decrementa pero no deja bajar de 0
    //     if ($tipo === 'salida') {
    //         $nuevoStock = max(0, $stock->stock_real - $cantidadFinal);
    //         $stock->update(['stock_real' => $nuevoStock]);
    //     }
    // }

    // /**
    //  * Convierte cualquier cantidad desde una unidad seleccionada → unidad base del producto.
    //  */
    // private function convertirCantidad(Unit $unidadSeleccionada, Unit $unidadProducto, float $cantidad): float
    // {
    //     $factorSeleccionado = $this->factorHastaBase($unidadSeleccionada);
    //     $factorProducto     = $this->factorHastaBase($unidadProducto);

    //     return ($cantidad * $factorSeleccionado) / $factorProducto;
    // }

    //   /**
    //  * Devuelve el factor total multiplicado hacia la unidad base raíz.
    //  */
    // private function factorHastaBase(Unit $unidad): float
    // {
    //     $factor = 1;
    //     $u = $unidad;

    //     while ($u) {
    //         $factor *= $u->quantity;
    //         $u = $u->unidadBase;
    //     }

    //     return $factor;
    // }
}
