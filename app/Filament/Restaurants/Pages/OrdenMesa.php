<?php

namespace App\Filament\Restaurants\Pages;

use App\Enums\statusPedido;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Table;
use App\Services\OrdenService;
use App\Models\Variant;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
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

    // === PROPIEDADES DE URL ===
    public int $mesa;
    public ?int $pedido = null;
    public $codigoOrden = null;
    public $personas = 1;
    // === FILTROS ===
    public $categoriaSeleccionada = null;
    public $search = '';

    // === CONTROL DE CAMBIOS ===
    public $cantidadesOriginales = [];
    public $notasOriginales = []; // <--- NUEVO: Para evitar reimprimir notas viejas

    public $stockActualVariante = 0;
    public $stockReservaVariante = 0;

    // === VARIABLES DEL MODAL (PRODUCTO SELECCIONADO) ===
    public ?Product $productoSeleccionado = null;
    public $variantSeleccionadaId = null;
    public $esCortesia = false;
    public $notaPedido = '';

    // Lógica de Selección
    public $selectedAttributes = [];
    public $precioCalculado = 0.00;

    // === NUEVA PROPIEDAD PARA LA COMANDA ===
    public $mostrarModalComanda = false;
    public ?Order $ordenGenerada = null;

    public $stocksProductos = [];
    public $cantidadesOriginalesPorVariante = [];
    public $tenantSlug;

    public function mount(int $mesa, ?int $pedido = null)
    {
        $this->mesa = $mesa;
        $this->pedido = $pedido;
        $this->tenantSlug = Filament::getTenant()->slug;

        if (session()->has('personas_iniciales')) {
            $this->personas = session('personas_iniciales');
        }

        if (session()->has('orden_creada_id')) {
            $idOrden = session('orden_creada_id');
            // IMPORTANTE: Cargar relaciones profundas para el modal
            $this->ordenGenerada = Order::with(['details.product.production.printer', 'table', 'user'])->find($idOrden);
            if ($this->ordenGenerada) {
                $this->mostrarModalComanda = true;
            }
        }

        if ($this->pedido) {
            $ordenExistente = Order::with(['details'])->find($this->pedido);
            if (!$ordenExistente || $ordenExistente->status === statusPedido::Cancelado) {
                return redirect()->to("/restaurants/{$this->tenantSlug}/point-of-sale");
            }
            if ($ordenExistente) {
                $this->codigoOrden = $ordenExistente->code;
                $this->subtotal = $ordenExistente->subtotal;
                $this->igv = $ordenExistente->igv;
                $this->total = $ordenExistente->total;
                
                $this->carrito = $ordenExistente->details->map(function ($detalle) {
                    // Guardamos estado original para comparar después
                    $this->cantidadesOriginales[$detalle->id] = $detalle->cantidad;
                    $this->notasOriginales[$detalle->id] = $detalle->notes; // <--- NUEVO

                    return [
                        'item_id'     => $detalle->id,
                        'product_id'  => $detalle->product_id,
                        'variant_id'  => $detalle->variant_id,
                        'name'        => $detalle->product_name,
                        'price'       => $detalle->price,
                        'quantity'    => $detalle->cantidad,
                        'total'       => $detalle->subTotal,
                        'is_cortesia' => (bool) $detalle->cortesia,
                        'notes'       => $detalle->notes,
                        'image'       => $detalle->product ? $detalle->product->image_path : null,
                        'guardado'    => true,
                    ];
                })->toArray();
            }
        }
        $this->hayCambios = false;
        $this->itemsEliminados = [];
    }

    // --- MÉTODO AUXILIAR PARA OBTENER EL ÁREA ---
    private function obtenerDatosArea($productId)
    {
        $producto = Product::with('production.printer')->find($productId);
        $prod = $producto?->production;
        
        // Validación flexible: Solo validamos que el área exista y esté activa
        // Ignoramos el estado de la impresora para evitar falsos "GENERAL"
        if ($prod && $prod->status) {
            return ['id' => $prod->id, 'name' => $prod->name];
        }

        return ['id' => 'general', 'name' => 'GENERAL'];
    }

    // --- PROCESAR ORDEN (CREAR NUEVA) ---
    public function procesarOrden()
    {
        if (empty($this->carrito)) {
            Notification::make()->title('El carrito está vacío')->warning()->send();
            return;
        }

        try {
            DB::beginTransaction();
            $restaurantId = Filament::getTenant()->id;
            $ultimoPedido = Order::where('restaurant_id', $restaurantId)->lockForUpdate()->orderBy('id', 'desc')->first();
            $numeroSiguiente = 1;
            if ($ultimoPedido) {
                $numeroSiguiente = intval($ultimoPedido->code) + 1;
            }
            $codigoFinal = str_pad($numeroSiguiente, 8, '0', STR_PAD_LEFT);
            
            // 1. Crear Orden
            $order = Order::create([
                'table_id'      => $this->mesa,
                'code'          => $codigoFinal,
                'status'        => statusPedido::Pendiente,
                'subtotal'      => $this->subtotal,
                'igv'           => $this->igv,
                'total'         => $this->total,
                'fecha_pedido'  => now(),
                'user_id'       => Auth::id(),
            ]);

            // Array para impresión
            $diffParaCocina = [
                'nuevos' => [],
                'cancelados' => []
            ];

            // 2. Detalles y Stock
            foreach ($this->carrito as $item) {
                OrderDetail::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['product_id'],
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
                $this->gestionarStock($item['variant_id'], $item['quantity'], 'restar');

                // --- CLASIFICACIÓN PARA EL TICKET ---
                $areaData = $this->obtenerDatosArea($item['product_id']);
                
                $diffParaCocina['nuevos'][] = [
                    'cant' => $item['quantity'],
                    'nombre' => $item['name'],
                    'nota' => $item['notes'],
                    'area_id' => $areaData['id'],
                    'area_nombre' => $areaData['name']
                ];
            }

            // 3. Actualizar Mesa
            $mesaModel = Table::where('id', $this->mesa);
            if ($mesaModel) {
                $mesaModel->update(['estado_mesa' => 'ocupada', 'order_id' => $order->id, 'asientos' => $this->personas]);
            }

            // 4. Guardar en Cache para Imprimir (con áreas)
            if (!empty($diffParaCocina['nuevos'])) {
                $jobId = 'print_new_' . $order->id . '_' . time();
                Cache::put($jobId, $diffParaCocina, now()->addMinutes(5));
                session()->flash('print_job_id', $jobId);
            }

            DB::commit();

            $this->carrito = [];
            return redirect()
                ->to("/restaurants/{$this->tenantSlug}/orden-mesa/{$this->mesa}/{$order->id}")
                ->with('orden_creada_id', $order->id); // Flash ID para reabrir modal si es necesario

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error al procesar orden')->body($e->getMessage())->danger()->send();
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
                if (!isset($item['guardado']) || !$item['guardado']) {
                    OrderDetail::create([
                        'order_id'      => $order->id,
                        'product_id'    => $item['product_id'],
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
                    $this->gestionarStock($item['variant_id'], $item['quantity'], 'restar');
                } else {
                    $detalle = OrderDetail::find($item['item_id']);
                    if ($detalle) {
                        // Actualizamos la nota en BD
                        $detalle->notes = $item['notes'];
                        
                        $cantidadAnterior = $detalle->cantidad;
                        $cantidadNueva = $item['quantity'];

                        if ($cantidadNueva != $cantidadAnterior) {
                            $detalle->update([
                                'cantidad' => $cantidadNueva,
                                'subTotal' => $item['total'],
                                'notes' => $item['notes']
                            ]);

                            if ($cantidadNueva > $cantidadAnterior) {
                                $diff = $cantidadNueva - $cantidadAnterior;
                                $this->gestionarStock($item['variant_id'], $diff, 'restar');
                            } else {
                                $diff = $cantidadAnterior - $cantidadNueva;
                                $this->gestionarStock($item['variant_id'], $diff, 'sumar');
                            }
                        } else {
                            // Solo guardar si cambió la nota sin cambiar cantidad
                            $detalle->save();
                        }
                    }
                }
            }

            if (!empty($this->itemsEliminados)) {
                $itemsABorrar = OrderDetail::whereIn('id', $this->itemsEliminados)->get();
                foreach ($itemsABorrar as $borrado) {
                    $this->gestionarStock($borrado->variant_id, $borrado->cantidad, 'sumar');
                }
                OrderDetail::whereIn('id', $this->itemsEliminados)->delete();
            }

            DB::commit();

            $this->hayCambios = false;
            $this->itemsEliminados = [];

            // Refrescamos y recargamos la página (mount se ejecutará de nuevo y actualizará notasOriginales)
            $this->ordenGenerada = $order->refresh()->load(['details.product.production.printer', 'table', 'user']);

            if (session()->has('print_job_id')) {
                $this->mostrarModalComanda = true;
            }
            Notification::make()->title('Orden actualizada')->success()->send();
            
            // Importante volver a llamar al mount para resetear originales
            $this->mount($this->mesa, $this->pedido);

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
        if ($this->productoSeleccionado->variants->count() > 0 && !$this->variantSeleccionadaId) {
            Notification::make()->title('Debes seleccionar una opción válida')->warning()->send();
            return;
        }

        $prodId = $this->productoSeleccionado->id;
        $varId = $this->variantSeleccionadaId;
        $esCortesia = $this->esCortesia;
        $nuevaNota = trim($this->notaPedido);
        $indiceExistente = null;

        foreach ($this->carrito as $index => $item) {
            if (
                $item['product_id'] == $prodId &&
                $item['variant_id'] == $varId &&
                $item['is_cortesia'] == $esCortesia
            ) {
                $indiceExistente = $index;
                break;
            }
        }

        if ($indiceExistente !== null) {
            if ($this->productoSeleccionado->control_stock == 1 && $this->productoSeleccionado->venta_sin_stock == 0) {
                $stockRestante = $this->stockReservaVariante;
                $cantidadNueva = $this->carrito[$indiceExistente]['quantity'] + 1;
                if ($cantidadNueva > $stockRestante) {
                    Notification::make()->title('No hay suficiente stock para agregar más')->warning()->send();
                    return;
                }
            }

            $this->carrito[$indiceExistente]['quantity']++;
            $this->carrito[$indiceExistente]['total'] = $this->carrito[$indiceExistente]['quantity'] * $this->carrito[$indiceExistente]['price'];

            if (!empty($nuevaNota)) {
                $notaActual = $this->carrito[$indiceExistente]['notes'];
                if (!empty($notaActual)) {
                    $this->carrito[$indiceExistente]['notes'] = $notaActual . ', ' . $nuevaNota;
                } else {
                    $this->carrito[$indiceExistente]['notes'] = $nuevaNota;
                }
            }
        } else {
            $variante = Variant::with('values')->find($varId);
            $nombreItem = $this->productoSeleccionado->name;
            if ($variante && $this->productoSeleccionado->attributes->count() > 0) {
                $nombreVariante = $variante->values->pluck('name')->join(' / ');
                $nombreItem .= " ($nombreVariante)";
            }
            $precioUnitario = $esCortesia ? 0 : $this->precioCalculado;
            $itemId = md5($prodId . $varId . $esCortesia . time());
            $this->carrito[] = [
                'item_id'     => $itemId,
                'product_id'  => $prodId,
                'variant_id'  => $varId,
                'name'        => $nombreItem,
                'price'       => $precioUnitario,
                'quantity'    => 1,
                'total'       => $precioUnitario,
                'is_cortesia' => $esCortesia,
                'notes'       => $nuevaNota,
                'image'       => $this->productoSeleccionado->image_path,
                'guardado'    => false
            ];
        }
        $this->hayCambios = true;
        $this->calcularTotales();
        $this->cerrarModal();
        Notification::make()->title('Producto agregado')->success()->send();
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
        $productoId = $item['product_id'];
        $variantId = $item['variant_id'];

        $producto = Product::find($productoId);

        if ($producto->control_stock == 1 && $producto->venta_sin_stock == 0) {
            $variante = Variant::with('stocks')->find($variantId);
            $stockMaximo = $variante ? $variante->stocks->sum('stock_reserva') : 0;
            $cantidadEnCarrito = collect($this->carrito)
                ->where('variant_id', $variantId)
                ->sum('quantity');

            if (($cantidadEnCarrito + 1) > $stockMaximo) {
                Notification::make()->title('Stock insuficiente')->body("Solo quedan {$stockMaximo} unidades disponibles.")->warning()->send();
                return;
            }
        }

        $this->carrito[$index]['quantity']++;
        $this->carrito[$index]['total'] = $this->carrito[$index]['quantity'] * $this->carrito[$index]['price'];
        $this->hayCambios = true;
        $this->calcularTotales();
    }

    public function decrementarCantidad($index)
    {
        if ($this->carrito[$index]['quantity'] > 1) {
            $this->carrito[$index]['quantity']--;
            $this->carrito[$index]['total'] = $this->carrito[$index]['quantity'] * $this->carrito[$index]['price'];
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

            foreach ($order->details as $detail) {
                if ($detail->status !== statusPedido::Cancelado) {
                    $this->gestionarStock($detail->variant_id, $detail->cantidad, 'sumar');

                    // Lógica de Área
                    $prod = $detail->product->production ?? null;
                    $areaId = ($prod && $prod->status) ? $prod->id : 'general';
                    $areaNombre = ($prod && $prod->status) ? $prod->name : 'GENERAL';

                    $diffParaCocina['cancelados'][] = [
                        'cant'   => $detail->cantidad,
                        'nombre' => $detail->product_name,
                        'nota'   => 'ANULACIÓN',
                        'area_id' => $areaId,
                        'area_nombre' => $areaNombre
                    ];
                }
            }

            $order->details()->where('status', '!=', statusPedido::Cancelado)->update(['status' => statusPedido::Cancelado]);
            $order->update(['status' => statusPedido::Cancelado]);

            if ($order->table_id) {
                Table::where('id', $order->table_id)->update([
                    'estado_mesa' => 'libre',
                    'order_id'    => null,
                    'asientos'    => 1
                ]);
            }

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

            return redirect()->to("/restaurants/{$this->tenantSlug}/point-of-sale");
        } catch (\Exception $e) {
            DB::rollBack();
            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // --- CONFIGURACIÓN DE PÁGINA ---
    public function getViewData(): array
    {
        $categorias = OrdenService::obtenerCategorias();
        $productos = OrdenService::obtenerProductos(
            $this->categoriaSeleccionada,
            $this->search
        );
        $consumoPendientePorProducto = [];
        foreach ($this->carrito as $item) {
            $prodId = $item['product_id'];
            $cantidadActual = $item['quantity'];
            $cantidadOriginal = 0;
            if (isset($item['guardado']) && $item['guardado']) {
                $cantidadOriginal = $this->cantidadesOriginales[$item['item_id']] ?? 0;
            }
            $consumoAdicional = max(0, $cantidadActual - $cantidadOriginal);
            if (!isset($consumoPendientePorProducto[$prodId])) {
                $consumoPendientePorProducto[$prodId] = 0;
            }
            $consumoPendientePorProducto[$prodId] += $consumoAdicional;
        }
        $productos->transform(function ($product) use ($consumoPendientePorProducto) {
            $stockEnBaseDatos = $product->variants->flatMap->stocks->sum('stock_reserva');
            $consumoExtra = $consumoPendientePorProducto[$product->id] ?? 0;
            $stockVisible = $stockEnBaseDatos - $consumoExtra;
            $product->setAttribute('stock_visible', max(0, $stockVisible));
            $product->setAttribute(
                'esta_agotado',
                ($product->control_stock == 1 && $stockVisible <= 0 && $product->venta_sin_stock == 0)
            );
            return $product;
        });

        return [
            'tenant'     => Filament::getTenant(),
            'mesa'       => $this->mesa,
            'pedido'     => $this->pedido,
            'categorias' => $categorias,
            'productos'  => $productos,
        ];
    }

    public function actualizarCantidadManual($index, $nuevoValor)
    {
        $cantidad = intval($nuevoValor);
        if ($cantidad < 1) {
            $this->carrito[$index]['quantity'] = 1;
            $this->calcularTotales();
            return;
        }
        $item = $this->carrito[$index];
        $productoId = $item['product_id'];
        $variantId = $item['variant_id'];
        $producto = Product::find($productoId);
        if ($producto && $producto->control_stock == 1 && $producto->venta_sin_stock == 0) {
            $variante = Variant::with('stocks')->find($variantId);
            $stockMaximo = $variante ? $variante->stocks->sum('stock_reserva') : 0;
            $cantidadOtrosItems = collect($this->carrito)
                ->where('variant_id', $variantId)
                ->except($index)
                ->sum('quantity');
            $totalRequerido = $cantidadOtrosItems + $cantidad;
            if ($totalRequerido > $stockMaximo) {
                $maximoPermitido = max(1, $stockMaximo - $cantidadOtrosItems);
                $this->carrito[$index]['quantity'] = $maximoPermitido;
                $this->carrito[$index]['total'] = $maximoPermitido * $this->carrito[$index]['price'];
                $this->calcularTotales();
                Notification::make()
                    ->title('Stock insuficiente')
                    ->body("Stock máximo disponible: {$stockMaximo}. Se ajustó la cantidad automáticamente.")
                    ->warning()
                    ->send();
                return;
            }
        }
        $this->carrito[$index]['quantity'] = $cantidad;
        $this->carrito[$index]['total'] = $cantidad * $this->carrito[$index]['price'];
        $this->hayCambios = true;
        $this->calcularTotales();
    }

    public static function getSlug(): string
    {
        return 'orden-mesa/{mesa}/{pedido?}';
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