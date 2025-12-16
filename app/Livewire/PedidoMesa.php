<?php

namespace App\Livewire;

use App\Enums\StatusProducto;
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

        // Productos + categorías + variantes + valores + atributos
        $this->productos = Product::where('status', StatusProducto::Activo)
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
                        ->select('id', 'product_id', 'extra_price');
                },
                'variants.values:id,name,attribute_id',
                'variants.values.attribute:id,name',
            ])
            ->select('id', 'name', 'price', 'image_path')
            ->orderBy('name')
            ->get()
            ->map(function ($product) {

                $groups = $product->variants
                    ->flatMap(function ($variant) {
                        if ($variant->values->isNotEmpty()) {
                            return $variant->values->map(function ($value) use ($variant) {
                                return [
                                    'attribute'   => $value->attribute?->name ?? 'Opciones',
                                    'variant_id'  => $variant->id,
                                    'label'       => $value->name,
                                    'extra_price' => (float) $variant->extra_price,
                                ];
                            });
                        }
                        return [[
                            'attribute'   => 'Opciones',
                            'variant_id'  => $variant->id,
                            'label'       => 'Normal',
                            'extra_price' => (float) $variant->extra_price,
                        ]];
                    })
                    ->groupBy('attribute')
                    ->map(function ($items, $attributeName) {
                        return [
                            'attribute' => $attributeName,
                            'options'   => $items->map(function ($item) {
                                return [
                                    'id'          => $item['variant_id'],
                                    'label'       => $item['label'],
                                    'extra_price' => $item['extra_price'],
                                ];
                            })->values(),
                        ];
                    })
                    ->values();

                return [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'price'          => (float) $product->price,
                    'image_path'     => $product->image_path,
                    'categories'     => $product->categories->pluck('id')->values(),
                    'variant_groups' => $groups,
                ];
            })
            ->values();
        // dd($this->productos);
    }

    // public function agregarAlCarrito($productoId, $variantData, $cantidad)
    // {
    //     $producto = $this->productos->firstWhere('id', $productoId);
    //     if (!$producto) return;

    //     if ($variantData === null) {
    //         $key = $productoId . '-0';

    //         if (!isset($this->carrito[$key])) {
    //             $this->carrito[$key] = [
    //                 'nombre'   => $producto['name'],
    //                 'precio'   => $producto['price'],
    //                 'cantidad' => $cantidad,
    //             ];
    //         } else {
    //             $this->carrito[$key]['cantidad'] += $cantidad;
    //         }

    //         return;
    //     }

    //     $key = $productoId . '-' . md5(json_encode($variantData));
    //     if (!isset($this->carrito[$key])) {
    //         $this->carrito[$key] = [
    //             'nombre'   => $producto['name'] . ' (' . $variantData['label'] . ')',
    //             'precio'   => $producto['price'] + $variantData['extra_price'],
    //             'cantidad' => $cantidad,
    //         ];
    //     } else {
    //         $this->carrito[$key]['cantidad'] += $cantidad;
    //     }
    // }



    public function render()
    {
        return view('livewire.pedido-mesa');
    }
}
