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

                // 1. Calcular Costo Unitario Base de la entrada
                $costoEntradaBase = 0;
                $unitProduct = $item->product->unit;
                $unitCompra = $item->unit ?? $unitProduct;

                // Factor (Ej. Caja 12u -> Factor 12)
                $factor = $this->convertirCantidad($unitCompra, $unitProduct, 1);

                if ($factor > 0) {
                    // Si la caja cuesta 24, costo unitario base = 2
                    $costoEntradaBase = $item->costo / $factor;
                }

                $this->processItem(
                    item: $item,
                    tipo: 'entrada',
                    comprobante: $purchase->serie . '-' . $purchase->numero,
                    movimiento: $movimiento,
                    costoEntradaUnitario: $costoEntradaBase // Pasamos el costo calculado
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
    private function processItem($item, string $tipo, ?string $comprobante = null, ?string $movimiento = null, ?float $costoEntradaUnitario = null): void
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

        if ($product->receta && $tipo === 'salida') {
            $this->processRecipeItem($item, $variant, $tipo, $comprobante, $movimiento);
        }

        if ($product->control_stock) {
            $this->processDirectItem($item, $variant, $product, $tipo, $comprobante, $movimiento, $costoEntradaUnitario);
        }
    }

    /**
     * Lógica A: Procesa un producto directo (Gaseosa)
     */
    private function processDirectItem($item, $variant, $product, $tipo, $comprobante, $movimiento, $costoEntradaUnitario): void
    {
        $unitProduct = $product->unit;
        $unitSelected = $item->unit ?? $unitProduct;
        $cantidadFinal = $this->convertirCantidad($unitSelected, $unitProduct, $item->cantidad);

        $this->updateStockAndKardex(
            variant: $variant,
            cantidadBase: $cantidadFinal,
            tipo: $tipo,
            comprobante: $comprobante,
            movimiento: $movimiento,
            modelo: $item,
            costoEntradaUnitario: $costoEntradaUnitario
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
     * NÚCLEO CRÍTICO: Actualiza Stock y VARIANTE (Costo)
     */
    private function updateStockAndKardex(Variant $variant, float $cantidadBase, string $tipo, ?string $comprobante, ?string $movimiento, Model $modelo, ?float $costoEntradaUnitario = null): void
    {
        // Bloqueo
        $stock = WarehouseStock::where('variant_id', $variant->id)
            ->where('restaurant_id', filament()->getTenant()->id)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            $stock = WarehouseStock::create([
                'variant_id'       => $variant->id,
                'stock_real'       => 0,
                'stock_reserva'    => 0,
                'costo_promedio'   => 0,
                'valor_inventario' => 0,
                'restaurant_id'    => filament()->getTenant()->id,
            ]);
        }

        // --- LÓGICA DE COMPRA (ENTRADA) ---
        if ($tipo === 'entrada') {
            // 1. Calcular nuevo promedio ponderado
            $valorTotalActual = $stock->valor_inventario;
            $valorEntrada = $cantidadBase * ($costoEntradaUnitario ?? 0);
            $nuevoStockTotal = $stock->stock_real + $cantidadBase;

            $nuevoCostoPromedio = $stock->costo_promedio;

            if ($nuevoStockTotal > 0) {
                // FÓRMULA PPP
                $nuevoCostoPromedio = ($valorTotalActual + $valorEntrada) / $nuevoStockTotal;
            } elseif ($costoEntradaUnitario > 0) {
                $nuevoCostoPromedio = $costoEntradaUnitario;
            }

            // 2. Actualizar WarehouseStock
            $stock->stock_real = $nuevoStockTotal;
            $stock->stock_reserva += $cantidadBase;
            $stock->costo_promedio = $nuevoCostoPromedio;
            $stock->valor_inventario = $nuevoStockTotal * $nuevoCostoPromedio;
            $stock->save();

            // 3. ACTUALIZAR VARIANTE (Para que la venta lo lea después) 🔥
            $variant->update(['costo' => $nuevoCostoPromedio]);
        }

        // --- LÓGICA DE VENTA (SALIDA) ---
        if ($tipo === 'salida') {
            // Solo movemos cantidades, el costo se mantiene
            $allowsNegative = $variant->product->venta_sin_stock ?? false;
            $nuevoStock = $allowsNegative ? $stock->stock_real - $cantidadBase : $stock->stock_real - $cantidadBase;

            $stock->stock_real = $nuevoStock;
            $stock->valor_inventario = $nuevoStock * $stock->costo_promedio;

            if ($movimiento !== 'Venta') {
                $stock->update(['stock_reserva' => $nuevoStock]);
            }
            $stock->save();
        }

        // 4. Registrar Kardex
        // ... (código de kardex igual al anterior) ...
        $variant->kardexes()->create([
            'product_id'       => $variant->product_id,
            'variant_id'       => $variant->id,
            'restaurant_id'    => filament()->getTenant()->id,
            'tipo_movimiento'  => $movimiento ?? $tipo,
            'comprobante'      => $comprobante,
            'cantidad'         => $tipo === 'salida' ? -$cantidadBase : $cantidadBase,
            'costo_unitario'   => $stock->costo_promedio, // Guardamos el costo del momento
            'saldo_valorizado' => $stock->valor_inventario,
            'stock_restante'   => $stock->stock_real,
            'modelo_type'      => get_class($modelo),
            'modelo_id'        => $modelo->id,
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
