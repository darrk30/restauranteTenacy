<?php

namespace App\Traits;

use App\Models\Kardex;
use App\Models\Unit;
use App\Models\Variant;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Models\SaleDetail;
use Illuminate\Database\Eloquent\Model;

trait ManjoStockProductos
{
    /*
    |--------------------------------------------------------------------------
    | PUNTOS DE ENTRADA (APPLY / REVERSE)
    |--------------------------------------------------------------------------
    */

    public function applyAdjustment($adjustment): void
    {
        foreach ($adjustment->items as $item) {
            $unitProduct = $item->product->unit;
            $unitAjuste = $item->unit ?? $unitProduct;
            $factor = $this->convertirCantidad($unitAjuste, $unitProduct, 1);
            $costoBase = ($factor > 0) ? ($item->costo / $factor) : 0;

            $this->processItem(
                item: $item,
                tipo: $adjustment->tipo,
                comprobante: $adjustment->codigo,
                movimiento: "Ajuste: " . $adjustment->motivo,
                costoEntradaUnitario: $costoBase
            );
        }
    }

    public function reverseAdjustment($adjustment, $movimiento = null): void
    {
        foreach ($adjustment->items as $item) {
            $this->reverseItem(
                item: $item,
                tipo: $adjustment->tipo, // 'entrada' o 'salida' original
                comprobante: $adjustment->codigo,
                movimiento: "Anulación Ajuste: " . $adjustment->codigo,
            );
        }
    }

    public function applyPurchase($purchase, $movimiento = null): void
    {
        foreach ($purchase->details as $item) {
            if ($purchase->estado_despacho === 'recibido') {
                $unitProduct = $item->product->unit;
                $unitCompra = $item->unit ?? $unitProduct;
                $factor = $this->convertirCantidad($unitCompra, $unitProduct, 1);
                $costoBase = ($factor > 0) ? ($item->costo / $factor) : 0;

                $this->processItem(
                    item: $item,
                    tipo: 'entrada',
                    comprobante: $purchase->serie . '-' . $purchase->numero,
                    movimiento: $movimiento ?? "Compra: " . ($purchase->supplier?->name ?? 'Proveedor'),
                    costoEntradaUnitario: $costoBase
                );
            }
        }
    }

    public function reversePurchase($purchase, $movimiento = null): void
    {
        foreach ($purchase->details as $item) {
            // Revertimos la entrada de la compra
            $this->reverseItem(
                item: $item,
                tipo: 'entrada',
                comprobante: $purchase->serie . '-' . $purchase->numero,
                movimiento: $movimiento ?? "Compra Anulada: " . $purchase->numero
            );
        }
    }

    public function applyVentaMasiva($items, string $tipo, ?string $comprobante = null, ?string $movimiento = null): void
    {
        foreach ($items as $item) {
            $this->processItem($item, $tipo, $comprobante, $movimiento);
        }
    }

    public function reverseVenta($sale, $movimiento = null): void
    {
        foreach ($sale->details as $item) {
            // Ya no desarmamos la promoción aquí.
            // Le pasamos el ítem crudo a reverseItem. Esta función ya sabe cómo 
            // desarmar combos, pasar el ID a todos los hijos y validar el control de stock.
            $this->reverseItem(
                item: $item, 
                tipo: 'salida', 
                comprobante: $sale->serie . '-' . $sale->correlativo, 
                movimiento: $movimiento
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LÓGICA DE PROCESAMIENTO (RECURSIVIDAD)
    |--------------------------------------------------------------------------
    */

    private function processItem($item, string $tipo, ?string $comprobante = null, ?string $movimiento = null, ?float $costoEntradaUnitario = null): void
    {
        if ($item->promotion_id && $item->promotion) {
            foreach ($item->promotion->promotionproducts as $subItem) {
                $virtualItem = new SaleDetail([
                    'product_id' => $subItem->product_id,
                    'variant_id' => $subItem->variant_id,
                    'cantidad'   => $item->cantidad * $subItem->quantity,
                ]);
                $virtualItem->id = $item->id;
                $virtualItem->setRelation('product', $subItem->product);
                $virtualItem->setRelation('variant', $subItem->variant);
                $virtualItem->setRelation('unit', $subItem->product->unit);
                $this->processItem($virtualItem, $tipo, $comprobante, $movimiento);
            }
            return;
        }

        $product = $item->product ?? Product::find($item->product_id);
        $variant = $item->variant ?? Variant::find($item->variant_id);

        if (!$product || !$variant) return;

        if ($product->receta && $tipo === 'salida') {
            $this->processRecipeItem($item, $variant, $tipo, $comprobante, $movimiento);
        }

        if ($product->control_stock) {
            $unitProduct = $product->unit;
            $unitSelected = $item->unit ?? $unitProduct;
            $cantidadBase = $this->convertirCantidad($unitSelected, $unitProduct, $item->cantidad);

            $this->updateStockAndKardex($variant, $cantidadBase, $tipo, $comprobante, $movimiento, $item, $costoEntradaUnitario);
        }
    }

    private function processRecipeItem($item, $variant, $tipo, $comprobante, $movimiento): void
    {
        $ingredientes = $variant->recetas;
        $rendimiento = (float) ($variant->rendimiento ?? 1) ?: 1;

        foreach ($ingredientes as $ingrediente) {
            $insumoVariant = Variant::with('product', 'product.unit')->find($ingrediente->insumo_id);
            if ($insumoVariant && $insumoVariant->product) {
                $cantidadPorPlato = ($variant->lote ?? false) ? ($ingrediente->cantidad / $rendimiento) : $ingrediente->cantidad;
                $cantidadTotal = $item->cantidad * $cantidadPorPlato;
                $cantidadBase = $this->convertirCantidad($ingrediente->unit, $insumoVariant->product->unit, $cantidadTotal);

                $this->updateStockAndKardex($insumoVariant, $cantidadBase, $tipo, $comprobante, $movimiento ?? 'Consumo Receta', $item);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NÚCLEO MATEMÁTICO: CPP Y KARDEX (SIEMPRE CREA REGISTRO)
    |--------------------------------------------------------------------------
    */

    private function updateStockAndKardex(Variant $variant, float $cantidadBase, string $tipo, ?string $comprobante, ?string $movimiento, Model $modelo, ?float $costoEntradaUnitario = null): void
    {
        $stock = WarehouseStock::firstOrCreate(
            ['variant_id' => $variant->id, 'restaurant_id' => filament()->getTenant()->id],
            ['stock_real' => 0, 'stock_reserva' => 0, 'costo_promedio' => 0, 'valor_inventario' => 0]
        );

        $costoUnitarioFila = 0;

        if ($tipo === 'entrada') {
            $costoUnitarioFila = $costoEntradaUnitario ?? 0;
            $valorMovimiento = $cantidadBase * $costoUnitarioFila;

            $nuevoStock = $stock->stock_real + $cantidadBase;
            // CPP = (Valor Anterior + Valor Nuevo) / Stock Nuevo
            $nuevoCostoPromedio = ($nuevoStock > 0) ? ($stock->valor_inventario + $valorMovimiento) / $nuevoStock : $costoUnitarioFila;

            $stock->update([
                'stock_real' => $nuevoStock,
                'stock_reserva' => $stock->stock_reserva + $cantidadBase,
                'costo_promedio' => $nuevoCostoPromedio,
                'valor_inventario' => $nuevoStock * $nuevoCostoPromedio,
            ]);
        } else {
            // Salidas: Salen al costo promedio actual
            $costoUnitarioFila = ($costoEntradaUnitario > 0) ? $costoEntradaUnitario : $stock->costo_promedio;
            $valorMovimiento = $cantidadBase * $costoUnitarioFila;

            $nuevoStock = $stock->stock_real - $cantidadBase;

            $stock->update([
                'stock_real' => $nuevoStock,
                'valor_inventario' => max(0, $stock->valor_inventario - $valorMovimiento),
            ]);

            // En salidas el promedio no debería cambiar drásticamente, pero recalculamos por precisión decimal
            if ($nuevoStock > 0) {
                $stock->costo_promedio = $stock->valor_inventario / $nuevoStock;
            }

            if ($movimiento !== 'Venta') {
                $stock->stock_reserva = $nuevoStock;
            }
            $stock->save();
        }

        // Sincronizar costo en variante
        $variant->update(['costo' => $stock->costo_promedio]);

        // REGISTRO SIEMPRE NUEVO EN KARDEX
        $variant->kardexes()->create([
            'product_id'       => $variant->product_id,
            'restaurant_id'    => filament()->getTenant()->id,
            'tipo_movimiento'  => $movimiento ?? $tipo,
            'comprobante'      => $comprobante,
            'cantidad'         => ($tipo === 'salida') ? -$cantidadBase : $cantidadBase,
            'costo_unitario'   => $costoUnitarioFila,
            'costo_total'      => $cantidadBase * $costoUnitarioFila,
            'stock_restante'   => $stock->stock_real,
            'saldo_valorizado' => $stock->valor_inventario,
            'costo_promedio'   => $stock->costo_promedio, // Foto del promedio después del movimiento
            'modelo_type'      => get_class($modelo),
            'modelo_id'        => $modelo->id,
        ]);
    }

    // Cambiamos $tipoOriginal por $tipo para que coincida con los argumentos nombrados
    // ... [Resto de tu Trait] ...

    private function reverseItem($item, string $tipo, ?string $comprobante = null, ?string $movimiento = null): void
    {
        // 1. Si el ítem es una promoción, se desarma aquí (heredando el ID)
        if ($item->promotion_id && $item->promotion) {
            foreach ($item->promotion->promotionproducts as $subItem) {
                $virtualItem = new SaleDetail([
                    'product_id' => $subItem->product_id,
                    'variant_id' => $subItem->variant_id,
                    'cantidad'   => $item->cantidad * $subItem->quantity,
                ]);
                
                // 🟢 HEREDAMOS EL ID DEL SALEDETAIL ORIGINAL
                $virtualItem->id = $item->id;
                
                $virtualItem->setRelation('product', $subItem->product);
                $virtualItem->setRelation('variant', $subItem->variant);
                $virtualItem->setRelation('unit', $subItem->product->unit);

                // Llamada recursiva: ahora entrará como un producto normal con ID
                $this->reverseItem($virtualItem, $tipo, $comprobante, $movimiento);
            }
            return; // Detenemos la ejecución para este ítem padre
        }

        // 2. Lógica para productos normales (o hijos de la promoción ya desarmados)
        $variant = $item->variant;
        $product = $item->product ?? Product::find($item->product_id);

        if (!$variant || !$product) return;

        // 🟢 VALIDAMOS ESTRICTAMENTE EL CONTROL DE STOCK DE CADA HIJO
        if (!$product->control_stock) return;

        $cantidadBase = $this->convertirCantidad($item->unit ?? $product->unit, $product->unit, $item->cantidad);
        $tipoInverso = ($tipo === 'entrada') ? 'salida' : 'entrada';

        // Buscamos el costo exacto con el que salió esta variante específica
        $registroOriginal = Kardex::where('modelo_type', get_class($item))
            ->where('modelo_id', $item->id)
            ->where('variant_id', $variant->id)
            ->first();

        $costoOriginal = $registroOriginal ? $registroOriginal->costo_unitario : ($item->costo_unitario ?? 0);

        // Generamos el movimiento de compensación en el Kardex
        $this->updateStockAndKardex(
            variant: $variant,
            cantidadBase: $cantidadBase,
            tipo: $tipoInverso,
            comprobante: $comprobante,
            movimiento: $movimiento ?? ($tipo . '-anulado'),
            modelo: $item,
            costoEntradaUnitario: $costoOriginal
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function convertirCantidad(Unit $uSel, Unit $uProd, float $cantidad): float
    {
        if ($uSel->id === $uProd->id) return $cantidad;
        $fSel = $this->factorHastaBase($uSel);
        $fProd = $this->factorHastaBase($uProd);
        return ($fProd == 0) ? $cantidad : ($cantidad * $fSel) / $fProd;
    }

    public function factorHastaBase(Unit $unidad): float
    {
        $factor = 1;
        $u = $unidad;
        $depth = 0;
        while ($u && $depth < 10) {
            $factor *= ($u->quantity > 0 ? $u->quantity : 1);
            $u = $u->unidadBase;
            $depth++;
        }
        return $factor;
    }

    private function getReverseMovementName($item, string $tipo): string
    {
        return strtolower(class_basename($item)) . '-anulado';
    }
}
