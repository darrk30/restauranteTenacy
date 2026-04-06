<?php

namespace App\Services;

use App\Enums\StatusPedido;
use App\Enums\TipoProducto;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Table;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrdenService
{
    public static function obtenerCategorias(?int $tenantId = null): Collection
    {
        return Category::query()
            ->when($tenantId, fn($q) => $q
                ->where('restaurant_id', $tenantId))
            ->where('status', true)
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    public static function obtenerProductos(?int $categoriaId = null, ?string $search = null): \Illuminate\Support\Collection
    {
        // 1. Obtener PRODUCTOS
        $productos = Product::query()
            ->with([
                'attributes',
                'variants' => function ($q) {
                    $q->where('status', 'activo');
                },
                'variants.values.attribute',
                'variants.stock',
            ])
            ->activos()
            ->porCategoria($categoriaId)
            ->whereIn('type', [TipoProducto::Producto, TipoProducto::Servicio])
            ->buscar($search)
            ->get()
            ->map(function ($product) {
                $tipoRaw = $product->type instanceof TipoProducto
                    ? $product->type->value
                    : ($product->type ?? 'Producto');
                $product->type = $tipoRaw;
                return $product;
            });

        // 2. Obtener PROMOCIONES
        $promociones = Promotion::query()
            ->with([
                'rules',
                'promotionproducts.product',
                'promotionproducts.variant.stock'
            ])
            ->when($categoriaId, fn($q) => $q->where('category_id', $categoriaId))
            ->when($search, fn($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->get()
            ->filter(function ($promo) {
                return $promo->isAvailable();
            })
            ->map(function ($promo) {
                $promo->type = TipoProducto::Promocion->value;
                $promo->esta_agotado = false;
                $promo->setRelation('attributes', new Collection());
                return $promo;
            });
        return $productos->concat($promociones)->sortBy('name');
    }

    public static function obtenerProductoId(int $id): Product | null
    {
        $producto = Product::with(['attributes', 'variants.values', 'variants.stock'])->find($id);

        if (!$producto) {
            return null;
        }
        $tipoRaw = $producto->type instanceof TipoProducto
            ? $producto->type->value
            : ($producto->type ?? 'Producto');
        $producto->type = $tipoRaw;

        return $producto;
    }

    public static function crearPedido(array $datosOrden, array $carrito, int $userId = null)
    {
        // 1. Validaciones según canal
        if ($datosOrden['canal'] === 'llevar' && empty($datosOrden['nombre_cliente'])) {
            throw new \Exception('El nombre del cliente es obligatorio para llevar.');
        }

        if ($datosOrden['canal'] === 'delivery') {
            if (empty($datosOrden['nombre_cliente']) || empty($datosOrden['direccion']) || empty($datosOrden['telefono'])) {
                throw new \Exception('Nombre, Dirección y Teléfono son obligatorios para Delivery.');
            }
        }

        DB::beginTransaction();
        try {
            $restaurantId = $datosOrden['restaurant_id'];

            // 🟢 2. INICIALIZAR VARIABLES SEGURAS
            $totalSeguroCalculado = 0;
            $detallesSeguros = [];
            $diffParaCocina = ['nuevos' => [], 'cancelados' => []];

            // 🟢 3. RECALCULAR PRECIOS DESDE LA BASE DE DATOS (Ignoramos el precio del frontend)
            foreach ($carrito as $item) {
                // Soporta 'quantity' (PDV interno) o 'qty' (Carta Web)
                $cantidad = abs((int) ($item['quantity'] ?? $item['qty'] ?? 1));
                $esPromocion = isset($item['type']) && $item['type'] === TipoProducto::Promocion->value;

                $precioUnitarioReal = 0;
                $nombreReal = '';

                // A. OBTENER PRECIO Y NOMBRE REAL
                if ($esPromocion) {
                    $promocion = Promotion::find($item['promotion_id'] ?? null);
                    if (!$promocion) throw new \Exception("Una promoción seleccionada ya no existe.");

                    $precioUnitarioReal = floatval($promocion->price);
                    $nombreReal = $promocion->name;
                } else {
                    // 🟢 CAMBIO 1: Cargamos los atributos junto con el producto
                    $producto = Product::with('attributes')->find($item['product_id'] ?? null);
                    if (!$producto) throw new \Exception("Un producto seleccionado ya no existe.");

                    $precioUnitarioReal = floatval($producto->price);
                    $nombreReal = $producto->name;

                    // Si tiene variante, sumamos los extras (si los hay) al precio base
                    if (!empty($item['variant_id'])) {
                        $variante = Variant::with('values')->find($item['variant_id']);
                        if ($variante) {
                            $extras = 0;
                            $variantValueIds = $variante->values->pluck('id')->toArray();
                            foreach ($producto->attributes as $attr) {
                                $rawValues = $attr->pivot->values ?? [];
                                $opciones = is_string($rawValues) ? json_decode($rawValues, true) : json_decode(json_encode($rawValues), true);
                                if (is_array($opciones)) {
                                    foreach ($opciones as $opcion) {
                                        if (in_array($opcion['id'], $variantValueIds)) {
                                            $extras += floatval($opcion['extra'] ?? 0);
                                        }
                                    }
                                }
                            }
                            $precioUnitarioReal += $extras;
                            $nombreVariante = $variante->values->pluck('name')->join(' / ');
                            $nombreReal .= " ($nombreVariante)";
                        }
                    }
                }

                // B. APLICAR CORTESÍA (Si es cortesía, el costo es cero)
                $esCortesia = ($item['is_cortesia'] ?? false) ? 1 : 0;
                if ($esCortesia) {
                    $precioUnitarioReal = 0;
                }

                // C. SUMAR AL TOTAL SEGURO
                $subTotalReal = $precioUnitarioReal * $cantidad;
                $totalSeguroCalculado += $subTotalReal;

                // Obtener Área de impresión
                $areaData = self::obtenerDatosArea($item['product_id'] ?? null, $item['promotion_id'] ?? null);

                // D. GUARDAR EL DETALLE LIMPIO
                $detallesSeguros[] = [
                    'product_id'   => $esPromocion ? null : ($item['product_id'] ?? null),
                    'promotion_id' => $esPromocion ? ($item['promotion_id'] ?? null) : null,
                    'variant_id'   => $item['variant_id'] ?? null,
                    'item_type'    => $esPromocion ? TipoProducto::Promocion->value : TipoProducto::Producto->value,
                    'product_name' => $nombreReal, // Dato seguro de BD
                    'price'        => $precioUnitarioReal, // Dato seguro de BD
                    'cantidad'     => $cantidad,
                    'subTotal'     => $subTotalReal, // Dato seguro de BD
                    'cortesia'     => $esCortesia,
                    'status'       => StatusPedido::Pendiente,
                    'notes'        => strip_tags($item['notes'] ?? ''), // Limpiamos HTML malicioso
                    'area_id'      => $areaData['id'],
                    'area_nombre'  => $areaData['name']
                ];
            }

            // 🟢 4. CALCULAR IMPUESTOS REALES
            $subtotalSeguro = $totalSeguroCalculado / 1.18; // Asumiendo IGV 18% incluido
            $igvSeguro = $totalSeguroCalculado - $subtotalSeguro;

            // 5. Generar Código Único
            $ultimoPedido = Order::where('restaurant_id', $restaurantId)->lockForUpdate()->orderBy('id', 'desc')->first();
            $numeroSiguiente = $ultimoPedido ? intval($ultimoPedido->code) + 1 : 1;
            $codigoFinal = str_pad($numeroSiguiente, 8, '0', STR_PAD_LEFT);

            // 6. Crear Orden con Datos SEGUROS
            $order = Order::create([
                'restaurant_id'   => $restaurantId,
                'table_id'        => ($datosOrden['canal'] === 'salon') ? ($datosOrden['mesa_id'] ?? null) : null,
                'client_id'       => $datosOrden['cliente_id'] ?? null,
                'canal'           => $datosOrden['canal'],
                'nombre_cliente'  => strip_tags($datosOrden['nombre_cliente'] ?? null),
                'nombre_delivery' => strip_tags($datosOrden['nombre_repartidor'] ?? null),
                'delivery_id'     => $datosOrden['delivery_id'] ?? null,
                'direccion'       => strip_tags($datosOrden['direccion'] ?? null),
                'telefono'        => strip_tags($datosOrden['telefono'] ?? null),
                'code'            => $codigoFinal,
                'status'          => StatusPedido::Pendiente,
                'subtotal'        => $subtotalSeguro, // SEGURO
                'igv'             => $igvSeguro,      // SEGURO
                'total'           => $totalSeguroCalculado, // SEGURO
                'fecha_pedido'    => now(),
                'user_id'         => $userId,
                'user_actualiza_id' => null,
                'web'             => $datosOrden['web'] ?? false,
                'payment_method_id' => $datosOrden['payment_method_id'] ?? null,
                'notas'           => strip_tags($datosOrden['notas'] ?? null),
            ]);

            // 7. Insertar Detalles, Preparar Ticket y Descontar Stock
            foreach ($detallesSeguros as $itemSeguro) {
                OrderDetail::create([
                    'restaurant_id'      => $restaurantId,
                    'order_id'           => $order->id,
                    'product_id'         => $itemSeguro['product_id'],
                    'promotion_id'       => $itemSeguro['promotion_id'],
                    'item_type'          => $itemSeguro['item_type'],
                    'variant_id'         => $itemSeguro['variant_id'],
                    'product_name'       => $itemSeguro['product_name'],
                    'price'              => $itemSeguro['price'],
                    'cantidad'           => $itemSeguro['cantidad'],
                    'subTotal'           => $itemSeguro['subTotal'],
                    'cortesia'           => $itemSeguro['cortesia'],
                    'status'             => $itemSeguro['status'],
                    'notes'              => $itemSeguro['notes'],
                    'fecha_envio_cocina' => now(),
                    'user_id'            => $userId,
                ]);

                // Preparar ticket de comanda
                $diffParaCocina['nuevos'][] = [
                    'cant'        => $itemSeguro['cantidad'],
                    'nombre'      => $itemSeguro['product_name'],
                    'nota'        => $itemSeguro['notes'],
                    'area_id'     => $itemSeguro['area_id'],
                    'area_nombre' => $itemSeguro['area_nombre']
                ];

                // Restar Stock
                $esPromo = $itemSeguro['item_type'] === TipoProducto::Promocion->value;
                if (!$esPromo && $itemSeguro['variant_id']) {
                    self::gestionarStock($itemSeguro['variant_id'], $itemSeguro['cantidad'], 'restar');
                } elseif ($esPromo && $itemSeguro['promotion_id']) {
                    self::gestionarStockPromocion($itemSeguro['promotion_id'], $itemSeguro['cantidad'], 'restar');
                }
            }

            // 8. Ocupar Mesa si es Salón
            if ($datosOrden['canal'] === 'salon' && !empty($datosOrden['mesa_id'])) {
                Table::where('id', $datosOrden['mesa_id'])->update([
                    'estado_mesa' => 'ocupada',
                    'order_id'    => $order->id,
                    'asientos'    => $datosOrden['personas'] ?? 1
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'order' => $order,
                'diffParaCocina' => $diffParaCocina
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function obtenerDatosArea($productId = null, $promotionId = null)
    {
        // Si es promoción
        if ($promotionId) {
            $promo = Promotion::with('production.printer')->find($promotionId);
            $prod = $promo?->production;
            if ($prod && $prod->status) return ['id' => $prod->id, 'name' => $prod->name];
        }
        // Si es producto
        elseif ($productId) {
            $producto = Product::with('production.printer')->find($productId);
            $prod = $producto?->production;
            if ($prod && $prod->status) return ['id' => $prod->id, 'name' => $prod->name];
        }

        return ['id' => 'general', 'name' => 'GENERAL'];
    }

    public static function gestionarStock($variantId, $cantidad, $operacion = 'restar')
    {
        $variant = Variant::with('stock')->find($variantId);
        if (!$variant || !$variant->stock) return;
        $product = $variant->product;
        if ($product && $product->control_stock == 0) return;
        $stockRegistro = $variant->stock;
        if ($operacion === 'restar') {
            $stockRegistro->decrement('stock_reserva', $cantidad);
        } else {
            $stockRegistro->increment('stock_reserva', $cantidad);
        }
    }

    public static function gestionarStockPromocion($promoId, $cantidadPromo, $operacion = 'restar')
    {
        $promo = \App\Models\Promotion::with('promotionproducts.product.variants')->find($promoId);

        if (!$promo) return;

        foreach ($promo->promotionproducts as $detalle) {
            $producto = $detalle->product;

            // Si el producto no controla stock, lo ignoramos
            if (!$producto || $producto->control_stock == 0) continue;

            // Cantidad total a restar del ingrediente = (Cantidad en Receta * Cantidad de Promos)
            $totalIngrediente = $detalle->quantity * $cantidadPromo;

            // 1. Si el ingrediente tiene variante específica definida
            if ($detalle->variant_id) {
                self::gestionarStock($detalle->variant_id, $totalIngrediente, $operacion);
            }
            // 2. Si es un producto simple (sin variante en la receta), buscamos su variante por defecto
            elseif ($detalle->product_id) {
                // Asumimos que el producto simple tiene una única variante principal
                $variant = $producto->variants->first();
                if ($variant) {
                    self::gestionarStock($variant->id, $totalIngrediente, $operacion);
                }
            }
        }
    }
}
