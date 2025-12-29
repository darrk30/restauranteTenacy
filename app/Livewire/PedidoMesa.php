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
use Barryvdh\DomPDF\Facade\Pdf;
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

        $this->productos = Product::where('status', StatusProducto::Activo)
            ->where('type', '!=', TipoProducto::Insumo)
            ->where(function ($q) {
                $q->whereHas('categories', function ($q2) {
                    $q2->where('status', true);
                })
                    ->orWhereDoesntHave('categories');
            })
            ->with([
                'categories:id,name',

                'variants' => function ($q) {
                    $q->where('status', 'activo')
                        ->select('id', 'product_id', 'extra_price', 'image_path');
                },

                'variants.values:id,name,attribute_id',
                'variants.values.attribute:id,name',
                'variants.stocks' => function ($q) {
                    $q->select(
                        'id',
                        'variant_id',
                        'warehouse_id',
                        'stock_real',
                        'stock_reserva',
                    );
                },
            ])
            ->select(
                'id',
                'name',
                'price',
                'image_path',
                'cortesia',
                'control_stock',
                'venta_sin_stock'
            )
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                $stockRealTotal = 0;
                $stockReservaTotal = 0;
                $variantOptions = $product->variants->map(function ($variant) use (&$stockRealTotal, &$stockReservaTotal) {
                    $variantStockReal = $variant->stocks->sum('stock_real');
                    $variantStockReserva = $variant->stocks->sum('stock_reserva');
                    $stockRealTotal += $variantStockReal;
                    $stockReservaTotal += $variantStockReserva;
                    return [
                        'id'          => $variant->id,
                        'label'       => $variant->values->isEmpty()
                            ? 'Normal'
                            : $variant->values
                            ->map(fn($v) => $v->attribute->name . ': ' . $v->name)
                            ->implode(' / '),

                        'extra_price' => (float) $variant->extra_price,
                        'image_path'  => $variant->image_path,
                        'stocks' => $variant->stocks->map(fn($s) => [
                            'warehouse_id'  => $s->warehouse_id,
                            'stock_real_variante'    => $s->stock_real,
                            'stock_reserva_variante' => $s->stock_reserva,
                            'min_stock'     => $s->min_stock,
                        ])->values(),
                        'stock_real_total_variante'    => $variantStockReal,
                        'stock_reserva_total_variante' => $variantStockReserva,
                    ];
                })->values();

                return [
                    'id'              => $product->id,
                    'name'            => $product->name,
                    'price'           => (float) $product->price,
                    'image_path'      => $product->image_path,
                    'cortesia'        => $product->cortesia,
                    'control_stock'   => $product->control_stock,
                    'venta_sin_stock' => $product->venta_sin_stock,
                    'stock_real_total'    => $stockRealTotal,
                    'stock_reserva_total' => $stockReservaTotal,
                    'categories' => $product->categories->pluck('id')->values(),
                    'variant_groups' => collect([
                        [
                            'attribute' => 'Opciones',
                            'options'   => $variantOptions,
                        ]
                    ]),
                ];
            })
            ->values();
        //dd($this->productos);
    }

    protected function cargarPedido(int $pedidoId): void
    {
        $order = Order::with([
            'details' => function ($query) {
                $query->where('status', '!=', 'cancelado');
            },
            'details.product',
            'details.variant.values.attribute',
        ])->where('id', $pedidoId)
            ->where('table_id', $this->mesa)
            ->where('status', '!=', 'cancelado')
            ->first();

        if (! $order) {
            return;
        }

        $this->pedidocompleto = $order;

        foreach ($order->details as $detail) {

            $variantLabel = $detail->variant && $detail->variant->values->isNotEmpty()
                ? $detail->variant->values
                ->map(fn($v) => $v->attribute->name . ': ' . $v->name)
                ->implode(' / ')
                : 'Normal';

            $tipo = $detail->cortesia ? 'cortesia' : 'normal';

            $this->carrito[] = [
                'detail_id' => $detail->id,
                'key'      => $detail->product_id . '-' . $detail->variant_id . '-' . $tipo,
                'nombre'   => $detail->product?->name . ' (' . $variantLabel . ')',
                'precio'   => (float) $detail->price,
                'cantidad' => (int) $detail->cantidad,
                'nota'     => $detail->notes ?? '',
                'cortesia' => (bool) $detail->cortesia,
            ];
        }
        //dd($this->carrito);
    }


    public function ordenar(array $data)
    {
        $order = null;
        $esPedidoNuevo = false;
        DB::transaction(function () use ($data, &$order, &$esPedidoNuevo) {
            $itemsParaCocina = [];
            if (!empty($data['pedido_id'])) {
                $order = Order::with('table')->findOrFail($data['pedido_id']);
            } else {
                $esPedidoNuevo = true;
                $empresaId = filament()->getTenant()->id;
                $ultimoNumero = Order::where('restaurant_id', $empresaId)
                    ->selectRaw("MAX(CAST(SUBSTRING_INDEX(code, '-', -1) AS UNSIGNED)) as max_num")
                    ->value('max_num');
                $siguienteNumero = ($ultimoNumero ?? 0) + 1;
                $codigoPedido = 'PED-' . str_pad($siguienteNumero, 6, '0', STR_PAD_LEFT);
                $total = $data['total'];
                $subtotal = round($total / 1.18, 2);
                $igv   = round($total - $subtotal, 2);
                $order = Order::create([
                    'table_id'     => $this->mesa,
                    'code'          => $codigoPedido,
                    'status'       => statusPedido::Pendiente->value,
                    'subtotal'     => $subtotal,
                    'igv'          => $igv,
                    'total'        => $total,
                    'fecha_pedido' => Carbon::now('America/Lima'),
                    'user_id'      => Auth::id(),
                ]);
                Table::where('id', $this->mesa)->update([
                    'estado_mesa' => 'ocupada',
                    'order_id'    => $order->id,
                ]);
            }
            $detallesExistentes = OrderDetail::with('product')
                ->where('order_id', $order->id)
                ->where('status', '!=', 'cancelado')
                ->get()
                ->keyBy(fn($d) => $d->product_id . '-' . $d->variant_id);
            foreach ($data['items'] as $item) {
                $key = $item['producto_id'] . '-' . $item['variante_id'];
                if ($detallesExistentes->has($key)) {
                    $detalle = $detallesExistentes[$key];
                    $cantidadAnterior = (int) $detalle->cantidad;
                    $cantidadNueva    = (int) $item['cantidad'];
                    $diferencia       = $cantidadNueva - $cantidadAnterior;
                    // dd($diferencia);
                    $detalle->update([
                        'cantidad' => $cantidadNueva,
                        'price'    => $item['precio'],
                        'cortesia' => (bool) ($item['cortesia'] ?? false),
                        'notes'    => !empty($item['nota']) ? trim($item['nota']) : null,
                    ]);
                    if ($diferencia > 0) {
                        WarehouseStock::where('variant_id', $item['variante_id'])->decrement('stock_reserva', $diferencia);
                    }elseif ($diferencia < 0) {
                        WarehouseStock::where('variant_id', $item['variante_id'])->increment('stock_reserva', abs($diferencia));
                    }
                    if ($diferencia > 0 || !empty($item['nota'])) {
                        $itemsParaCocina[] = [
                            'cantidad' => $diferencia > 0 ? $diferencia : 1,
                            'producto' => $detalle->product->name,
                            'nota'     => $item['nota'] ?? '',
                        ];
                    }

                } else {
                    $detalle = OrderDetail::create([
                        'order_id'            => $order->id,
                        'restaurant_id'       => $order->restaurant_id,
                        'product_id'          => $item['producto_id'],
                        'variant_id'          => $item['variante_id'],
                        'price'               => $item['precio'],
                        'cantidad'            => $item['cantidad'],
                        'subTotal'            => $item['subtotal'],
                        'status'              => 'pendiente',
                        'notes'               => $item['nota'] ?? null,
                        'cortesia'            => (bool) ($item['cortesia'] ?? false),
                        'fecha_envio_cocina'  => now(),
                    ]);
                    WarehouseStock::where('variant_id', $item['variante_id'])->decrement('stock_reserva', $item['cantidad']);
                    $itemsParaCocina[] = [
                        'cantidad' => $item['cantidad'],
                        'producto' => $detalle->product->name,
                        'nota'     => $item['nota'] ?? '',
                    ];
                }
            }
            $keysActuales = collect($data['items'])->map(fn($i) => $i['producto_id'] . '-' . $i['variante_id'])->toArray();
            foreach ($detallesExistentes as $key => $detalleEliminado) {
                if (!in_array($key, $keysActuales)) {
                    WarehouseStock::where('variant_id', $detalleEliminado->variant_id)->increment('stock_reserva', $detalleEliminado->cantidad);
                    $detalleEliminado->update([
                        'status' => 'cancelado',
                    ]);
                    $itemsParaCocina[] = [
                        'cantidad' => $detalleEliminado->cantidad,
                        'producto' => $detalleEliminado->product->name,
                        'nota'     => 'ELIMINADO',
                    ];
                }
            }
            $ordenCocina = [
                'pedido' => $order->code,
                'mesa'   => $order->table->name ?? $this->mesa,
                'mozo'   => Auth::user()->name,
                'fecha'  => now('America/Lima')->format('d/m/Y H:i'),
                'tipo'   => $esPedidoNuevo ? 'NUEVO PEDIDO' : 'ACTUALIZACIÓN',
                'items'  => $itemsParaCocina,
            ];
        });
        if (! $order instanceof Order) {
            Log::error('PedidoMesa@ordenar: No se pudo crear u obtener el pedido', [
                'mesa' => $this->mesa,
                'payload' => $data,
            ]);

            $this->dispatch('pedido-error', message: 'Ocurrió un error al guardar el pedido.');
            return;
        }
        $this->dispatch('pedido-guardado', orderId: $order->id, esNuevo: $esPedidoNuevo);
    }

    public function anularPedido($pedidoId)
    {
        DB::transaction(function () use ($pedidoId) {
            $order = Order::with('details')->findOrFail($pedidoId);
            foreach ($order->details as $detail) {
                if ($detail->status !== statusPedido::Cancelado->value) {
                    WarehouseStock::where('variant_id', $detail->variant_id)
                        ->increment('stock_reserva', $detail->cantidad);
                }
            }
            $order->details()
                ->where('status', '!=', statusPedido::Cancelado)
                ->update([
                    'status' => statusPedido::Cancelado,
                ]);
            $order->update([
                'status' => statusPedido::Cancelado,
            ]);
            if ($order->table_id) {
                Table::where('id', $order->table_id)->update([
                    'estado_mesa' => 'libre',
                    'order_id'    => null,
                ]);
            }
        });

        $this->dispatch('pedido-anulado');
    }

    public function cancelarDetalle($detailId)
    {
        $detail = OrderDetail::find($detailId);
        if (! $detail || $detail->status === 'cancelado') {
            return;
        }
        DB::transaction(function () use ($detail) {
            WarehouseStock::where('variant_id', $detail->variant_id)
                ->increment('stock_reserva', $detail->cantidad);
            $detail->update([
                'status' => 'cancelado',
            ]);
        });
        $this->dispatch('detalle-cancelado');
    }

    public function render()
    {
        return view('livewire.pedido-mesa');
    }
}
