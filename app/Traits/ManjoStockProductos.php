<?php

namespace App\Traits;

use App\Models\Unit;
use App\Models\WarehouseStock;

trait ManjoStockProductos
{
    /**
     * Procesa cada ítem de un ajuste (entrada/salida)
     */
    public function applyAdjustment($adjustment): void
    {
        foreach ($adjustment->items as $item) {
            $this->processItem($item, $adjustment->tipo, $adjustment->warehouse);
        }
    }

    /**
     * Revierte un ajuste (anulación).
     */
    public function reverseAdjustment($adjustment): void
    {
        foreach ($adjustment->items as $item) {
            $this->reverseItem($item, $adjustment->tipo, $adjustment->warehouse);
        }
    }

    /**
     * Procesa un item para entrada/salida.
     */
    private function processItem($item, string $tipo, $warehouse): void
    {
        if (! $warehouse) return;

        $variant = $item->variant;
        $unitProduct = $item->product->unit;
        $unitSelected = $item->unit;

        $cantidadFinal = $this->convertirCantidad($unitSelected, $unitProduct, $item->cantidad);

        $stock = WarehouseStock::firstOrCreate(
            [
                'variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
            ],
            ['stock_real' => 0]
        );

        if ($tipo === 'entrada') {
            $stock->increment('stock_real', $cantidadFinal);
        }

        if ($tipo === 'salida') {
            $nuevo = max(0, $stock->stock_real - $cantidadFinal);
            $stock->update(['stock_real' => $nuevo]);
        }
    }

    /**
     * Reverso del ajuste.
     */
    private function reverseItem($item, string $tipo, $warehouse): void
    {
        if (! $warehouse) return;

        $variant = $item->variant;
        $unitProduct = $item->product->unit;
        $unitSelected = $item->unit;

        $cantidadFinal = $this->convertirCantidad($unitSelected, $unitProduct, $item->cantidad);

        $stock = WarehouseStock::firstOrCreate(
            [
                'variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
            ],
            ['stock_real' => 0]
        );

        // REVERSO
        if ($tipo === 'entrada') {
            // Si fue entrada, ahora restamos
            $nuevo = max(0, $stock->stock_real - $cantidadFinal);
            $stock->update(['stock_real' => $nuevo]);
        }

        if ($tipo === 'salida') {
            // Si fue salida, ahora sumamos
            $stock->increment('stock_real', $cantidadFinal);
        }
    }

    /**
     * Conversión de unidades → base del producto.
     */
    public function convertirCantidad(Unit $unidadSeleccionada, Unit $unidadProducto, float $cantidad): float
    {
        $factorSeleccionado = $this->factorHastaBase($unidadSeleccionada);
        $factorProducto     = $this->factorHastaBase($unidadProducto);

        return ($cantidad * $factorSeleccionado) / $factorProducto;
    }

    /**
     * Factor total hasta unidad base raíz.
     */
    public function factorHastaBase(Unit $unidad): float
    {
        $factor = 1;
        $u = $unidad;

        while ($u) {
            $factor *= $u->quantity;
            $u = $u->unidadBase;
        }

        return $factor;
    }
}
