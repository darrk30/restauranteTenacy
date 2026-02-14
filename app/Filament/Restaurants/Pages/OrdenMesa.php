<?php

namespace App\Filament\Restaurants\Pages;

use App\Enums\statusPedido;
use App\Enums\TipoProducto;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Table;
use App\Services\OrdenService;
use App\Models\Variant;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrdenMesa extends Page implements HasActions
{
    use InteractsWithActions;
    protected static string $view = 'filament.pdv.orden-mesa';
    protected static string $panel = 'restaurants';

    public $subtotal = 0.00;
    public $igv = 0.00;
    public $total = 0.00;
    public $carrito = [];
    public $hayCambios = false;
    public $itemsEliminados = [];
    public $lastUpdatedItemId = null;

    public $codigoOrden = null;
    public $personas = 1;
    public $categoriaSeleccionada = null;
    public $search = '';

    public $cantidadesOriginales = [];
    public $notasOriginales = [];
    public $preciosOriginales = [];

    public $stockActualVariante = 0;
    public $stockReservaVariante = 0;

    public Product|Promotion|null $productoSeleccionado = null;
    public $variantSeleccionadaId = null;
    public $esCortesia = false;
    public $notaPedido = '';

    public $selectedAttributes = [];
    public $precioCalculado = 0.00;

    public $mostrarModalComanda = false;
    public ?Order $ordenGenerada = null;

    public $stocksProductos = [];
    public $cantidadesOriginalesPorVariante = [];
    public $tenantSlug;

    public $canal = 'salon';
    public $nombre_cliente = null;
    public $nombre_repartidor = null;
    public $direccion = null;
    public $telefono = null;
    public $delivery_id = null;
    public $cliente_id = null;

    public $mesa = null;
    public ?int $pedido = null;

    public function mount(Request $request, $mesa = null, ?int $pedido = null)
    {
        // Captura inicial de datos de la URL
        $this->canal = $request->query('canal', 'salon');
        $this->nombre_cliente = $request->query('nombre');
        $this->cliente_id = $request->query('cliente_id');
        $this->direccion = $request->query('direccion');
        $this->telefono = $request->query('telefono');
        $this->delivery_id = $request->query('delivery_id');
        $this->nombre_repartidor = $request->query('nombre_delivery');

        $this->mesa = ($mesa === 'nuevo' || $mesa == 0) ? null : $mesa;
        $this->pedido = $pedido;
        $this->tenantSlug = Filament::getTenant()->slug;

        // Cargamos los datos del pedido (si existe)
        $this->cargarDatosPedido();
    }

    /**
     * Método extraído para evitar el error de Request en llamadas manuales
     */
    public function cargarDatosPedido()
    {
        if (session()->has('personas_iniciales')) {
            $this->personas = session('personas_iniciales');
        }

        if (session()->has('orden_creada_id')) {
            $idOrden = session('orden_creada_id');
            $this->ordenGenerada = Order::with(['details.product.production.printer', 'table', 'user'])->find($idOrden);
            if ($this->ordenGenerada) {
                $this->mostrarModalComanda = true;
            }
        }

        if ($this->pedido) {
            $ordenExistente = Order::with(['details.product'])->find($this->pedido);

            if (!$ordenExistente || $ordenExistente->status === statusPedido::Cancelado) {
                return redirect()->to("/app/point-of-sale");
            }

            // Sincronizar datos de la orden
            $this->cliente_id = $ordenExistente->cliente_id;
            $this->canal = $ordenExistente->canal ?? 'salon';
            $this->nombre_cliente = $ordenExistente->nombre_cliente;
            $this->direccion = $ordenExistente->direccion;
            $this->telefono = $ordenExistente->telefono;
            $this->nombre_repartidor = $ordenExistente->nombre_delivery;
            $this->delivery_id = $ordenExistente->delivery_id;
            $this->codigoOrden = $ordenExistente->code;
            $this->subtotal = $ordenExistente->subtotal;
            $this->igv = $ordenExistente->igv;
            $this->total = $ordenExistente->total;

            $this->carrito = $ordenExistente->details->map(function ($detalle) {
                $this->cantidadesOriginales[$detalle->id] = $detalle->cantidad;
                $this->notasOriginales[$detalle->id] = $detalle->notes;
                $this->preciosOriginales[$detalle->id] = $detalle->price;

                $esPromocion = $detalle->item_type === TipoProducto::Promocion->value || !empty($detalle->promotion_id);
                $tipo = $esPromocion ? TipoProducto::Promocion->value : TipoProducto::Producto->value;
                $idReal = $esPromocion ? $detalle->promotion_id : $detalle->product_id;

                return [
                    'item_id'      => $detalle->id,
                    'product_id'   => $esPromocion ? null : $detalle->product_id,
                    'variant_id'   => $detalle->variant_id,
                    'promotion_id' => $esPromocion ? $detalle->promotion_id : null,
                    'type'         => $tipo,
                    'name'         => $detalle->product_name,
                    'price'        => $detalle->price,
                    'quantity'     => $detalle->cantidad,
                    'total'        => $detalle->subTotal,
                    'is_cortesia'  => (bool) $detalle->cortesia,
                    'notes'        => $detalle->notes,
                    'image'        => $esPromocion
                        ? (\App\Models\Promotion::find($idReal)?->image_path)
                        : ($detalle->product ? $detalle->product->image_path : null),
                    'guardado'     => true,
                ];
            })->toArray();
        }

        $this->hayCambios = false;
        $this->itemsEliminados = [];
    }

    // --- MÉTODO AUXILIAR PARA OBTENER EL ÁREA ---
    private function obtenerDatosArea($productId)
    {
        $producto = Product::with('production.printer')->find($productId);
        $prod = $producto?->production;
        if ($prod && $prod->status) return ['id' => $prod->id, 'name' => $prod->name];
        return ['id' => 'general', 'name' => 'GENERAL'];
    }

    // --- PROCESAR ORDEN (CREAR NUEVA) ---
    public function procesarOrden()
    {
        if (empty($this->carrito)) {
            Notification::make()->title('El carrito está vacío')->warning()->send();
            return;
        }

        // VALIDACIONES SEGÚN CANAL
        if ($this->canal === 'llevar' && empty($this->nombre_cliente)) {
            Notification::make()->title('El nombre del cliente es obligatorio para llevar')->danger()->send();
            return;
        }

        if ($this->canal === 'delivery') {
            if (empty($this->nombre_cliente) || empty($this->direccion) || empty($this->telefono)) {
                Notification::make()->title('Nombre, Dirección y Teléfono son obligatorios para Delivery')->danger()->send();
                return;
            }
        }

        try {
            DB::beginTransaction();
            $restaurantId = Filament::getTenant()->id;

            // Generar Código
            $ultimoPedido = Order::where('restaurant_id', $restaurantId)->lockForUpdate()->orderBy('id', 'desc')->first();
            $numeroSiguiente = $ultimoPedido ? intval($ultimoPedido->code) + 1 : 1;
            $codigoFinal = str_pad($numeroSiguiente, 8, '0', STR_PAD_LEFT);

            // 1. Crear Orden con datos dinámicos
            $order = Order::create([
                'restaurant_id' => $restaurantId,
                'table_id'      => ($this->canal === 'salon') ? $this->mesa : null, // Solo guarda mesa si es salón
                'client_id'    => $this->cliente_id,
                'canal'         => $this->canal,
                'nombre_cliente' => $this->nombre_cliente,
                'nombre_delivery' => $this->nombre_repartidor,
                'delivery_id'   => $this->delivery_id,
                'direccion'     => $this->direccion,
                'telefono'      => $this->telefono,
                'code'          => $codigoFinal,
                'status'        => statusPedido::Pendiente,
                'subtotal'      => $this->subtotal,
                'igv'           => $this->igv,
                'total'         => $this->total,
                'fecha_pedido'  => now(),
                'user_id'       => Auth::id(),
            ]);

            // 2. Detalles y Stock (Se mantiene igual que tu lógica original)
            foreach ($this->carrito as $item) {
                $esPromocion = isset($item['type']) && $item['type'] === TipoProducto::Promocion->value;
                OrderDetail::create([
                    'order_id'      => $order->id,
                    'product_id'    => $esPromocion ? null : $item['product_id'],
                    'promotion_id'  => $esPromocion ? $item['promotion_id'] : null,
                    'item_type'     => $esPromocion ? TipoProducto::Promocion->value : TipoProducto::Producto->value,
                    'variant_id'    => $item['variant_id'],
                    'product_name'  => $item['name'],
                    'price'         => $item['price'],
                    'cantidad'      => $item['quantity'],
                    'subTotal'      => $item['total'],
                    'cortesia'      => $item['is_cortesia'] ? 1 : 0,
                    'status'        => statusPedido::Pendiente,
                    'notes'         => $item['notes'],
                    'fecha_envio_cocina' => now(),
                ]);

                if (!$esPromocion) {
                    $this->gestionarStock($item['variant_id'], $item['quantity'], 'restar');
                } else {
                    $this->gestionarStockPromocion($item['promotion_id'], $item['quantity'], 'restar');
                }
            }

            // 3. Actualizar Mesa solo si es Salón
            if ($this->canal === 'salon' && $this->mesa) {
                Table::where('id', $this->mesa)->update([
                    'estado_mesa' => 'ocupada',
                    'order_id' => $order->id,
                    'asientos' => $this->personas
                ]);
            }

            DB::commit();

            // Redirección dinámica
            $paramMesa = $this->mesa ?? 'nuevo';
            return redirect()
                ->to("/app/orden-mesa/{$paramMesa}/{$order->id}")
                ->with('orden_creada_id', $order->id);
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error al procesar')->body($e->getMessage())->danger()->send();
        }
    }

    // --- ACTUALIZAR ORDEN (MODIFICAR) ---
    public function actualizarOrden()
    {
        if (!$this->pedido) return;
        try {
            $diffParaCocina = [
                'nuevos' => [],
                'cancelados' => []
            ];

            foreach ($this->carrito as $item) {
                // Obtenemos el área
                $areaData = $this->obtenerDatosArea($item['product_id']);

                if (!isset($item['guardado']) || !$item['guardado']) {
                    // CASO 1: PRODUCTO NUEVO (Siempre nota completa)
                    $diffParaCocina['nuevos'][] = [
                        'cant' => $item['quantity'],
                        'nombre' => $item['name'],
                        'nota' => $item['notes'],
                        'area_id' => $areaData['id'],
                        'area_nombre' => $areaData['name']
                    ];
                } else {
                    // CASO 2: PRODUCTO EXISTENTE
                    $idDetalle = $item['item_id'];
                    $cantidadOriginal = $this->cantidadesOriginales[$idDetalle] ?? 0;
                    $cantidadActual = $item['quantity'];

                    // Verificamos si la nota cambió
                    $notaOriginal = $this->notasOriginales[$idDetalle] ?? '';
                    $notaActual = $item['notes'];
                    $notaParaImprimir = ($notaActual !== $notaOriginal) ? $notaActual : '';

                    if ($cantidadActual > $cantidadOriginal) {
                        $diferencia = $cantidadActual - $cantidadOriginal;
                        $diffParaCocina['nuevos'][] = [
                            'cant' => $diferencia,
                            'nombre' => $item['name'],
                            'nota' => $notaParaImprimir, // Solo si cambió
                            'area_id' => $areaData['id'],
                            'area_nombre' => $areaData['name']
                        ];
                    } elseif ($cantidadActual < $cantidadOriginal) {
                        $diferencia = $cantidadOriginal - $cantidadActual;
                        $diffParaCocina['cancelados'][] = [
                            'cant' => $diferencia,
                            'nombre' => $item['name'],
                            'nota' => $item['notes'], // En cancelación mantenemos nota para identificar
                            'area_id' => $areaData['id'],
                            'area_nombre' => $areaData['name']
                        ];
                    } elseif ($notaParaImprimir !== '') {
                        // CASO 3: MISMA CANTIDAD PERO CAMBIÓ LA NOTA (MODIFICACIÓN)
                        // Enviamos como "nuevo" pero con cantidad 0 o indicativo de cambio de nota
                        // Opcional: Podrías manejar esto como una reimpresión de nota
                        $diffParaCocina['nuevos'][] = [
                            'cant' => $cantidadActual, // Se reimprime todo el item con la nueva nota
                            'nombre' => $item['name'] . ' (MODIF. NOTA)',
                            'nota' => $notaParaImprimir,
                            'area_id' => $areaData['id'],
                            'area_nombre' => $areaData['name']
                        ];
                    }
                }
            }

            if (!empty($this->itemsEliminados)) {
                $itemsABorrar = OrderDetail::whereIn('id', $this->itemsEliminados)->get();
                foreach ($itemsABorrar as $item) {
                    $areaData = $this->obtenerDatosArea($item->product_id);
                    $diffParaCocina['cancelados'][] = [
                        'cant' => $item->cantidad,
                        'nombre' => $item->product_name,
                        'nota' => $item->notes,
                        'area_id' => $areaData['id'],
                        'area_nombre' => $areaData['name']
                    ];
                }
            }

            // Guardar Cache
            if (!empty($diffParaCocina['nuevos']) || !empty($diffParaCocina['cancelados'])) {
                $jobId = 'print_' . $this->pedido . '_' . time();
                Cache::put($jobId, $diffParaCocina, now()->addMinutes(5));
                session()->flash('print_job_id', $jobId);
            }

            DB::beginTransaction();

            $order = Order::find($this->pedido);
            $order->update([
                'subtotal' => $this->subtotal,
                'igv'      => $this->igv,
                'total'    => $this->total,
            ]);

            foreach ($this->carrito as $item) {
                $esPromocion = isset($item['type']) && $item['type'] === TipoProducto::Promocion->value;
                if (!isset($item['guardado']) || !$item['guardado']) {
                    OrderDetail::create([
                        'order_id'      => $order->id,
                        'product_id'     => $esPromocion ? null : $item['product_id'], // Corregido
                        'promotion_id'   => $esPromocion ? $item['promotion_id'] : null, // Corregido
                        'variant_id'    => $item['variant_id'],
                        'product_name'  => $item['name'],
                        'item_type'      => $esPromocion ? TipoProducto::Promocion->value : TipoProducto::Producto->value,
                        'price'         => $item['price'],
                        'cantidad'      => $item['quantity'],
                        'subTotal'      => $item['total'],
                        'cortesia'      => $item['is_cortesia'] ? 1 : 0,
                        'status'        => statusPedido::Pendiente,
                        'notes'         => $item['notes'],
                        'fecha_envio_cocina' => now(),
                    ]);
                    if (!$esPromocion) {
                        $this->gestionarStock($item['variant_id'], $item['quantity'], 'restar');
                    } else {
                        // AQUÍ FALTABA ESTO:
                        $this->gestionarStockPromocion($item['promotion_id'], $item['quantity'], 'restar');
                    }
                } else {
                    $detalle = OrderDetail::find($item['item_id']);
                    if ($detalle) {
                        // Actualizamos la nota en BD
                        $detalle->notes = $item['notes'];

                        $cantidadAnterior = $detalle->cantidad;
                        $cantidadNueva = $item['quantity'];

                        if ($cantidadNueva != $cantidadAnterior) {
                            $detalle->update(['cantidad' => $cantidadNueva, 'subTotal' => $item['total'], 'notes' => $item['notes']]);

                            // CÁLCULO DE DIFERENCIA
                            if ($cantidadNueva > $cantidadAnterior) {
                                $diff = $cantidadNueva - $cantidadAnterior;
                                // Restar stock (Consumimos más)
                                if (!$esPromocion) {
                                    $this->gestionarStock($item['variant_id'], $diff, 'restar');
                                } else {
                                    $this->gestionarStockPromocion($item['promotion_id'], $diff, 'restar');
                                }
                            } else {
                                $diff = $cantidadAnterior - $cantidadNueva;
                                // Sumar stock (Devolvemos)
                                if (!$esPromocion) {
                                    $this->gestionarStock($item['variant_id'], $diff, 'sumar');
                                } else {
                                    $this->gestionarStockPromocion($item['promotion_id'], $diff, 'sumar');
                                }
                            }
                        } else {
                            $detalle->save();
                        }
                    }
                }
            }

            if (!empty($this->itemsEliminados)) {
                $detallesABorrar = OrderDetail::whereIn('id', $this->itemsEliminados)->get();
                foreach ($detallesABorrar as $borrado) {
                    // Detectar si era promo
                    $eraPromo = $borrado->item_type === TipoProducto::Promocion->value || $borrado->promotion_id;

                    if (!$eraPromo) {
                        $this->gestionarStock($borrado->variant_id, $borrado->cantidad, 'sumar');
                    } else {
                        // Devolvemos ingredientes de la promo
                        $this->gestionarStockPromocion($borrado->promotion_id, $borrado->cantidad, 'sumar');
                    }

                    $borrado->delete();
                }
            }

            DB::commit();

            if (!empty($diffParaCocina['nuevos']) || !empty($diffParaCocina['cancelados'])) {
                $jobId = 'print_' . $this->pedido . '_' . time();
                Cache::put($jobId, $diffParaCocina, now()->addMinutes(5));

                // Usamos session()->flash para que el x-modal-ticket lo detecte en este request
                session()->flash('print_job_id', $jobId);
            }

            // === PASO CLAVE 2: Refrescar el objeto para el modal ===
            $this->ordenGenerada = $order->refresh()->load(['details.product.production.printer', 'table', 'user']);
            $this->mostrarModalComanda = true; // Forzamos la visibilidad

            Notification::make()->title('Orden actualizada')->success()->send();

            // === PASO CLAVE 3: Reiniciar estados del carrito sin recargar la página entera ===
            $this->cargarDatosPedido();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // --- GESTIÓN DE STOCK ---
    private function gestionarStock($variantId, $cantidad, $operacion = 'restar')
    {
        $variant = Variant::with(['stocks' => function ($q) {
            $q->orderBy('id', 'asc');
        }])->find($variantId);

        if (!$variant) return;

        $product = $variant->product;
        if ($product && $product->control_stock == 0) return;

        $pendiente = $cantidad;

        foreach ($variant->stocks as $stock) {
            if ($pendiente <= 0) break;

            if ($operacion === 'restar') {
                $disponible = $stock->stock_reserva;
                if ($disponible >= $pendiente) {
                    $stock->decrement('stock_reserva', $pendiente);
                    $pendiente = 0;
                } else {
                    if ($disponible > 0) {
                        $stock->decrement('stock_reserva', $disponible);
                        $pendiente -= $disponible;
                    }
                }
            } else {
                $stock->increment('stock_reserva', $pendiente);
                $pendiente = 0;
            }
        }
        if ($operacion === 'restar' && $pendiente > 0 && $variant->stocks->isNotEmpty()) {
            $variant->stocks->last()->decrement('stock_reserva', $pendiente);
        }
    }

    // --- SELECCIÓN DE PRODUCTOS ---
    public function agregarProducto(int $productoId)
    {
        $producto = OrdenService::obtenerProductoId($productoId);
        $this->productoSeleccionado = $producto;
        $this->esCortesia = false;
        $this->notaPedido = '';
        $this->selectedAttributes = [];
        $this->variantSeleccionadaId = null;
        $this->precioCalculado = $producto->price;
        $this->stockActualVariante = 0;
        $this->stockReservaVariante = 0;

        if ($producto->attributes->isEmpty()) {
            $varianteUnica = $producto->variants->first();
            if ($varianteUnica) {
                $this->variantSeleccionadaId = $varianteUnica->id;
                $this->stockActualVariante = $varianteUnica->stocks->sum('stock_real');
                $this->stockReservaVariante = $varianteUnica->stocks->sum('stock_reserva');
            }
        } else {
            foreach ($producto->attributes as $attr) {
                $rawValues = $attr->pivot->values ?? [];
                $values = is_string($rawValues) ? json_decode($rawValues, true) : $rawValues;
                if (is_array($values) && count($values) > 0) {
                    $primerValorId = $values[0]['id'];
                    $this->selectedAttributes[$attr->id] = $primerValorId;
                }
            }
            $this->buscarVarianteCoincidente();
        }
    }

    public function seleccionarAtributo($attributeId, $valueId)
    {
        $this->selectedAttributes[$attributeId] = $valueId;
        $this->buscarVarianteCoincidente();
    }

    public function buscarVarianteCoincidente()
    {
        $precioBase = $this->productoSeleccionado->price;
        $extrasAcumulados = 0;
        foreach ($this->selectedAttributes as $attrId => $valIdSeleccionado) {
            $atributo = $this->productoSeleccionado->attributes->find($attrId);
            if ($atributo) {
                $opciones = is_string($atributo->pivot->values) ? json_decode($atributo->pivot->values, true) : $atributo->pivot->values;
                $opcion = collect($opciones)->firstWhere('id', $valIdSeleccionado);
                if ($opcion) {
                    $extrasAcumulados += ($opcion['extra'] ?? 0);
                }
            }
        }

        $this->precioCalculado = $precioBase + $extrasAcumulados;

        if (!$this->productoSeleccionado || $this->productoSeleccionado->variants->isEmpty()) {
            return;
        }
        $matchVariant = null;
        foreach ($this->productoSeleccionado->variants as $variant) {
            $variantValueIds = $variant->values->pluck('id')->toArray();
            $seleccionados = array_values($this->selectedAttributes);
            $coincidencias = array_intersect($seleccionados, $variantValueIds);
            if (count($coincidencias) === count($this->productoSeleccionado->attributes)) {
                $matchVariant = $variant;
                break;
            }
        }

        if ($matchVariant) {
            $this->variantSeleccionadaId = $matchVariant->id;
            $this->stockActualVariante = $matchVariant->stocks->sum('stock_real');
            $this->stockReservaVariante = $matchVariant->stocks->sum('stock_reserva');
        } else {
            $this->variantSeleccionadaId = null;
            $this->stockActualVariante = 0;
            $this->stockReservaVariante = 0;
        }
    }

    public function confirmarAgregado()
    {
        if (!$this->productoSeleccionado) return;

        // ... (Tu lógica de detección de tipo y preparación de datos sigue igual) ...
        $esPromocion = $this->productoSeleccionado instanceof \App\Models\Promotion;

        // Validaciones...
        if (!$esPromocion && $this->productoSeleccionado->variants->count() > 0 && !$this->variantSeleccionadaId) {
            Notification::make()->title('Debes seleccionar una opción válida')->warning()->send();
            return;
        }

        // Preparar Datos...
        $idBase = $this->productoSeleccionado->id;
        $nombreItem = $this->productoSeleccionado->name;
        $precioFinal = floatval($this->precioCalculado);
        $esCortesia = $this->esCortesia;
        if ($esCortesia) $precioFinal = 0;

        $prodId = $esPromocion ? null : $idBase;
        $promoId = $esPromocion ? $idBase : null;
        $tipoEnum = $esPromocion ? TipoProducto::Promocion->value : TipoProducto::Producto->value;
        $varId = $this->variantSeleccionadaId;

        // Nombre con variante...
        if (!$esPromocion && $varId) {
            $variante = Variant::with('values')->find($varId);
            if ($variante && $this->productoSeleccionado->attributes->count() > 0) {
                $nombreVariante = $variante->values->pluck('name')->join(' / ');
                $nombreItem .= " ($nombreVariante)";
            }
        }

        $nuevaNota = trim($this->notaPedido);
        $indiceExistente = null;

        // Buscar coincidencia exacta (incluyendo precio)
        foreach ($this->carrito as $index => $item) {
            $coincide = false;
            if ($esPromocion) {
                if (
                    isset($item['type']) && $item['type'] === TipoProducto::Promocion->value &&
                    $item['promotion_id'] == $promoId && (float)$item['price'] == $precioFinal && $item['is_cortesia'] == $esCortesia
                ) {
                    $coincide = true;
                }
            } else {
                if ((!isset($item['type']) || $item['type'] === TipoProducto::Producto->value) &&
                    $item['product_id'] == $prodId && $item['variant_id'] == $varId && (float)$item['price'] == $precioFinal && $item['is_cortesia'] == $esCortesia
                ) {
                    $coincide = true;
                }
            }
            if ($coincide) {
                $indiceExistente = $index;
                break;
            }
        }

        // Validar Stock...
        if ($esPromocion) {
            if (!$this->puedeAgregarPromo($promoId, 1)) {
                Notification::make()->title('Stock insuficiente')->warning()->send();
                return;
            }
        } else {
            // ... tu validación de stock de producto ...
            if ($this->productoSeleccionado->control_stock == 1 && $this->productoSeleccionado->venta_sin_stock == 0) {
                $stockBase = $this->stockReservaVariante;
                $enCarrito = $indiceExistente !== null ? $this->carrito[$indiceExistente]['quantity'] : 0;
                if (($enCarrito + 1) > $stockBase) {
                    Notification::make()->title('No hay suficiente stock')->warning()->send();
                    return;
                }
            }
        }

        // --- LÓGICA DE AGREGADO / ACTUALIZACIÓN ---
        if ($indiceExistente !== null) {
            // CASO AMARILLO: Ya existía, solo aumentamos
            $this->carrito[$indiceExistente]['quantity']++;
            $this->carrito[$indiceExistente]['total'] = $this->carrito[$indiceExistente]['quantity'] * $precioFinal;

            if (!empty($nuevaNota)) {
                $notaActual = $this->carrito[$indiceExistente]['notes'];
                $this->carrito[$indiceExistente]['notes'] = empty($notaActual) ? $nuevaNota : $notaActual . ', ' . $nuevaNota;
            }

            // MARCAR COMO ACTUALIZADO (AMARILLO)
            $this->lastUpdatedItemId = $this->carrito[$indiceExistente]['item_id'];
        } else {
            // CASO VERDE: Es nuevo
            $itemId = md5(($esPromocion ? 'promo_' . $promoId : 'prod_' . $prodId) . $varId . $esCortesia . $precioFinal . time());

            $nuevoItem = [
                'item_id' => $itemId,
                'product_id' => $prodId,
                'promotion_id' => $promoId,
                'variant_id' => $varId,
                'type' => $tipoEnum,
                'name' => $nombreItem,
                'price' => $precioFinal,
                'quantity' => 1,
                'total' => $precioFinal,
                'is_cortesia' => $esCortesia,
                'notes' => $nuevaNota,
                'image' => $this->productoSeleccionado->image_path,
                'guardado' => false // <--- Esto define el color verde
            ];

            // Insertar al principio
            array_unshift($this->carrito, $nuevoItem);

            // Limpiamos rastro de actualización (el verde tiene prioridad por ser !guardado)
            $this->lastUpdatedItemId = null;
        }

        $this->hayCambios = true;
        $this->calcularTotales();
        $this->cerrarModal();
        Notification::make()->title($esPromocion ? 'Combo agregado' : 'Producto agregado')->success()->send();
    }

    public function cerrarModal()
    {
        $this->productoSeleccionado = null;
    }

    public function cerrarModalComanda()
    {
        $this->mostrarModalComanda = false;
    }

    // --- ACCIONES DE CARRITO ---
    public function incrementarCantidad($index)
    {
        $item = $this->carrito[$index];

        // 1. LÓGICA PARA PROMOCIONES
        if (isset($item['type']) && $item['type'] === TipoProducto::Promocion->value) {

            // Preguntamos: ¿Puedo agregar +1 unidad a esta promo?
            if (!$this->puedeAgregarPromo($item['promotion_id'], 1)) {
                Notification::make()
                    ->title('Stock insuficiente')
                    ->body("No hay suficientes ingredientes (o límite diario) para agregar otro combo.")
                    ->warning()
                    ->send();
                return;
            }
        }
        // 2. LÓGICA PARA PRODUCTOS (Tu código anterior)
        else {
            // ... tu lógica de producto simple ...
            $productoId = $item['product_id'];
            $variantId = $item['variant_id'];
            $producto = Product::find($productoId);

            if ($producto && $producto->control_stock == 1 && $producto->venta_sin_stock == 0) {
                $variante = Variant::with('stocks')->find($variantId);
                $stockMaximo = $variante ? $variante->stocks->sum('stock_reserva') : 0;

                // CORRECCIÓN RÁPIDA: También deberías verificar si este producto ya se gastó en promos
                // Pero si solo quieres mantener tu lógica actual:
                $cantidadEnCarrito = collect($this->carrito)->where('variant_id', $variantId)->sum('quantity');
                if (($cantidadEnCarrito + 1) > $stockMaximo) {
                    Notification::make()->title('Stock insuficiente')->warning()->send();
                    return;
                }
            }
        }

        // Ejecución
        $this->carrito[$index]['quantity']++;
        $this->carrito[$index]['total'] = $this->carrito[$index]['quantity'] * $this->carrito[$index]['price'];

        // MARCAR AMARILLO
        $this->lastUpdatedItemId = $this->carrito[$index]['item_id'];

        $this->hayCambios = true;
        $this->calcularTotales();
    }

    public function decrementarCantidad($index)
    {
        if ($this->carrito[$index]['quantity'] > 1) {
            $this->carrito[$index]['quantity']--;
            $this->carrito[$index]['total'] = $this->carrito[$index]['quantity'] * $this->carrito[$index]['price'];

            // MARCAR AMARILLO
            $this->lastUpdatedItemId = $this->carrito[$index]['item_id'];
        } else {
            $this->eliminarItem($index);
            return;
        }
        $this->hayCambios = true;
        $this->calcularTotales();
    }

    public function eliminarItem($index)
    {
        if (!isset($this->carrito[$index])) {
            return;
        }
        $item = $this->carrito[$index];
        if (isset($item['guardado']) && $item['guardado']) {
            $this->itemsEliminados[] = $item['item_id'];
        }
        unset($this->carrito[$index]);
        $this->carrito = array_values($this->carrito);
        $this->hayCambios = true;
        $this->calcularTotales();
    }

    public function calcularTotales()
    {
        $acumulado = 0;
        foreach ($this->carrito as $item) {
            $acumulado += $item['total'];
        }
        $this->total = $acumulado;
        $this->subtotal = $this->total / 1.18;
        $this->igv = $this->total - $this->subtotal;
    }

    // --- ACCIONES DE ANULACIÓN ---
    public function anularPedidoAction(): Action
    {
        return Action::make('anularPedido')
            ->label('Anular Pedido')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->iconButton()
            ->tooltip('Anular Pedido y Liberar Mesa')
            ->extraAttributes([
                'x-on:click' => 'mobileCartOpen = false',
            ])
            ->requiresConfirmation()
            ->modalHeading('¿Anular Pedido?')
            ->modalDescription('¿Seguro que deseas anular este pedido? Se devolverá el stock y la mesa quedará libre.')
            ->modalSubmitActionLabel('Sí, Anular')
            ->action(function () {
                $this->ejecutarAnulacion($this->pedido);
            });
    }

    public function ejecutarAnulacion($pedidoId)
    {
        if (!$pedidoId) return;

        try {
            DB::beginTransaction();

            $order = \App\Models\Order::with('details.product.production.printer')->findOrFail($pedidoId);
            $diffParaCocina = ['nuevos' => [], 'cancelados' => []];

            // Iteramos sobre CADA detalle
            foreach ($order->details as $detail) {

                // Si ya está cancelado, lo ignoramos para no devolver stock doble
                if ($detail->status === statusPedido::Cancelado) continue;

                // 1. Detectamos si es Promo o Producto Normal
                $esPromo = $detail->item_type === TipoProducto::Promocion->value || $detail->promotion_id;

                // 2. Devolvemos Stock Físico (Ingredientes)
                if (!$esPromo) {
                    // Producto Individual: Devolvemos su variante directamente
                    $this->gestionarStock($detail->variant_id, $detail->cantidad, 'sumar');
                } else {
                    // Promoción: Devolvemos los ingredientes que componen la promo
                    $this->gestionarStockPromocion($detail->promotion_id, $detail->cantidad, 'sumar');
                }

                // 3. Lógica de impresión (Ticket de Anulación para Cocina)
                $prod = $detail->product->production ?? null;
                // Si es promo, $detail->product es null, así que usamos un área general o la lógica que prefieras
                // (Opcional: podrías buscar el área de la promo si tiene, pero 'general' es seguro)
                $areaId = ($prod && $prod->status) ? $prod->id : 'general';
                $areaNombre = ($prod && $prod->status) ? $prod->name : 'GENERAL';

                $diffParaCocina['cancelados'][] = [
                    'cant'        => $detail->cantidad,
                    'nombre'      => $detail->product_name,
                    'nota'        => 'ANULACIÓN',
                    'area_id'     => $areaId,
                    'area_nombre' => $areaNombre
                ];

                // 4. CAMBIO CRÍTICO: Actualizamos el modelo individualmente
                // Esto dispara el evento 'updated' en OrderDetail.php y resta la 'venta_diaria' de la promo
                $detail->status = statusPedido::Cancelado;
                $detail->save();
            }

            // Actualizamos la cabecera de la orden
            $order->update(['status' => statusPedido::Cancelado]);

            // Liberamos Mesa
            if ($order->table_id) {
                Table::where('id', $order->table_id)->update([
                    'estado_mesa' => 'libre',
                    'order_id'    => null,
                    'asientos'    => 1
                ]);
            }

            // Cache de impresión...
            if (!empty($diffParaCocina['cancelados'])) {
                $jobId = 'print_anul_' . $pedidoId . '_' . time();
                Cache::put($jobId, $diffParaCocina, now()->addMinutes(5));
                session()->flash('print_job_id', $jobId);
                session()->flash('print_order_id', $pedidoId);
            }

            DB::commit();

            \Filament\Notifications\Notification::make()
                ->title('Pedido anulado correctamente')
                ->success()
                ->send();

            return redirect()->to("/app/point-of-sale");
        } catch (\Exception $e) {
            DB::rollBack();
            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getViewData(): array
    {
        $categorias = OrdenService::obtenerCategorias();
        $itemsMixtos = OrdenService::obtenerProductos($this->categoriaSeleccionada, $this->search);

        // =======================================================================
        // PASO 1: MAPA DE CONSUMO "VIRTUAL" (DELTA)
        // =======================================================================
        // Calculamos cuánto stock EXTRA se está consumiendo en el carrito ahora mismo
        // comparado con lo que ya está guardado en BD.

        $consumoVariantes = [];
        $consumoProductos = [];
        $conteoPromosTotal = []; // Para regla de límite (cuenta total absoluto)

        foreach ($this->carrito as $item) {
            $qtyActual = $item['quantity'];

            // Calculamos el Delta: ¿Cuánto estoy pidiendo AHORA vs lo que ya guardé?
            $qtyOriginal = 0;
            if (isset($item['guardado']) && $item['guardado']) {
                $qtyOriginal = $this->cantidadesOriginales[$item['item_id']] ?? 0;
            }
            $delta = $qtyActual - $qtyOriginal;

            // A. Si es PROMOCIÓN
            if (isset($item['type']) && $item['type'] === TipoProducto::Promocion->value) {
                $promoId = $item['promotion_id'];

                // Para Regla de Límite: Usamos el total absoluto en carrito
                if (!isset($conteoPromosTotal[$promoId])) $conteoPromosTotal[$promoId] = 0;
                $conteoPromosTotal[$promoId] += $qtyActual;

                // Para Stock Físico: Usamos el DELTA de los ingredientes
                $promoModel = \App\Models\Promotion::with('promotionproducts')->find($promoId);
                if ($promoModel) {
                    foreach ($promoModel->promotionproducts as $pp) {
                        $gasto = $pp->quantity * $delta; // Solo lo extra resta stock
                        if ($pp->variant_id) {
                            $consumoVariantes[$pp->variant_id] = ($consumoVariantes[$pp->variant_id] ?? 0) + $gasto;
                        } elseif ($pp->product_id) {
                            $consumoProductos[$pp->product_id] = ($consumoProductos[$pp->product_id] ?? 0) + $gasto;
                        }
                    }
                }
            }
            // B. Si es PRODUCTO
            else {
                if ($item['variant_id']) {
                    $consumoVariantes[$item['variant_id']] = ($consumoVariantes[$item['variant_id']] ?? 0) + $delta;
                } else {
                    $prodId = $item['product_id'];
                    $consumoProductos[$prodId] = ($consumoProductos[$prodId] ?? 0) + $delta;
                }
            }
        }

        // =======================================================================
        // PASO 2: APLICAR LÓGICA VISUAL
        // =======================================================================

        $itemsMixtos->transform(function ($item) use ($consumoVariantes, $consumoProductos, $conteoPromosTotal) {

            $tipo = $item->type instanceof \App\Enums\TipoProducto ? $item->type->value : $item->type;
            $item->type = $tipo;

            // --- PRODUCTO ---
            if ($tipo === TipoProducto::Producto->value) {
                $stockDb = 0;
                $impacto = 0;

                if ($item->variants->isNotEmpty()) {
                    foreach ($item->variants as $variant) {
                        $stockDb += $variant->stocks->sum('stock_reserva');
                        $impacto += ($consumoVariantes[$variant->id] ?? 0);
                    }
                } else {
                    $stockDb = $item->stock ?? 0;
                    $impacto = $consumoProductos[$item->id] ?? 0;
                }

                $visible = $stockDb - $impacto;
                $item->setAttribute('stock_visible', $visible);
                $item->setAttribute('esta_agotado', ($item->control_stock == 1 && $visible <= 0 && $item->venta_sin_stock == 0));
                $item->setAttribute('tiene_limite', $item->control_stock == 1);
            }

            // --- PROMOCIÓN ---
            elseif ($tipo === TipoProducto::Promocion->value) {

                // 1. REGLA (Límite Diario)
                // getStockDiarioRestante devuelve: (Límite - VentasBD).
                // Pero "VentasBD" ya incluye lo "Guardado" de este pedido.
                // Así que solo debemos restar lo "Nuevo" del carrito? No, es más seguro restar el Delta Total ajustado.
                // Simplificación: Calculamos el restante puro de BD y le restamos el aumento neto del carrito.

                $stockPorReglaBase = $item->getStockDiarioRestante(); // Ej: 8

                // Cálculo del Delta específico para esta promo (TotalCarrito - TotalGuardadoEnCarrito)
                $deltaPromoTotal = 0;
                // (Nota: Esto es una aproximación rápida, para exactitud total deberías pasar el array de deltas)
                // Asumimos que $conteoPromosTotal tiene todo. Restamos lo que asumimos que ya está en BD.
                // Mejor estrategia visual: Mostrar disponibilidad real basada en regla.
                // Si la regla dice 10, y tengo 2 en carrito. Quedan 8.
                // Pero si esos 2 ya estaban guardados, la regla BD ya bajó.

                // CORRECCIÓN CRÍTICA PARA VISUALIZACIÓN:
                // Usaremos solo el Stock Físico como limitante visual principal si no hay regla estricta.

                $limiteReglaFinal = null;
                if ($stockPorReglaBase !== null) {
                    // Calcular Delta de esta promo específica
                    $qtyTotalPromo = $conteoPromosTotal[$item->id] ?? 0;
                    // Recuperar cuánto de eso es "viejo" (ya descontado en BD)
                    $qtyViejo = 0;
                    foreach ($this->carrito as $c) {
                        if (isset($c['guardado']) && $c['guardado'] && $c['promotion_id'] == $item->id && $c['type'] == TipoProducto::Promocion->value) {
                            $qtyViejo += $this->cantidadesOriginales[$c['item_id']] ?? 0;
                        }
                    }
                    $deltaPuro = max(0, $qtyTotalPromo - $qtyViejo);
                    $limiteReglaFinal = max(0, $stockPorReglaBase - $deltaPuro);
                }

                // 2. FÍSICO (Ingredientes)
                $minimoPosibleFisico = 999999;

                if (!$item->promotionproducts->isEmpty()) {
                    foreach ($item->promotionproducts as $detalle) {
                        $producto = $detalle->product;
                        if (!$producto || $producto->control_stock == 0) continue;

                        $cantidadRequerida = $detalle->quantity;
                        if ($cantidadRequerida <= 0) continue;

                        $stockTotalBD = 0;
                        $impactoNeto = 0;

                        if ($detalle->variant_id && $detalle->variant) {
                            $stockTotalBD = $detalle->variant->stocks->sum('stock_reserva');
                            $impactoNeto = $consumoVariantes[$detalle->variant_id] ?? 0;
                        } elseif ($producto) {
                            $stockTotalBD = $producto->stock ?? 0;
                            $impactoNeto = $consumoProductos[$producto->id] ?? 0;
                        }

                        // (StockBD - ConsumoVirtual) / Receta
                        $remanente = max(0, $stockTotalBD - $impactoNeto);
                        $alcanzaPara = floor($remanente / $cantidadRequerida);

                        if ($alcanzaPara < $minimoPosibleFisico) {
                            $minimoPosibleFisico = $alcanzaPara;
                        }
                    }
                }

                if ($minimoPosibleFisico === 999999) $minimoPosibleFisico = 9999;

                // 3. SELECCIÓN FINAL
                if ($limiteReglaFinal !== null) {
                    $stockVisible = min($limiteReglaFinal, $minimoPosibleFisico);
                    $tieneLimite = true;
                } else {
                    $stockVisible = $minimoPosibleFisico;
                    $tieneLimite = ($minimoPosibleFisico < 9999);
                }

                $item->setAttribute('stock_visible', intval($stockVisible));
                $item->setAttribute('esta_agotado', $stockVisible <= 0);
                $item->setAttribute('tiene_limite', $tieneLimite);
            } else {
                $item->setAttribute('stock_visible', 9999);
                $item->setAttribute('esta_agotado', false);
                $item->setAttribute('tiene_limite', false);
            }

            return $item;
        });

        return [
            'tenant'     => Filament::getTenant(),
            'mesa'       => $this->mesa,
            'pedido'     => $this->pedido,
            'categorias' => $categorias,
            'productos'  => $itemsMixtos,
        ];
    }

    // === HELPER PRIVADO PARA VALIDAR STOCK DE PROMO ===
    // Este método simula agregar +1 a la promo y verifica si explota el stock de ingredientes
    private function puedeAgregarPromo($promoId, $cantidadAumentar = 1)
    {
        $promocion = \App\Models\Promotion::with('promotionproducts')->find($promoId);
        if (!$promocion) return false;

        // 1. Validar Regla
        $stockRegla = $promocion->getStockDiarioRestante(); // Restante en BD
        if ($stockRegla !== null) {
            // Calcular delta actual en carrito
            $deltaEnCarrito = 0;
            foreach ($this->carrito as $c) {
                if (isset($c['type']) && $c['type'] === TipoProducto::Promocion->value && $c['promotion_id'] == $promoId) {
                    $qtyOld = (isset($c['guardado']) && $c['guardado']) ? ($this->cantidadesOriginales[$c['item_id']] ?? 0) : 0;
                    $deltaEnCarrito += ($c['quantity'] - $qtyOld);
                }
            }
            // Si (lo que ya aumenté + lo que quiero aumentar) > lo que queda en BD -> Falso
            if (($deltaEnCarrito + $cantidadAumentar) > $stockRegla) return false;
        }

        // 2. Validar Ingredientes (Físico)
        // Calculamos el consumo TOTAL proyectado de cada ingrediente
        foreach ($promocion->promotionproducts as $pp) {
            $producto = $pp->product;
            if (!$producto || $producto->control_stock == 0) continue;

            $necesarioTotal = 0;
            $stockBD = 0;

            if ($pp->variant_id) {
                // Stock Total en BD
                $variant = \App\Models\Variant::with('stocks')->find($pp->variant_id);
                $stockBD = $variant->stocks->sum('stock_reserva');

                // Consumo de TODO el carrito actual (Deltas)
                foreach ($this->carrito as $c) {
                    $qtyItem = $c['quantity'];
                    $qtyOld = (isset($c['guardado']) && $c['guardado']) ? ($this->cantidadesOriginales[$c['item_id']] ?? 0) : 0;
                    $deltaItem = $qtyItem - $qtyOld;

                    // Si es producto suelto
                    if ((!isset($c['type']) || $c['type'] === TipoProducto::Producto->value) && $c['variant_id'] == $pp->variant_id) {
                        $necesarioTotal += $deltaItem;
                    }
                    // Si es promo (esta u otra)
                    elseif (isset($c['type']) && $c['type'] === TipoProducto::Promocion->value) {
                        $pModel = \App\Models\Promotion::with('promotionproducts')->find($c['promotion_id']);
                        foreach ($pModel->promotionproducts as $subPP) {
                            if ($subPP->variant_id == $pp->variant_id) {
                                $necesarioTotal += ($subPP->quantity * $deltaItem);
                            }
                        }
                    }
                }

                // Sumamos lo que QUEREMOS agregar ahora
                $necesarioTotal += ($pp->quantity * $cantidadAumentar);
            } elseif ($pp->product_id) {
                // ... Lógica análoga para producto simple ...
                $prod = \App\Models\Product::find($pp->product_id);
                $stockBD = $prod->stock ?? 0;

                foreach ($this->carrito as $c) {
                    $qtyItem = $c['quantity'];
                    $qtyOld = (isset($c['guardado']) && $c['guardado']) ? ($this->cantidadesOriginales[$c['item_id']] ?? 0) : 0;
                    $deltaItem = $qtyItem - $qtyOld;

                    if ((!isset($c['type']) || $c['type'] === TipoProducto::Producto->value) && $c['product_id'] == $pp->product_id) {
                        $necesarioTotal += $deltaItem;
                    } elseif (isset($c['type']) && $c['type'] === TipoProducto::Promocion->value) {
                        $pModel = \App\Models\Promotion::with('promotionproducts')->find($c['promotion_id']);
                        foreach ($pModel->promotionproducts as $subPP) {
                            if ($subPP->product_id == $pp->product_id) {
                                $necesarioTotal += ($subPP->quantity * $deltaItem);
                            }
                        }
                    }
                }
                $necesarioTotal += ($pp->quantity * $cantidadAumentar);
            }

            // Verificación final
            if ($necesarioTotal > $stockBD) return false;
        }

        return true;
    }

    public function agregarPromocion($promoId)
    {
        // 1. Buscamos la promoción con sus relaciones necesarias
        $promocion = \App\Models\Promotion::with('promotionproducts')->find($promoId);

        if (!$promocion) {
            Notification::make()->title('Promoción no encontrada')->danger()->send();
            return;
        }

        if (!$promocion->isAvailable()) {
            Notification::make()->title('Promoción no disponible')->warning()->send();
            return;
        }

        // 2. Preparamos el Modal (Reutilizamos la propiedad productoSeleccionado)
        $this->productoSeleccionado = $promocion;

        // Truco: Para evitar errores en el blade si intenta recorrer 'attributes',
        // le inyectamos una colección vacía si no existe.
        if (!$this->productoSeleccionado->relationLoaded('attributes')) {
            $this->productoSeleccionado->setRelation('attributes', collect([]));
        }

        // 3. Reseteamos variables del modal
        $this->variantSeleccionadaId = null; // Las promos no usan selector de variantes aquí
        $this->selectedAttributes = [];
        $this->esCortesia = false;
        $this->notaPedido = '';

        // 4. Preparamos el Precio (para que aparezca en el Input Editable)
        $this->precioCalculado = $promocion->price;

        // 5. Stocks visuales (Opcional: podrías calcular el límite aquí si quieres mostrarlo en el modal)
        $this->stockActualVariante = 0;
        $this->stockReservaVariante = 0;
    }

    // --- HELPER PARA GESTIONAR INGREDIENTES DE PROMOS ---
    private function gestionarStockPromocion($promoId, $cantidadPromo, $operacion = 'restar')
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
                $this->gestionarStock($detalle->variant_id, $totalIngrediente, $operacion);
            }
            // 2. Si es un producto simple (sin variante en la receta), buscamos su variante por defecto
            elseif ($detalle->product_id) {
                // Asumimos que el producto simple tiene una única variante principal
                $variant = $producto->variants->first();
                if ($variant) {
                    $this->gestionarStock($variant->id, $totalIngrediente, $operacion);
                }
            }
        }
    }

    public function actualizarCantidadManual($index, $nuevoValor)
    {
        $cantidadDeseada = intval($nuevoValor);
        if ($cantidadDeseada < 1) {
            $this->carrito[$index]['quantity'] = 1;
            $this->calcularTotales();
            return;
        }

        $item = $this->carrito[$index];
        $cantidadActual = $item['quantity'];

        // Calculamos la diferencia a sumar (puede ser negativa si bajamos cantidad, eso siempre es true)
        $diferencia = $cantidadDeseada - $cantidadActual;

        if ($diferencia > 0) {
            if (isset($item['type']) && $item['type'] === TipoProducto::Promocion->value) {
                if (!$this->puedeAgregarPromo($item['promotion_id'], $diferencia)) {
                    // Revertimos al valor anterior
                    // Opcional: Podrías calcular el máximo posible y poner eso, pero es complejo.
                    // Simplemente negamos el cambio por ahora.
                    Notification::make()
                        ->title('Stock insuficiente')
                        ->body("No hay stock para esa cantidad.")
                        ->warning()
                        ->send();

                    // Forzamos refresco UI (el input puede quedar visualmente mal si no se refresca el componente completo,
                    // pero al menos el modelo de livewire no cambia).
                    return;
                }
            } else {
                // ... tu lógica de producto simple ...
            }
        }

        $this->carrito[$index]['quantity'] = $cantidadDeseada;
        $this->carrito[$index]['total'] = $cantidadDeseada * $this->carrito[$index]['price'];
        $this->hayCambios = true;
        $this->calcularTotales();
    }

    // En tu componente OrdenMesa.php

    public function pagarOrden()
    {
        // 1. Si no hay pedido creado (es nuevo), lo creamos primero
        if (!$this->pedido) {
            $this->guardarOrdenEnBaseDeDatos(); // Tu lógica actual que crea el Order y OrderItems
        } else {
            // Si ya existe y hay cambios, actualizamos
            if ($this->hayCambios) {
                $this->actualizarOrden();
            }
        }

        // 2. AQUI ES EL CAMBIO: Redirigir a la nueva página de pago
        // Usamos el helper de ruta de Filament, pasando el ID del pedido
        return redirect()->to(PagarOrden::getUrl(['record' => $this->pedido]));
    }

    public static function getSlug(): string
    {
        return 'orden-mesa/{mesa?}/{pedido?}';
    }

    public function getHeading(): string
    {
        return '';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }
}
