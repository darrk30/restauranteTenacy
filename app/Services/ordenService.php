<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class OrdenService
{
    public static function obtenerCategorias(): Collection
    {
        return Category::query()->where('status', true)->get();
    }

    public static function obtenerProductos(?int $categoriaId = null, ?string $search = null): Collection
    {
        $productos = Product::query()
            ->with([
                'attributes', 
                'variants' => function ($q) {
                    $q->where('status', 'activo')->select('id', 'product_id', 'extra_price', 'image_path');
                },
                'variants.values.attribute',
                'variants.stocks',
            ])
            ->activos()                 
            ->porCategoria($categoriaId) 
            ->buscar($search)            
            ->get();
        //dd($productos);
        return $productos;
    }
}