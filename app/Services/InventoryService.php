<?php

namespace App\Services;

class InventoryService
{
    public function determinarAlmacenParaItem($item, $almacenes, $todosLosStocks, $primerAlmacen)
    {
        $stocksDeVariante = $todosLosStocks->get($item->variant_id, collect());
        foreach ($almacenes as $almacen) {
            $stockEnEsteAlmacen = $stocksDeVariante->firstWhere('warehouse_id', $almacen->id);
            if ($stockEnEsteAlmacen && $stockEnEsteAlmacen->stock_real >= $item->cantidad) {
                return $almacen;
            }
        }
        return $item->product->venta_sin_stock ? $primerAlmacen : null;
    }
}
