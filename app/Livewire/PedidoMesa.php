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
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PedidoMesa extends Component
{
    public int $mesa;
    public $categorias;
    public $productos;
    public array $carrito = [];
    public $restaurantSlug;

    public function mount(int $mesa, $tenant)
    {
        $this->mesa = $mesa;
        $tenant = Filament::getTenant(); // devuelve el modelo Restaurant

        $this->restaurantSlug = $tenant?->slug;
        // Categorías
        $this->categorias = Category::where('status', true)
            ->select('id', 'name')
            ->get();

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

                // 👇 ESTA ES LA CLAVE
                'variants.stocks' => function ($q) {
                    $q->select(
                        'id',
                        'variant_id',
                        'warehouse_id',
                        'stock_real',
                        'stock_reserva',
                        'min_stock'
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

    public function ordenar(array $data)
    {
        DB::transaction(function () use ($data) {

            // 1️⃣ Crear PEDIDO
            $order = Order::create([
                'table_id'      => $this->mesa,
                'code'          => 'PED-' . now()->format('YmdHis'),
                'status'        => statusPedido::Pendiente,
                'subtotal'      => collect($data['items'])->sum('subtotal'),
                'igv'           => 0, // luego puedes calcularlo
                'total'         => $data['total'],
                'fecha_pedido'  => Carbon::now('America/Lima'),
                'user_id'       => Auth::id(),
            ]);

            // 2️⃣ Crear DETALLES
            foreach ($data['items'] as $item) {
                OrderDetail::create([
                    'order_id'      => $order->id,
                    'restaurant_id' => $order->restaurant_id,
                    'product_id'    => $item['producto_id'],
                    'variant_id'    => $item['variante_id'],
                    'price'         => $item['precio'],
                    'cantidad'      => $item['cantidad'],
                    'status'        => 'pendiente',
                    'notes'         => $item['nota'] ?? null,
                    'fecha_envio_cocina' => null,
                    'fecha_listo'   => null,
                ]);
            }

            WarehouseStock::where('variant_id', $item['variante_id'])
                ->decrement('stock_reserva', $item['cantidad']);

            // 3️⃣ (Opcional) actualizar estado de la mesa
            Table::where('id', $this->mesa)->update([
                'estado_mesa' => 'ocupada',
                'order_id'    => $order->id,
            ]);
        });

        // 4️⃣ limpiar carrito (frontend)
        $this->dispatch('pedido-guardado');
    }


    public function render()
    {
        return view('livewire.pedido-mesa');
    }
}
