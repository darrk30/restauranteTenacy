<?php

namespace App\Traits;

use App\Models\Unit;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\Log;

trait ManjoStockProductos
{
    /**
     * Procesa cada ítem de un ajuste (entrada/salida)
     */
    public function applyAdjustment($adjustment): void
    {
        foreach ($adjustment->items as $item) {
            $this->processItem($item, $adjustment->tipo, $adjustment->codigo);
        }
    }

    /**
     * Revierte un ajuste (anulación).
     */
    public function reverseAdjustment($adjustment): void
    {
        foreach ($adjustment->items as $item) {
            $this->reverseItem($item, $adjustment->tipo, $adjustment->codigo);
        }
    }

    /**
     * Procesa el stock de múltiples ítems de venta en un solo paso.
     */
    public function applyVentaMasiva($items, string $tipo, ?string $comprobante = null, ?string $movimiento = null): void
    {
        foreach ($items as $item) {
            $this->processItem($item, $tipo, $comprobante, $movimiento);
        }
    }

    /**
     * Revierte el stock de una venta (Anulación).
     */
    public function reverseVenta($sale): void
    {
        foreach ($sale->details as $item) {
            if ($item->product && $item->product->control_stock) {
                $this->reverseItem(
                    item: $item,
                    tipo: 'salida',
                    comprobante: $sale->serie . '-' . $sale->correlativo,
                    movimiento: 'Venta Anulada'
                );
            }
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

    /**
     * Lógica centralizada para procesar stock (Entradas/Salidas)
     */
    private function processItem($item, string $tipo, ?string $comprobante = null, ?string $movimiento = null): void
    {
        $variant = $item->variant;
        if (!$variant) return;

        // Conversión de unidades
        $unitProduct = $item->product->unit;
        $unitSelected = $item->unit ?? $unitProduct;
        $cantidadFinal = $this->convertirCantidad($unitSelected, $unitProduct, $item->cantidad);

        // BLOQUEO PESIMISTA sobre el stock único de la variante
        $stock = WarehouseStock::where('variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            $stock = WarehouseStock::create([
                'variant_id' => $variant->id,
                'stock_real' => 0,
                'stock_reserva' => 0,
                'restaurant_id' => filament()->getTenant()->id,
            ]);
        }

        if ($tipo === 'entrada') {
            $stock->increment('stock_real', $cantidadFinal);
            $stock->increment('stock_reserva', $cantidadFinal);
        }

        if ($tipo === 'salida') {
            $nuevoStock = $item->product->venta_sin_stock
                ? $stock->stock_real - $cantidadFinal
                : max(0, $stock->stock_real - $cantidadFinal);

            $stock->update(['stock_real' => $nuevoStock]);

            // Solo actualizar reserva si NO es una venta final
            if ($movimiento !== 'Venta') {
                $stock->update(['stock_reserva' => $nuevoStock]);
            }
        }

        $variant->kardexes()->create([
            'product_id'      => $variant->product_id,
            'variant_id'      => $variant->id,
            'restaurant_id'   => filament()->getTenant()->id,
            'tipo_movimiento' => $movimiento ?? $tipo,
            'comprobante'     => $comprobante,
            'cantidad'        => $tipo === 'salida' ? -$cantidadFinal : $cantidadFinal,
            'stock_restante'  => $stock->stock_real,
            'modelo_type'     => get_class($item),
            'modelo_id'       => $item->id,
        ]);
    }

    /**
     * Reverso del ajuste (Anulaciones)
     */
    private function reverseItem($item, string $tipo, ?string $comprobante = null, ?string $movimiento = null): void
    {
        $variant = $item->variant;
        if (!$variant) return;

        $unitProduct = $item->product->unit;
        $unitSelected = $item->unit ?? $unitProduct;
        $cantidadFinal = $this->convertirCantidad($unitSelected, $unitProduct, $item->cantidad);

        $stock = WarehouseStock::where('variant_id', $variant->id)->lockForUpdate()->first();

        if (!$stock) return;

        // REVERSO DE LÓGICA
        if ($tipo === 'entrada') {
            // Si era entrada (sumó), ahora restamos
            $nuevo = max(0, $stock->stock_real - $cantidadFinal);
            $stock->update([
                'stock_real' => $nuevo,
                'stock_reserva' => $nuevo
            ]);
        }

        if ($tipo === 'salida') {
            // Si era salida (restó), ahora sumamos
            $stock->increment('stock_real', $cantidadFinal);
            $stock->increment('stock_reserva', $cantidadFinal);
        }

        // Registro de KARDEX de anulación
        $variant->kardexes()->create([
            'product_id'      => $variant->product_id,
            'variant_id'      => $variant->id,
            'restaurant_id'   => filament()->getTenant()->id,
            'tipo_movimiento' => $movimiento ?? $this->getReverseMovementName($item, $tipo),
            'comprobante'     => $comprobante,
            'cantidad'        => $tipo === 'salida' ? $cantidadFinal : -$cantidadFinal,
            'stock_restante'  => $stock->stock_real,
            'modelo_type'     => get_class($item),
            'modelo_id'       => $item->id,
        ]);
    }

    /**
     * Conversión de unidades
     */
    public function convertirCantidad(Unit $unidadSeleccionada, Unit $unidadProducto, float $cantidad): float
    {
        $factorSeleccionado = $this->factorHastaBase($unidadSeleccionada);
        $factorProducto     = $this->factorHastaBase($unidadProducto);
        return ($cantidad * $factorSeleccionado) / $factorProducto;
    }

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
        $modelName = class_basename($item);
        return match ($modelName) {
            'StockAdjustmentItem' => 'ajuste-anulado',
            'PurchaseDetail'      => 'compra-anulada',
            'SaleDetail'          => 'venta-anulada',
            default               => 'movimiento-anulado',
        };
    }
}
