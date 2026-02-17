<?php

namespace App\Traits;

use App\Models\Unit;
use App\Models\Variant;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Model;
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
            // Nota: Aquí podrías necesitar lógica recursiva similar si quieres revertir recetas,
            // pero por ahora mantenemos la lógica base de reversión de productos con stock.
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
     * DIRECTOR: Decide recursivamente cómo procesar el ítem
     */
    private function processItem($item, string $tipo, ?string $comprobante = null, ?string $movimiento = null): void
    {
        // 1. CASO PROMOCIÓN (Recursivo)
        if ($item->promotion_id && $item->promotion) {
            foreach ($item->promotion->promotionproducts as $subItem) {
                // Creamos un objeto virtual simulando un SaleDetail
                $virtualItem = new \App\Models\SaleDetail([
                    'product_id' => $subItem->product_id,
                    'variant_id' => $subItem->variant_id,
                    'cantidad'   => $item->cantidad * $subItem->quantity,
                    // No guardamos en BD
                ]);

                // Hydratamos relaciones manualmente
                $virtualItem->setRelation('product', $subItem->product);
                $virtualItem->setRelation('variant', $subItem->variant);
                // Si la promoción no define unidad, usamos la del producto base
                $virtualItem->setRelation('unit', $subItem->product->unit);

                // Llamada recursiva para procesar el hijo (puede ser Gaseosa o Ceviche)
                $this->processItem($virtualItem, $tipo, $comprobante, $movimiento);
            }
            return;
        }

        // Cargar producto y variante si no están cargados
        $product = $item->product ?? \App\Models\Product::find($item->product_id);
        $variant = $item->variant ?? \App\Models\Variant::find($item->variant_id);

        if (!$product || !$variant) return;

        // 2. CASO PRODUCTO CON RECETA (Ej. Ceviche)
        // Solo procesamos receta si es SALIDA (Venta/Merma), no en entrada (Compra)
        if ($product->receta && $tipo === 'salida') {
            $this->processRecipeItem($item, $variant, $tipo, $comprobante, $movimiento);
        }

        // 3. CASO PRODUCTO CON CONTROL DE STOCK (Ej. Gaseosa / Insumo Arroz)
        if ($product->control_stock) {
            $this->processDirectItem($item, $variant, $product, $tipo, $comprobante, $movimiento);
        }
    }

    /**
     * Lógica A: Procesa un producto directo (Gaseosa)
     */
    private function processDirectItem($item, $variant, $product, $tipo, $comprobante, $movimiento): void
    {
        // 1. Determinar unidades
        $unitProduct = $product->unit; // Unidad Base (ej. Botella/Kg)
        $unitSelected = $item->unit ?? $unitProduct; // Unidad Venta (ej. Pack/Gramos)

        // 2. Convertir cantidad total a unidad base
        $cantidadFinal = $this->convertirCantidad($unitSelected, $unitProduct, $item->cantidad);

        // 3. Actualizar BD
        $this->updateStockAndKardex(
            variant: $variant,
            cantidadBase: $cantidadFinal,
            tipo: $tipo,
            comprobante: $comprobante,
            movimiento: $movimiento,
            modelo: $item
        );
    }

    /**
     * Lógica B: Procesa una Receta (Ceviche -> Arroz + Pescado)
     */
    private function processRecipeItem($item, $variant, $tipo, $comprobante, $movimiento): void
    {
        $ingredientes = $variant->recetas; // Asume relación hasMany

        if ($ingredientes->count() === 0) return;

        foreach ($ingredientes as $ingrediente) {
            // Cargar el Insumo (Variante) y su Producto Padre
            $insumoVariant = Variant::with('product', 'product.unit')->find($ingrediente->insumo_id);

            if ($insumoVariant && $insumoVariant->product) {

                // A. Cantidad total requerida (Platos * Cantidad por plato)
                $cantidadTotalReceta = $item->cantidad * $ingrediente->cantidad;

                // B. Identificar Unidades para conversión
                $unidadReceta = $ingrediente->unit; // Ej. Gramos
                $unidadBaseInsumo = $insumoVariant->product->unit; // Ej. Kilos

                // C. Convertir (Ej. 400 Gramos -> 0.4 Kilos)
                $cantidadBase = $this->convertirCantidad($unidadReceta, $unidadBaseInsumo, $cantidadTotalReceta);

                // D. Actualizar BD del INSUMO
                $this->updateStockAndKardex(
                    variant: $insumoVariant, // ¡OJO! Afectamos al Insumo
                    cantidadBase: $cantidadBase,
                    tipo: $tipo,
                    comprobante: $comprobante,
                    movimiento: $movimiento ?? 'Consumo Receta',
                    modelo: $item // Enlazamos al Plato original
                );
            }
        }
    }

    /**
     * NÚCLEO COMÚN: Bloqueo, Update Stock y Create Kardex
     */
    private function updateStockAndKardex(Variant $variant, float $cantidadBase, string $tipo, ?string $comprobante, ?string $movimiento, Model $modelo): void
    {
        // 1. BLOQUEO PESIMISTA
        $stock = WarehouseStock::where('variant_id', $variant->id)
            ->where('restaurant_id', filament()->getTenant()->id)
            ->lockForUpdate()
            ->first();

        // Crear si no existe
        if (!$stock) {
            $stock = WarehouseStock::create([
                'variant_id'    => $variant->id,
                'stock_real'    => 0,
                'stock_reserva' => 0,
                'restaurant_id' => filament()->getTenant()->id,
            ]);
        }

        // 2. ACTUALIZAR STOCK REAL Y RESERVA
        if ($tipo === 'entrada') {
            $stock->increment('stock_real', $cantidadBase);
            $stock->increment('stock_reserva', $cantidadBase);
        }

        if ($tipo === 'salida') {
            $allowsNegative = $variant->product->venta_sin_stock ?? false;

            $nuevoStock = $allowsNegative
                ? $stock->stock_real - $cantidadBase
                : max(0, $stock->stock_real - $cantidadBase);

            $stock->update(['stock_real' => $nuevoStock]);

            // Solo actualizar reserva si NO es una venta final
            if ($movimiento !== 'Venta') {
                $stock->update(['stock_reserva' => $nuevoStock]);
            }
        }

        // 3. REGISTRAR KARDEX
        // Manejamos el caso de objetos virtuales
        $modeloType = get_class($modelo);
        $modeloId   = $modelo->id ?? null; // Puede ser null si es virtual de promoción

        $variant->kardexes()->create([
            'product_id'      => $variant->product_id,
            'variant_id'      => $variant->id,
            'restaurant_id'   => filament()->getTenant()->id,
            'tipo_movimiento' => $movimiento ?? $tipo,
            'comprobante'     => $comprobante,
            'cantidad'        => $tipo === 'salida' ? -$cantidadBase : $cantidadBase,
            'stock_restante'  => $stock->stock_real,
            'modelo_type'     => $modeloType,
            'modelo_id'       => $modeloId,
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

        $stock = WarehouseStock::where('variant_id', $variant->id)
            ->where('restaurant_id', filament()->getTenant()->id)
            ->lockForUpdate()
            ->first();

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
        // Si son la misma unidad, retornamos directo
        if ($unidadSeleccionada->id === $unidadProducto->id) return $cantidad;

        $factorSeleccionado = $this->factorHastaBase($unidadSeleccionada);
        $factorProducto     = $this->factorHastaBase($unidadProducto);

        // Evitar división por cero
        if ($factorProducto == 0) return $cantidad;

        return ($cantidad * $factorSeleccionado) / $factorProducto;
    }

    public function factorHastaBase(Unit $unidad): float
    {
        $factor = 1;
        $u = $unidad;
        $depth = 0;
        // Evitamos bucles infinitos
        while ($u && $depth < 10) {
            $factor *= ($u->quantity > 0 ? $u->quantity : 1);
            $u = $u->unidadBase;
            $depth++;
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
