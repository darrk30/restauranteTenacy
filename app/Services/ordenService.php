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
            ->buscar($search)
            ->get()
            ->map(function ($product) {
                // AQUÍ ESTÁ EL TRUCO:
                // Verificamos si 'type' es una instancia de tu Enum para sacar su valor (string).
                // Si no, lo dejamos como está o asignamos 'Producto' por defecto.
                $tipoRaw = $product->type instanceof TipoProducto
                    ? $product->type->value
                    : ($product->type ?? 'Producto');

                // Sobreescribimos el atributo en memoria para que el Blade reciba un STRING simple
                $product->type = $tipoRaw;

                return $product;
            });

        // 2. Obtener PROMOCIONES
        $promociones = Promotion::query()
            ->with([
                'rules',                       // 1. Para validar días, horas y límites diarios
                'promotionproducts.product',   // 2. Para ver la configuración "control_stock" del producto padre
                'promotionproducts.variant.stock' // 3. CRÍTICO: Para poder sumar el stock de la tabla warehouse_stock
            ])
            // ->where('status', 'activo') // Puedes descomentar esto si ya arreglaste mayúsculas/minúsculas en BD
            // ->where('visible', true)
            ->when($categoriaId, fn($q) => $q->where('category_id', $categoriaId))
            ->when($search, fn($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->get()
            // Filtro de disponibilidad (Fecha, Estado, Reglas de Tiempo)
            ->filter(function ($promo) {
                return $promo->isAvailable();
            })
            ->map(function ($promo) {
                // Normalización para el Frontend
                $promo->type = TipoProducto::Promocion->value;

                // Inicializamos en false, pero recuerda que el "transform" 
                // de OrdenMesa.php calculará el valor real basándose en stock físico y límites.
                $promo->esta_agotado = false;

                $promo->setRelation('attributes', new Collection());
                return $promo;
            });

        //dd($productos->concat($promociones)->sortBy('name'));
        // 3. Unir y devolver ordenado
        return $productos->concat($promociones)->sortBy('name');
    }

    public static function obtenerProductoId(int $id): Product | null
    {
        $producto = Product::with(['attributes', 'variants.values', 'variants.stock'])->find($id);

        if (!$producto) {
            return null;
        }

        // También aplicamos la conversión aquí por si acaso se usa individualmente
        $tipoRaw = $producto->type instanceof TipoProducto
            ? $producto->type->value
            : ($producto->type ?? 'Producto');
        $producto->type = $tipoRaw;

        return $producto;
    }
}
