<?php

namespace App\Filament\Restaurants\Pages;

use App\Enums\StatusPedido;
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

    public $mostrarModalPrecuenta = false;
    public $mesa = null;
    public ?int $pedido = null;

    public function mount(Request $request, $mesa = null, ?int $pedido = null)
    {
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

        $this->cargarDatosPedido();
    }

    public function cargarDatosPedido()
    {
        if (session()->has('personas_iniciales')) {
            $this->personas = session('personas_iniciales');
        }

        if (session()->has('orden_creada_id')) {
            $idOrden = session('orden_creada_id');
            $this->ordenGenerada = Order::with(['details.product.production.printer', 'table', 'user'])->find($idOrden);
            if ($this->ordenGenerada) {
                $config = Filament::getTenant()->cached_config;
                if ($config->mostrar_modal_impresion_comanda) {
                    $this->mostrarModalComanda = true;
                }
            }
        }

        if ($this->pedido) {
            $ordenExistente = Order::with(['details' => function ($query) {
                $query->where('status', '!=', StatusPedido::Cancelado);
            }, 'details.product'])->find($this->pedido);

            if (!$ordenExistente || $ordenExistente->status === StatusPedido::Cancelado) {
                return redirect()->to("/app/point-of-sale");
            }

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

    // --- PROCESAR ORDEN (CREAR NUEVA) 🟢 AHORA USA EL SERVICE ---
    public function procesarOrden()
    {
        if (empty($this->carrito)) {
            Notification::make()->title('El carrito está vacío')->warning()->send();
            return;
        }

        try {
            // 1. Preparar datos para el Service
            $datosOrden = [
                'restaurant_id'   => Filament::getTenant()->id,
                'canal'           => $this->canal,
                'mesa_id'         => $this->mesa,
                'cliente_id'      => $this->cliente_id,
                'nombre_cliente'  => $this->nombre_cliente,
                'nombre_repartidor' => $this->nombre_repartidor,
                'delivery_id'     => $this->delivery_id,
                'direccion'       => $this->direccion,
                'telefono'        => $this->telefono,
                'personas'        => $this->personas,
                'subtotal'        => $this->subtotal,
                'igv'             => $this->igv,
                'total'           => $this->total,
                'web'             => false, // Creado desde el PDV interno, no es web
                'notas'           => null,
            ];

            // 2. Llamar al Service
            $resultado = OrdenService::crearPedido($datosOrden, $this->carrito, Auth::id());

            $order = $resultado['order'];
            $diffParaCocina = $resultado['diffParaCocina'];

            // 3. Imprimir Comanda
            $config = Filament::getTenant()->cached_config; // 🟢 Leer config

            if (!empty($diffParaCocina['nuevos'])) {
                // 🟢 Solo genera caché si imprime directo o usa modal
                if ($config->mostrar_modal_impresion_comanda) {
                    $jobId = 'print_' . $order->id . '_' . time();
                    Cache::put($jobId, $diffParaCocina, now()->addMinutes(5));
                    session()->flash('print_job_id', $jobId);
                }
            }

            // 4. Redirección
            $paramMesa = $this->mesa ?? 'nuevo';
            return redirect()
                ->to("/app/orden-mesa/{$paramMesa}/{$order->id}")
                ->with('orden_creada_id', $order->id);
        } catch (\Exception $e) {
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
                // USAMOS EL SERVICE
                $areaData = OrdenService::obtenerDatosArea($item['product_id'], $item['promotion_id'] ?? null);

                if (!isset($item['guardado']) || !$item['guardado']) {
                    $diffParaCocina['nuevos'][] = [
                        'cant' => $item['quantity'],
                        'nombre' => $item['name'],
                        'nota' => $item['notes'],
                        'area_id' => $areaData['id'],
                        'area_nombre' => $areaData['name']
                    ];
                } else {
                    $idDetalle = $item['item_id'];
                    $cantidadOriginal = $this->cantidadesOriginales[$idDetalle] ?? 0;
                    $cantidadActual = $item['quantity'];

                    $notaOriginal = $this->notasOriginales[$idDetalle] ?? '';
                    $notaActual = $item['notes'];
                    $notaParaImprimir = ($notaActual !== $notaOriginal) ? $notaActual : '';

                    if ($cantidadActual > $cantidadOriginal) {
                        $diferencia = $cantidadActual - $cantidadOriginal;
                        $diffParaCocina['nuevos'][] = [
                            'cant' => $diferencia,
                            'nombre' => $item['name'],
                            'nota' => $notaParaImprimir,
                            'area_id' => $areaData['id'],
                            'area_nombre' => $areaData['name']
                        ];
                    } elseif ($cantidadActual < $cantidadOriginal) {
                        $diferencia = $cantidadOriginal - $cantidadActual;
                        $diffParaCocina['cancelados'][] = [
                            'cant' => $diferencia,
                            'nombre' => $item['name'],
                            'nota' => 'ANULACIÓN PARCIAL: ' . Auth::user()->name,
                            'area_id' => $areaData['id'],
                            'area_nombre' => $areaData['name']
                        ];
                    }
                }
            }

            DB::beginTransaction();

            $order = Order::find($this->pedido);
            $order->update([
                'subtotal' => $this->subtotal,
                'igv'      => $this->igv,
                'total'    => $this->total,
                'user_actualiza_id' => Auth::id(),
            ]);

            foreach ($this->carrito as $item) {
                $esPromocion = isset($item['type']) && $item['type'] === TipoProducto::Promocion->value;

                if (!isset($item['guardado']) || !$item['guardado']) {
                    OrderDetail::create([
                        'restaurant_id'      => Filament::getTenant()->id,
                        'order_id'           => $order->id,
                        'product_id'         => $esPromocion ? null : $item['product_id'],
                        'promotion_id'       => $esPromocion ? $item['promotion_id'] : null,
                        'variant_id'         => $item['variant_id'],
                        'product_name'       => $item['name'],
                        'item_type'          => $esPromocion ? TipoProducto::Promocion->value : TipoProducto::Producto->value,
                        'price'              => $item['price'],
                        'cantidad'           => $item['quantity'],
                        'subTotal'           => $item['total'],
                        'cortesia'           => $item['is_cortesia'] ? 1 : 0,
                        'status'             => StatusPedido::Pendiente,
                        'notes'              => $item['notes'],
                        'fecha_envio_cocina' => now(),
                        'user_id'            => Auth::id(),
                        'user_actualiza_id'  => null,
                    ]);

                    if (!$esPromocion) {
                        OrdenService::gestionarStock($item['variant_id'], $item['quantity'], 'restar');
                    } else {
                        OrdenService::gestionarStockPromocion($item['promotion_id'], $item['quantity'], 'restar');
                    }
                } else {
                    $detalle = OrderDetail::find($item['item_id']);
                    if ($detalle) {
                        $cantidadAnterior = $this->cantidadesOriginales[$item['item_id']] ?? $detalle->cantidad;
                        $cantidadNueva = $item['quantity'];

                        if ($cantidadNueva != $cantidadAnterior) {
                            if ($cantidadNueva < $cantidadAnterior) {
                                $cantidadAnulada = $cantidadAnterior - $cantidadNueva;
                                $detalleAnulado = $detalle->replicate();
                                $detalleAnulado->cantidad = $cantidadAnulada;
                                $detalleAnulado->subTotal = $cantidadAnulada * $detalle->price;
                                $detalleAnulado->status = StatusPedido::Cancelado;
                                $detalleAnulado->user_actualiza_id = Auth::id();
                                $detalleAnulado->notes = "Anulación parcial de " . Auth::user()->name;
                                $detalleAnulado->save();

                                $detalle->update([
                                    'cantidad' => $cantidadNueva,
                                    'subTotal' => $item['total'],
                                    'notes'    => $item['notes'],
                                    'user_actualiza_id' => Auth::id(),
                                ]);

                                if (!$esPromocion) {
                                    OrdenService::gestionarStock($item['variant_id'], $cantidadAnulada, 'sumar');
                                } else {
                                    OrdenService::gestionarStockPromocion($item['promotion_id'], $cantidadAnulada, 'sumar');
                                }
                            } else {
                                $diff = $cantidadNueva - $cantidadAnterior;
                                $detalle->update([
                                    'cantidad' => $cantidadNueva,
                                    'subTotal' => $item['total'],
                                    'notes'    => $item['notes'],
                                    'user_actualiza_id' => Auth::id(),
                                ]);

                                if (!$esPromocion) {
                                    OrdenService::gestionarStock($item['variant_id'], $diff, 'restar');
                                } else {
                                    OrdenService::gestionarStockPromocion($item['promotion_id'], $diff, 'restar');
                                }
                            }
                        } else {
                            $detalle->update([
                                'notes' => $item['notes'],
                                'user_actualiza_id' => Auth::id()
                            ]);
                        }
                    }
                }
            }

            if (!empty($this->itemsEliminados)) {
                $detallesABorrar = OrderDetail::whereIn('id', $this->itemsEliminados)->get();
                foreach ($detallesABorrar as $borrado) {

                    // 🟢 BUG CORREGIDO: Evitamos reprocesar si ya estaba cancelado
                    if ($borrado->status === StatusPedido::Cancelado) continue;

                    $eraPromo = $borrado->item_type === TipoProducto::Promocion->value || $borrado->promotion_id;
                    $areaData = OrdenService::obtenerDatosArea($borrado->product_id, $borrado->promotion_id ?? null);

                    // 🟢 BUG CORREGIDO: Agregamos el ítem completamente borrado a la comanda de anulación
                    $diffParaCocina['cancelados'][] = [
                        'cant'        => $borrado->cantidad,
                        'nombre'      => $borrado->product_name,
                        'nota'        => 'ANULACIÓN TOTAL',
                        'area_id'     => $areaData['id'],
                        'area_nombre' => $areaData['name']
                    ];

                    if (!$eraPromo) {
                        OrdenService::gestionarStock($borrado->variant_id, $borrado->cantidad, 'sumar');
                    } else {
                        OrdenService::gestionarStockPromocion($borrado->promotion_id, $borrado->cantidad, 'sumar');
                    }

                    $borrado->update([
                        'status' => StatusPedido::Cancelado,
                        'user_actualiza_id' => Auth::id(),
                        'notes' => $borrado->notes . " (Eliminado por " . Auth::user()->name . ")",
                    ]);
                }
            }

            DB::commit();
            $config = Filament::getTenant()->cached_config;
            if (!empty($diffParaCocina['nuevos']) || !empty($diffParaCocina['cancelados'])) {
                if ($config->mostrar_modal_impresion_comanda) {
                    $jobId = 'print_' . $this->pedido . '_' . time();
                    Cache::put($jobId, $diffParaCocina, now()->addMinutes(5));
                    session()->flash('print_job_id', $jobId);
                }
            }

            $this->ordenGenerada = $order->refresh()->load(['details.product.production.printer', 'table', 'user']);
            if ($config->mostrar_modal_impresion_comanda) {
                $this->mostrarModalComanda = true;
            }

            Notification::make()->title('Orden actualizada')->success()->send();
            $this->cargarDatosPedido(); // Recargar el estado limpio

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
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

    // --- EJECUTAR ANULACIÓN DE TODA LA MESA ---
    public function ejecutarAnulacion($pedidoId)
    {
        if (!$pedidoId) return;

        try {
            DB::beginTransaction();

            $order = \App\Models\Order::with([
                // 🟢 Agregamos un Closure (función anónima) para filtrar los detalles
                'details' => function ($query) {
                    $query->where('status', '!=', \App\Enums\StatusPedido::Cancelado);
                },
                // Y mantenemos la carga profunda anidada para los detalles que SÍ pasen el filtro
                'details.product.production.printer'
            ])->findOrFail($pedidoId);
            $diffParaCocina = ['nuevos' => [], 'cancelados' => []];

            foreach ($order->details as $detail) {
                // 🟢 BUG CORREGIDO: Si el ítem YA estaba cancelado antes, lo ignoramos por completo
                if ($detail->status === StatusPedido::Cancelado) {
                    continue;
                }

                $esPromo = $detail->item_type === TipoProducto::Promocion->value || $detail->promotion_id;
                $areaData = OrdenService::obtenerDatosArea($detail->product_id, $detail->promotion_id ?? null);

                $diffParaCocina['cancelados'][] = [
                    'cant'        => $detail->cantidad,
                    'nombre'      => $detail->product_name,
                    'nota'        => 'MESA ANULADA COMPLETAMENTE',
                    'area_id'     => $areaData['id'],
                    'area_nombre' => $areaData['name']
                ];

                // Devolvemos el stock de los productos que sí estaban activos
                if (!$esPromo) {
                    OrdenService::gestionarStock($detail->variant_id, $detail->cantidad, 'sumar');
                } else {
                    OrdenService::gestionarStockPromocion($detail->promotion_id, $detail->cantidad, 'sumar');
                }

                $detail->status = StatusPedido::Cancelado;
                $detail->user_actualiza_id = Auth::id();
                $detail->save();
            }

            $order->user_actualiza_id = Auth::id();
            $order->update(['status' => StatusPedido::Cancelado]);

            if ($order->table_id) {
                Table::where('id', $order->table_id)->update([
                    'estado_mesa' => 'libre',
                    'order_id'    => null,
                    'asientos'    => 1
                ]);
            }
            $config = Filament::getTenant()->cached_config;
            if (!empty($diffParaCocina['cancelados'])) {
                if ($config->mostrar_modal_impresion_comanda) {
                    $jobId = 'print_anul_' . $pedidoId . '_' . time();
                    Cache::put($jobId, $diffParaCocina, now()->addMinutes(5));
                    session()->flash('print_job_id', $jobId);
                    session()->flash('print_order_id', $pedidoId);
                }
            }

            DB::commit();

            Notification::make()->title('Pedido anulado correctamente')->success()->send();
            return redirect()->to("/app/point-of-sale");
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function getViewData(): array
    {
        $categorias = OrdenService::obtenerCategorias();
        $itemsMixtos = OrdenService::obtenerProductos($this->categoriaSeleccionada, $this->search);

        $consumoVariantes = [];
        $consumoProductos = [];
        $conteoPromosTotal = [];

        foreach ($this->carrito as $item) {
            $qtyActual = $item['quantity'];
            $qtyOriginal = 0;
            if (isset($item['guardado']) && $item['guardado']) {
                $qtyOriginal = $this->cantidadesOriginales[$item['item_id']] ?? 0;
            }
            $delta = $qtyActual - $qtyOriginal;

            if (isset($item['type']) && $item['type'] === TipoProducto::Promocion->value) {
                $promoId = $item['promotion_id'];
                if (!isset($conteoPromosTotal[$promoId])) $conteoPromosTotal[$promoId] = 0;
                $conteoPromosTotal[$promoId] += $qtyActual;

                $promoModel = \App\Models\Promotion::with('promotionproducts')->find($promoId);
                if ($promoModel) {
                    foreach ($promoModel->promotionproducts as $pp) {
                        $gasto = $pp->quantity * $delta;
                        if ($pp->variant_id) {
                            $consumoVariantes[$pp->variant_id] = ($consumoVariantes[$pp->variant_id] ?? 0) + $gasto;
                        } elseif ($pp->product_id) {
                            $consumoProductos[$pp->product_id] = ($consumoProductos[$pp->product_id] ?? 0) + $gasto;
                        }
                    }
                }
            } else {
                if ($item['variant_id']) {
                    $consumoVariantes[$item['variant_id']] = ($consumoVariantes[$item['variant_id']] ?? 0) + $delta;
                } else {
                    $prodId = $item['product_id'];
                    $consumoProductos[$prodId] = ($consumoProductos[$prodId] ?? 0) + $delta;
                }
            }
        }

        $itemsMixtos->transform(function ($item) use ($consumoVariantes, $consumoProductos, $conteoPromosTotal) {
            $tipo = $item->type instanceof \App\Enums\TipoProducto ? $item->type->value : $item->type;
            $item->type = $tipo;

            if ($tipo === TipoProducto::Producto->value) {
                $stockDb = 0;
                $impacto = 0;

                if ($item->variants->isNotEmpty()) {
                    foreach ($item->variants as $variant) {
                        $stockDb += ($variant->stock->stock_reserva ?? 0);
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
            } elseif ($tipo === TipoProducto::Promocion->value) {
                $stockPorReglaBase = $item->getStockDiarioRestante();
                $deltaPromoTotal = 0;
                $limiteReglaFinal = null;

                if ($stockPorReglaBase !== null) {
                    $qtyTotalPromo = $conteoPromosTotal[$item->id] ?? 0;
                    $qtyViejo = 0;
                    foreach ($this->carrito as $c) {
                        if (isset($c['guardado']) && $c['guardado'] && $c['promotion_id'] == $item->id && $c['type'] == TipoProducto::Promocion->value) {
                            $qtyViejo += $this->cantidadesOriginales[$c['item_id']] ?? 0;
                        }
                    }
                    $deltaPuro = max(0, $qtyTotalPromo - $qtyViejo);
                    $limiteReglaFinal = max(0, $stockPorReglaBase - $deltaPuro);
                }

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
                            $stockObj = $detalle->variant->stock;
                            if ($stockObj) {
                                $stockTotalBD = $stockObj->stock_reserva;
                            } else {
                                $stockTotalBD = 0;
                            }
                            $impactoNeto = $consumoVariantes[$detalle->variant_id] ?? 0;
                        } elseif ($producto) {
                            $stockTotalBD = $producto->stock ?? 0;
                            $impactoNeto = $consumoProductos[$producto->id] ?? 0;
                        }

                        $remanente = max(0, $stockTotalBD - $impactoNeto);
                        $alcanzaPara = floor($remanente / $cantidadRequerida);

                        if ($alcanzaPara < $minimoPosibleFisico) {
                            $minimoPosibleFisico = $alcanzaPara;
                        }
                    }
                }

                if ($minimoPosibleFisico === 999999) $minimoPosibleFisico = 9999;

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

    private function puedeAgregarPromo($promoId, $cantidadAumentar = 1)
    {
        $promocion = \App\Models\Promotion::with('promotionproducts')->find($promoId);
        if (!$promocion) return false;

        $stockRegla = $promocion->getStockDiarioRestante();
        if ($stockRegla !== null) {
            $deltaEnCarrito = 0;
            foreach ($this->carrito as $c) {
                if (isset($c['type']) && $c['type'] === TipoProducto::Promocion->value && $c['promotion_id'] == $promoId) {
                    $qtyOld = (isset($c['guardado']) && $c['guardado']) ? ($this->cantidadesOriginales[$c['item_id']] ?? 0) : 0;
                    $deltaEnCarrito += ($c['quantity'] - $qtyOld);
                }
            }
            if (($deltaEnCarrito + $cantidadAumentar) > $stockRegla) return false;
        }

        foreach ($promocion->promotionproducts as $pp) {
            $producto = $pp->product;
            if (!$producto || $producto->control_stock == 0) continue;

            $necesarioTotal = 0;
            $stockBD = 0;

            if ($pp->variant_id) {
                $variant = \App\Models\Variant::with('stock')->find($pp->variant_id);
                $stockBD = $variant->stock->stock_reserva ?? 0;

                foreach ($this->carrito as $c) {
                    $qtyItem = $c['quantity'];
                    $qtyOld = (isset($c['guardado']) && $c['guardado']) ? ($this->cantidadesOriginales[$c['item_id']] ?? 0) : 0;
                    $deltaItem = $qtyItem - $qtyOld;

                    if ((!isset($c['type']) || $c['type'] === TipoProducto::Producto->value) && $c['variant_id'] == $pp->variant_id) {
                        $necesarioTotal += $deltaItem;
                    } elseif (isset($c['type']) && $c['type'] === TipoProducto::Promocion->value) {
                        $pModel = \App\Models\Promotion::with('promotionproducts')->find($c['promotion_id']);
                        foreach ($pModel->promotionproducts as $subPP) {
                            if ($subPP->variant_id == $pp->variant_id) {
                                $necesarioTotal += ($subPP->quantity * $deltaItem);
                            }
                        }
                    }
                }
                $necesarioTotal += ($pp->quantity * $cantidadAumentar);
            } elseif ($pp->product_id) {
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

            if ($necesarioTotal > $stockBD) return false;
        }

        return true;
    }

    public function agregarProducto(int $productoId)
    {
        $producto = OrdenService::obtenerProductoId($productoId);
        $this->productoSeleccionado = $producto;
        $this->esCortesia = false;
        $this->notaPedido = '';
        $this->selectedAttributes = [];
        $this->variantSeleccionadaId = null;
        $this->precioCalculado = floatval($producto->price);
        $this->stockActualVariante = 0;
        $this->stockReservaVariante = 0;

        if ($producto->attributes->isEmpty()) {
            $varianteUnica = $producto->variants->first();
            if ($varianteUnica) {
                $this->variantSeleccionadaId = $varianteUnica->id;
                $this->stockActualVariante = $varianteUnica->stock?->stock_real ?? 0;
                $this->stockReservaVariante = $varianteUnica->stock?->stock_reserva ?? 0;
            }
        } else {
            foreach ($producto->attributes as $attr) {
                $rawValues = $attr->pivot->values ?? [];

                // TRUCO INFALIBLE: Forzamos a que sea un array asociativo sin importar cómo venga
                $values = is_string($rawValues) ? json_decode($rawValues, true) : json_decode(json_encode($rawValues), true);

                if (is_array($values) && count($values) > 0) {
                    // Ahora estamos 100% seguros de que ['id'] funcionará
                    $this->selectedAttributes[$attr->id] = $values[0]['id'] ?? null;
                }
            }
            // Esto calculará el precio correcto desde el segundo 1 en que se abre el modal
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
        if (!$this->productoSeleccionado) return;
        $precioBase = floatval($this->productoSeleccionado->price);
        $extrasAcumulados = 0;
        foreach ($this->selectedAttributes as $attrId => $valIdSeleccionado) {
            $atributo = $this->productoSeleccionado->attributes->firstWhere('id', $attrId);
            if ($atributo && $valIdSeleccionado) {
                $rawValues = $atributo->pivot->values ?? [];
                $opciones = is_string($rawValues) ? json_decode($rawValues, true) : json_decode(json_encode($rawValues), true);
                $opcion = collect($opciones)->firstWhere('id', $valIdSeleccionado);
                if ($opcion) {
                    $extrasAcumulados += floatval($opcion['extra'] ?? 0);
                }
            }
        }

        $this->precioCalculado = $precioBase + $extrasAcumulados;

        if ($this->productoSeleccionado->variants->isEmpty()) {
            return;
        }
        $matchVariant = null;
        foreach ($this->productoSeleccionado->variants as $variant) {
            $variantValueIds = $variant->values->pluck('id')->toArray();
            $seleccionados = array_filter(array_values($this->selectedAttributes));
            $coincidencias = array_intersect($seleccionados, $variantValueIds);
            if (count($coincidencias) === count($this->productoSeleccionado->attributes)) {
                $matchVariant = $variant;
                break;
            }
        }

        if ($matchVariant) {
            $this->variantSeleccionadaId = $matchVariant->id;
            $this->stockActualVariante = $matchVariant->stock->stock_real ?? 0;
            $this->stockReservaVariante = $matchVariant->stock->stock_reserva ?? 0;
        } else {
            $this->variantSeleccionadaId = null;
            $this->stockActualVariante = 0;
            $this->stockReservaVariante = 0;
        }
    }

    public function confirmarAgregado()
    {
        if (!$this->productoSeleccionado) return;

        $esPromocion = $this->productoSeleccionado instanceof \App\Models\Promotion;

        if (!$esPromocion && $this->productoSeleccionado->variants->count() > 0 && !$this->variantSeleccionadaId) {
            Notification::make()->title('Debes seleccionar una opción válida')->warning()->send();
            return;
        }

        $idBase = $this->productoSeleccionado->id;
        $nombreItem = $this->productoSeleccionado->name;
        $precioFinal = floatval($this->precioCalculado);
        $esCortesia = $this->esCortesia;
        if ($esCortesia) $precioFinal = 0;

        $prodId = $esPromocion ? null : $idBase;
        $promoId = $esPromocion ? $idBase : null;
        $tipoEnum = $esPromocion ? TipoProducto::Promocion->value : TipoProducto::Producto->value;
        $varId = $this->variantSeleccionadaId;

        if (!$esPromocion && $varId) {
            $variante = Variant::with('values')->find($varId);
            if ($variante && $this->productoSeleccionado->attributes->count() > 0) {
                $nombreVariante = $variante->values->pluck('name')->join(' / ');
                $nombreItem .= " ($nombreVariante)";
            }
        }

        $nuevaNota = trim($this->notaPedido);
        $indiceExistente = null;

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

        if ($esPromocion) {
            if (!$this->puedeAgregarPromo($promoId, 1)) {
                Notification::make()->title('Stock insuficiente')->warning()->send();
                return;
            }
        } else {
            if ($this->productoSeleccionado->control_stock == 1 && $this->productoSeleccionado->venta_sin_stock == 0) {
                $stockBase = $this->stockReservaVariante;
                $enCarrito = $indiceExistente !== null ? $this->carrito[$indiceExistente]['quantity'] : 0;
                if (($enCarrito + 1) > $stockBase) {
                    Notification::make()->title('No hay suficiente stock')->warning()->send();
                    return;
                }
            }
        }

        if ($indiceExistente !== null) {
            $this->carrito[$indiceExistente]['quantity']++;
            $this->carrito[$indiceExistente]['total'] = $this->carrito[$indiceExistente]['quantity'] * $precioFinal;

            if (!empty($nuevaNota)) {
                $notaActual = $this->carrito[$indiceExistente]['notes'];
                $this->carrito[$indiceExistente]['notes'] = empty($notaActual) ? $nuevaNota : $notaActual . ', ' . $nuevaNota;
            }

            $this->lastUpdatedItemId = $this->carrito[$indiceExistente]['item_id'];
        } else {
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
                'guardado' => false
            ];

            array_unshift($this->carrito, $nuevoItem);
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

    public function incrementarCantidad($index)
    {
        $item = $this->carrito[$index];

        if (isset($item['type']) && $item['type'] === TipoProducto::Promocion->value) {
            if (!$this->puedeAgregarPromo($item['promotion_id'], 1)) {
                Notification::make()
                    ->title('Stock insuficiente')
                    ->body("No hay suficientes ingredientes (o límite diario) para agregar otro combo.")
                    ->warning()
                    ->send();
                return;
            }
        } else {
            $productoId = $item['product_id'];
            $variantId = $item['variant_id'];
            $producto = Product::find($productoId);

            if ($producto && $producto->control_stock == 1 && $producto->venta_sin_stock == 0) {
                $variante = Variant::with('stock')->find($variantId);
                $stockMaximo = ($variante && $variante->stock) ? $variante->stock->stock_reserva : 0;

                $cantidadEnCarrito = collect($this->carrito)->where('variant_id', $variantId)->sum('quantity');
                if (($cantidadEnCarrito + 1) > $stockMaximo) {
                    Notification::make()->title('Stock insuficiente')->warning()->send();
                    return;
                }
            }
        }

        $this->carrito[$index]['quantity']++;
        $this->carrito[$index]['total'] = $this->carrito[$index]['quantity'] * $this->carrito[$index]['price'];
        $this->lastUpdatedItemId = $this->carrito[$index]['item_id'];
        $this->hayCambios = true;
        $this->calcularTotales();
    }

    public function decrementarCantidad($index)
    {
        if ($this->carrito[$index]['quantity'] > 1) {
            $this->carrito[$index]['quantity']--;
            $this->carrito[$index]['total'] = $this->carrito[$index]['quantity'] * $this->carrito[$index]['price'];
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
        $divisor = get_tax_divisor();
        $this->subtotal = $this->total / $divisor;
        $this->igv = $this->total - $this->subtotal;
    }

    public function agregarPromocion($promoId)
    {
        $promocion = \App\Models\Promotion::with('promotionproducts')->find($promoId);

        if (!$promocion) {
            Notification::make()->title('Promoción no encontrada')->danger()->send();
            return;
        }

        if (!$promocion->isAvailable()) {
            Notification::make()->title('Promoción no disponible')->warning()->send();
            return;
        }

        $this->productoSeleccionado = $promocion;

        if (!$this->productoSeleccionado->relationLoaded('attributes')) {
            $this->productoSeleccionado->setRelation('attributes', collect([]));
        }

        $this->variantSeleccionadaId = null;
        $this->selectedAttributes = [];
        $this->esCortesia = false;
        $this->notaPedido = '';
        $this->precioCalculado = $promocion->price;
        $this->stockActualVariante = 0;
        $this->stockReservaVariante = 0;
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
        $diferencia = $cantidadDeseada - $cantidadActual;

        if ($diferencia > 0) {
            if (isset($item['type']) && $item['type'] === TipoProducto::Promocion->value) {
                if (!$this->puedeAgregarPromo($item['promotion_id'], $diferencia)) {
                    Notification::make()
                        ->title('Stock insuficiente')
                        ->body("No hay stock para esa cantidad.")
                        ->warning()
                        ->send();
                    return;
                }
            }
        }

        $this->carrito[$index]['quantity'] = $cantidadDeseada;
        $this->carrito[$index]['total'] = $cantidadDeseada * $this->carrito[$index]['price'];
        $this->hayCambios = true;
        $this->calcularTotales();
    }

    public function pagarOrden()
    {
        if (!$this->pedido) {
            $this->procesarOrden(); // OJO: Lo corregí a procesarOrden porque guardarOrdenEnBaseDeDatos no existe.
        } else {
            if ($this->hayCambios) {
                $this->actualizarOrden();
            }
        }
        return redirect()->to(PagarOrden::getUrl(['record' => $this->pedido]));
    }

    public function mostrarTiketPrecuenta()
    {
        $config = Filament::getTenant()->cached_config;
        if ($config->mostrar_modal_impresion_precuenta) {
            $this->mostrarModalPrecuenta = true;
        }

        if ($this->canal === 'salon' && $this->mesa) {
            \App\Models\Table::where('id', $this->mesa)->update([
                'estado_mesa' => 'pagando'
            ]);
            \Filament\Notifications\Notification::make()
                ->title('Cuenta solicitada')
                ->body('La mesa ha cambiado a estado "Pagando".')
                ->info()
                ->send();
        }
    }

    public function mostrarPrecuenta(): Action
    {
        return Action::make('mostrarPrecuenta')
            ->label('Mostrar Precuenta')
            ->icon('heroicon-o-document-text')
            ->color('primary')
            ->iconButton()
            ->tooltip('Mostrar Precuenta')
            ->extraAttributes([
                'x-on:click' => 'mobileCartOpen = false',
            ])
            ->requiresConfirmation()
            ->modalHeading('¿Mostrar Precuenta?')
            ->modalDescription('¿Seguro que deseas mostrar la precuenta?')
            ->modalSubmitActionLabel('Sí, Mostrar')
            ->action(function () {
                $this->mostrarTiketPrecuenta($this->pedido);
            });
    }

    public function cerrarPrecuenta()
    {
        $this->mostrarModalPrecuenta = false;
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
