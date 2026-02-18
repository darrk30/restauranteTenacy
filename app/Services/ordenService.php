<?php

namespace App\Services;

use App\Enums\TipoProducto;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;

class OrdenService
{
    public static function obtenerCategorias(): Collection
    {
        return Category::query()->where('status', true)->get();
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
}
