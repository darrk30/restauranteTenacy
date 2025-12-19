<?php

namespace App\Livewire;

use App\Enums\StatusProducto;
use App\Enums\TipoProducto;
use Livewire\Component;
use App\Models\Product;
use App\Models\Category;

class PedidoMesa extends Component
{
    public int $mesa;
    public $categorias;
    public $productos;
    public array $carrito = [];

    public function mount(int $mesa)
    {
        $this->mesa = $mesa;
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

                    // 👉 sumamos stocks por variante
                    $variantStockReal = $variant->stocks->sum('stock_real');
                    $variantStockReserva = $variant->stocks->sum('stock_reserva');

                    // 👉 acumulamos al producto
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

                        // 👇 stocks detallados (NO se tocan)
                        'stocks' => $variant->stocks->map(fn($s) => [
                            'warehouse_id'  => $s->warehouse_id,
                            'stock_real_variante'    => $s->stock_real,
                            'stock_reserva_variante' => $s->stock_reserva,
                            'min_stock'     => $s->min_stock,
                        ])->values(),

                        // 👇 opcional: total por variante (muy útil)
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

                    // 👇 TOTALES A NIVEL PRODUCTO
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
        dd([
            'mesa'  => $this->mesa,
            'data'  => $data,
            'items' => $data['items'] ?? null,
        ]);
    }


    public function render()
    {
        return view('livewire.pedido-mesa');
    }
}
