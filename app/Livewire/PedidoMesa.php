<?php

namespace App\Livewire;

use App\Enums\statusPedido;
use App\Enums\StatusProducto;
use App\Enums\TipoProducto;
use Livewire\Component;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Table;
use App\Models\WarehouseStock;
use App\Models\Variant; // IMPORTANTE: Agregado para buscar precios de variantes
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PedidoMesa extends Component
{
    public int $mesa;
    public ?int $pedido = null;
    public ?Order $pedidocompleto = null;
    public $categorias;
    public $productos;
    public array $carrito = [];
    public $restaurantSlug;

    public function mount(int $mesa, $tenant, ?int $pedido = null)
    {
        $this->mesa = $mesa;
        $this->pedido = $pedido;
        $this->restaurantSlug = $tenant?->slug;

        if ($this->pedido) {
            $this->cargarPedido($this->pedido);
        }

        $this->categorias = Category::where('status', true)->select('id', 'name')->get();

        // Mantenemos tu lógica de carga de productos (funcional para catálogos pequeños/medianos)
        $this->productos = Product::where('status', StatusProducto::Activo)
            ->where('type', '!=', TipoProducto::Insumo)
            ->where(function ($q) {
                $q->whereHas('categories', function ($q2) {
                    $q2->where('status', true);
                })->orWhereDoesntHave('categories');
            })
            ->with([
                'categories:id,name',
                'variants' => function ($q) {
                    $q->where('status', 'activo')->select('id', 'product_id', 'extra_price', 'image_path');
                },
                'variants.values:id,name,attribute_id',
                'variants.values.attribute:id,name',
                'variants.stocks:id,variant_id,warehouse_id,stock_real,stock_reserva,min_stock',
            ])
            ->select('id', 'name', 'price', 'image_path', 'cortesia', 'control_stock', 'venta_sin_stock')
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                return $this->transformarProductoParaVista($product);
            })->values();
    }

    private function transformarProductoParaVista($product)
    {
        $stockRealTotal = 0;
        $stockReservaTotal = 0;
        $variantOptions = $product->variants->map(function ($variant) use (&$stockRealTotal, &$stockReservaTotal) {
            $variantStockReal = $variant->stocks->sum('stock_real');
            $variantStockReserva = $variant->stocks->sum('stock_reserva');
            $stockRealTotal += $variantStockReal;
            $stockReservaTotal += $variantStockReserva;
            return [
                'id' => $variant->id,
                'label' => $variant->values->isEmpty() ? 'Normal' : $variant->values->map(fn($v) => $v->attribute->name . ': ' . $v->name)->implode(' / '),
                'extra_price' => (float) $variant->extra_price,
                'image_path' => $variant->image_path,
                'stocks' => $variant->stocks,
                'stock_reserva_total_variante' => $variantStockReserva,
            ];
        })->values();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'image_path' => $product->image_path,
            'cortesia' => $product->cortesia,
            'control_stock' => $product->control_stock,
            'venta_sin_stock' => $product->venta_sin_stock,
            'stock_reserva_total' => $stockReservaTotal,
            'categories' => $product->categories->pluck('id')->values(),
            'variant_groups' => collect([['attribute' => 'Opciones', 'options' => $variantOptions]]),
        ];
    }

    protected function cargarPedido(int $pedidoId): void
    {
        $order = Order::with(['details' => fn($q) => $q->where('status', '!=', 'cancelado'), 'details.product', 'details.variant.values.attribute'])
            ->where('id', $pedidoId)->where('table_id', $this->mesa)->where('status', '!=', 'cancelado')->first();

        if (!$order) return;

        $this->pedidocompleto = $order;

        foreach ($order->details as $detail) {
            $variantLabel = $detail->variant && $detail->variant->values->isNotEmpty()
                ? $detail->variant->values->map(fn($v) => $v->attribute->name . ': ' . $v->name)->implode(' / ')
                : 'Normal';

            $tipo = $detail->cortesia ? 'cortesia' : 'normal';

            $this->carrito[] = [
                'detail_id' => $detail->id,
                'key' => $detail->product_id . '-' . $detail->variant_id . '-' . $tipo,
                'nombre' => $detail->product?->name . ' (' . $variantLabel . ')',
                'precio' => (float) $detail->price,
                'cantidad' => (int) $detail->cantidad,
                'nota' => $detail->notes ?? '',
                'cortesia' => (bool) $detail->cortesia,
            ];
        }
    }

    public function ordenar(array $data)
    {
        $order = null;
        $esPedidoNuevo = false;

        DB::beginTransaction();

        try {
            // 1. CREAR O RECUPERAR PEDIDO
            if (!empty($data['pedido_id'])) {
                $order = Order::with('table')->findOrFail($data['pedido_id']);
            } else {
                $esPedidoNuevo = true;
                $empresaId = filament()->getTenant()->id;

                $ultimoNumero = Order::where('restaurant_id', $empresaId)
                    ->selectRaw("MAX(CAST(SUBSTRING_INDEX(code, '-', -1) AS UNSIGNED)) as max_num")
                    ->lockForUpdate()
                    ->value('max_num');

                $codigoPedido = 'PED-' . str_pad(($ultimoNumero ?? 0) + 1, 6, '0', STR_PAD_LEFT);

                $order = Order::create([
                    'table_id'     => $this->mesa,
                    'code'         => $codigoPedido,
                    'status'       => statusPedido::Pendiente->value,
                    'fecha_pedido' => now('America/Lima'),
                    'user_id'      => Auth::id(),
                    'subtotal'     => 0,
                    'igv'          => 0,
                    'total'        => 0,
                ]);

                Table::where('id', $this->mesa)->update(['estado_mesa' => 'ocupada', 'order_id' => $order->id]);
            }

            $itemsParaCocina = []; // Aquí guardaremos todo lo que cambió
            $totalCalculadoDelPedido = 0;

            // 2. OBTENER DETALLES EXISTENTES
            $detallesExistentes = OrderDetail::where('order_id', $order->id)
                ->where('status', '!=', 'cancelado')
                ->get()
                ->keyBy(fn($d) => $d->product_id . '-' . $d->variant_id . '-' . ($d->cortesia ? '1' : '0'));

            // 3. PROCESAR ITEMS (AGREGAR O ACTUALIZAR)
            foreach ($data['items'] as $item) {
                $productoDB = Product::find($item['producto_id']);
                if (!$productoDB) continue;

                $precioReal = $productoDB->price;
                if (!empty($item['variante_id'])) {
                    $varianteDB = Variant::find($item['variante_id']);
                    if ($varianteDB) $precioReal += $varianteDB->extra_price;
                }

                $esCortesia = (bool) ($item['cortesia'] ?? false);
                $precioFinal = $esCortesia ? 0 : $precioReal;
                $cantidad = (int) $item['cantidad'];
                $subtotalItem = $precioFinal * $cantidad;
                $totalCalculadoDelPedido += $subtotalItem;

                $key = $item['producto_id'] . '-' . $item['variante_id'] . '-' . ($esCortesia ? '1' : '0');
                $nota = !empty($item['nota']) ? trim($item['nota']) : null;

                if ($detallesExistentes->has($key)) {
                    // == ACTUALIZAR ==
                    $detalle = $detallesExistentes[$key];
                    $diferencia = $cantidad - $detalle->cantidad;

                    if ($diferencia != 0) {
                        $this->actualizarStock($item['variante_id'], $diferencia);
                        $detalle->update([
                            'cantidad' => $cantidad,
                            'price'    => $precioFinal,
                            'subTotal' => $subtotalItem,
                            'notes'    => $nota
                        ]);
                    } elseif ($detalle->notes !== $nota) {
                        $detalle->update(['notes' => $nota]);
                    }

                    if ($diferencia > 0) {
                        // AGREGAMOS ESTADO 'NUEVO' A LA LISTA DE IMPRESIÓN
                        $itemsParaCocina[] = [
                            'cantidad' => $diferencia,
                            'producto' => $productoDB->name,
                            'nota'     => $nota,
                            'tipo_impresion' => 'nuevo' // <--- IMPORTANTE
                        ];
                    } elseif ($diferencia < 0) {
                        // CASO: SE QUITARON ITEMS (Ej: de 2 a 1 => imprimo 1 cancelado)
                        // Usamos abs() para mostrar la cantidad positiva en el ticket (1, no -1)
                        $itemsParaCocina[] = [
                            'cantidad' => abs($diferencia),
                            'producto' => $productoDB->name,
                            'nota'     => '',
                            'tipo_impresion' => 'cancelado' // Saldrá tachado
                        ];
                    }
                } else {
                    // == CREAR ==
                    $this->actualizarStock($item['variante_id'], $cantidad);
                    OrderDetail::create([
                        'order_id'           => $order->id,
                        'restaurant_id'      => $order->restaurant_id,
                        'product_id'         => $item['producto_id'],
                        'variant_id'         => $item['variante_id'],
                        'price'              => $precioFinal,
                        'cantidad'           => $cantidad,
                        'subTotal'           => $subtotalItem,
                        'status'             => 'pendiente',
                        'notes'              => $nota,
                        'cortesia'           => $esCortesia,
                        'fecha_envio_cocina' => now(),
                    ]);

                    // AGREGAMOS ESTADO 'NUEVO'
                    $itemsParaCocina[] = [
                        'cantidad' => $cantidad,
                        'producto' => $productoDB->name,
                        'nota'     => $nota,
                        'tipo_impresion' => 'nuevo' // <--- IMPORTANTE
                    ];
                }
            }

            // 4. ELIMINAR ITEMS
            $keysActuales = collect($data['items'])->map(fn($i) => $i['producto_id'] . '-' . $i['variante_id'] . '-' . (($i['cortesia'] ?? false) ? '1' : '0'))->toArray();

            foreach ($detallesExistentes as $key => $detalleEliminado) {
                if (!in_array($key, $keysActuales)) {
                    $this->actualizarStock($detalleEliminado->variant_id, - ($detalleEliminado->cantidad));
                    $detalleEliminado->update(['status' => 'cancelado']);

                    // AGREGAMOS ESTADO 'CANCELADO'
                    $itemsParaCocina[] = [
                        'cantidad' => $detalleEliminado->cantidad,
                        'producto' => $detalleEliminado->product->name,
                        'nota'     => '',
                        'tipo_impresion' => 'cancelado' // <--- IMPORTANTE: ESTO DIFERENCIA EN EL PDF
                    ];
                }
            }

            // 5. ACTUALIZAR TOTALES
            $subtotalOrder = round($totalCalculadoDelPedido / 1.18, 2);
            $igvOrder = round($totalCalculadoDelPedido - $subtotalOrder, 2);

            $order->update([
                'subtotal' => $subtotalOrder,
                'igv'      => $igvOrder,
                'total'    => $totalCalculadoDelPedido
            ]);

            // 6. IMPRESIÓN INTELIGENTE
            if (count($itemsParaCocina) > 0) {
                $itemsPDF = [];
                foreach ($itemsParaCocina as $item) {
                    $itemsPDF[] = [
                        'cantidad' => $item['cantidad'],
                        'producto' => $item['producto'],
                        'nota'     => $item['nota'],
                        'estado'   => $item['tipo_impresion']
                    ];
                }

                $titulo = $esPedidoNuevo ? 'NUEVO PEDIDO' : 'PEDIDO ACTUALIZADO';
                $this->enviarAImpresion($titulo, $itemsPDF);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Pedido Mesa ' . $this->mesa . ': ' . $e->getMessage());
            $this->dispatch('pedido-error', message: 'Error: ' . $e->getMessage());
            return;
        }

        $this->dispatch('pedido-guardado', orderId: $order->id, esNuevo: $esPedidoNuevo);
    }

    private function actualizarStock($variantId, $diferencia)
    {
        if ($diferencia == 0 || !$variantId) return;
        if ($diferencia > 0) {
            WarehouseStock::where('variant_id', $variantId)->decrement('stock_reserva', $diferencia);
        } else {
            WarehouseStock::where('variant_id', $variantId)->increment('stock_reserva', abs($diferencia));
        }
    }

    public function anularPedido($pedidoId)
    {
        DB::transaction(function () use ($pedidoId) {
            $order = Order::with('details')->findOrFail($pedidoId);
            $itemsPDF = [];

            foreach ($order->details as $detail) {
                // Solo devolvemos stock si no estaba cancelado ya
                if ($detail->status !== statusPedido::Cancelado->value) {
                    WarehouseStock::where('variant_id', $detail->variant_id)->increment('stock_reserva', $detail->cantidad);

                    // Agregamos a la lista de impresión
                    $itemsPDF[] = [
                        'cantidad' => $detail->cantidad,
                        'producto' => $detail->product->name,
                        'nota'     => '',
                        'estado'   => 'cancelado' // CSS class: tachado
                    ];
                }
            }

            $order->details()->where('status', '!=', statusPedido::Cancelado)->update(['status' => statusPedido::Cancelado]);
            $order->update(['status' => statusPedido::Cancelado]);

            if ($order->table_id) {
                Table::where('id', $order->table_id)->update(['estado_mesa' => 'libre', 'order_id' => null]);
            }

            $this->enviarAImpresion('PEDIDO ANULADO', $itemsPDF);
        });

        $this->dispatch('pedido-anulado');
    }

    public function cancelarDetalle($detailId)
    {
        $detail = OrderDetail::with('product')->find($detailId);
        if (!$detail || $detail->status === 'cancelado') return;

        DB::transaction(function () use ($detail) {
            WarehouseStock::where('variant_id', $detail->variant_id)->increment('stock_reserva', $detail->cantidad);
            $detail->update(['status' => 'cancelado']);

            $itemsPDF = [[
                'cantidad' => $detail->cantidad,
                'producto' => $detail->product->name,
                'nota'     => '',
                'estado'   => 'cancelado'
            ]];

            $this->enviarAImpresion('ITEM ELIMINADO', $itemsPDF);
        });

        $this->dispatch('detalle-cancelado');
    }

    // ==========================================
    //  MÉTODO CORREGIDO: ENVIAR A IMPRESIÓN
    // ==========================================
    private function enviarAImpresion($titulo, $items)
    {
        if (count($items) === 0) return;

        // Armamos el paquete visual (Ya no necesitamos ID de orden en URL)
        $paquete = [
            'titulo' => $titulo,
            'items'  => $items,
            'meta'   => [
                'mesa'   => 'Mesa ' . $this->mesa,
                'mozo'   => Auth::user()->name,
                'fecha'  => now('America/Lima')->format('d/m/Y H:i'),
                'codigo' => $this->pedidocompleto?->code ?? '---'
            ]
        ];

        // Usamos PUT en lugar de FLASH para evitar condiciones de carrera (error 404)
        session()->put('ticket_data', $paquete);

        // La URL NO lleva items, solo el tenant
        $this->dispatch('abrir-impresion', url: route('comanda.generica', [
            'tenant' => $this->restaurantSlug
        ]));
    }

    public function render()
    {
        return view('livewire.pedido-mesa');
    }
}
