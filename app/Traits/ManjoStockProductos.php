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
            $this->processItem($item, $adjustment->tipo, comprobante: $adjustment->codigo);
        }
    }

    /**
     * Revierte un ajuste (anulación).
     */
    public function reverseAdjustment($adjustment): void
    {
        foreach ($adjustment->items as $item) {
            $this->reverseItem($item, $adjustment->tipo);
        }
    }

    public function applyPurchase($purchase, $movimiento = null): void
    {
        foreach ($purchase->details as $item) {
            if ($purchase->estado_despacho === 'recibido') {
                $this->processItem(
                    item: $item,
                    tipo: 'entrada',
                    comprobante: $purchase->serie . '-' . $purchase->numero,
                    movimiento: $movimiento,
                );
            }
        }
    }

    public function reversePurchase($purchase, $movimiento = null): void
    {
        foreach ($purchase->details as $item) {
            $this->reverseItem(
                item: $item,
                tipo: 'entrada',
                comprobante: $purchase->serie . '-' . $purchase->numero,
                movimiento: $movimiento,
            );
        }
    }

    private function processItem($item, string $tipo, ?string $comprobante = null, ?string $movimiento = null): void
    {
        $warehouse = $item->warehouse;
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

        /*** ACTUALIZAR STOCK REAL ***/
        if ($tipo === 'entrada') {
            if (! $variant->stock_inicial) {
                $variant->update(['stock_inicial' => true]);
            }
            $stock->increment('stock_real', $cantidadFinal);
            $stock->increment('stock_reserva', $cantidadFinal);
        }

        if ($tipo === 'salida') {
            $nuevo = max(0, $stock->stock_real - $cantidadFinal);
            $stock->update(['stock_real' => $nuevo]);
            $stock->update(['stock_reserva' => $nuevo]);
        }

        /*** REGISTRO DE KARDEX ***/
        $variant->kardexes()->create([
            'product_id'     => $variant->product_id,
            'variant_id'     => $variant->id,
            'restaurant_id'  => filament()->getTenant()->id,
            'warehouse_id'   =>  $warehouse->id,
            'tipo_movimiento' => $movimiento ?? $tipo,
            'comprobante'     => $comprobante,
            'cantidad'       => $cantidadFinal,
            'stock_restante' => $stock->stock_real,
            'modelo_type'    => get_class($item->modelo ?? $item),
            'modelo_id'      => $item->modelo->id ?? $item->id,
        ]);
    }


    /**
     * Reverso del ajuste.
     */
    private function reverseItem($item, string $tipo, ?string $comprobante = null, ?string $movimiento = null): void
    {
        $warehouse = $item->warehouse;
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
            $stock->update(['stock_reserva' => $nuevo]);
        }

        if ($tipo === 'salida') {
            // Si fue salida, ahora sumamos
            $stock->increment('stock_real', $cantidadFinal);
            $stock->increment('stock_reserva', $cantidadFinal);
        }

        $variant->kardexes()->create([
            'product_id'      => $variant->product_id,
            'variant_id'      => $variant->id,
            'restaurant_id'   => filament()->getTenant()->id,
            'warehouse_id'   =>  $warehouse->id,
            'tipo_movimiento' => $movimiento ?? $this->getReverseMovementName($item, $tipo),
            'comprobante'     => $comprobante,   // ← YA VIENE LISTO
            'cantidad'        => -$cantidadFinal,
            'stock_restante'  => $stock->stock_real,
            'modelo_type'     => get_class($item->modelo ?? $item),
            'modelo_id'       => $item->modelo->id ?? $item->id,
        ]);
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

    private function getReverseMovementName($item, string $tipo): string
    {
        $modelName = class_basename($item->modelo ?? $item);
        return match ($modelName) {
            'StockAdjustmentItem' => 'ajuste-anulado',
            'PurchaseDetail'      => 'compra-anulada',
            'SaleItem'            => 'venta-anulada',
            default               => 'movimiento-anulado',
        };
    }
}
