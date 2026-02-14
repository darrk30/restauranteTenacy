<?php

namespace App\Services;

use Exception;
use App\Filament\Clusters\Products\Traits\SyncProductAttributesTrait;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductService
{
    use SyncProductAttributesTrait;

    public function handleAfterCreate(Product $product, array $data): void
    {
        $attributeValues = collect($data['attribute_values'] ?? [])
            ->filter(fn($item) => isset($item['attribute_id']) && !empty($item['values']));

        if ($attributeValues->isNotEmpty()) {
            // aquí usamos el método del trait; le pasamos $product
            $valuesByAttribute = $this->syncProductAttributes($attributeValues->toArray(), $product);
            $this->syncVariants($valuesByAttribute, $product);
            return;
        }

        // Si no hay atributos -> crear variante principal
        $variant = $product->variants()->create([
            'extra_price' => 0,
            'status' => 'activo',
            'restaurant_id' => $product->restaurant_id,
        ]);

        do {
            $code = "producto_{$variant->id}";
        } while ($product->variants()->where('internal_code', $code)->exists());

        $variant->update(['internal_code' => $code]);
    }

    public function validateAndGenerateSlug(array $data): array
    {
        if (!empty($data['name'])) {
            $slug = Str::slug($data['name']. '-' . filament()->getTenant()->id);

            if (Product::where('slug', $slug)->exists()) {
                throw new Exception("El slug ya existe: {$slug}");
            }

            $data['slug'] = $slug;
        }

        return $data;
    }
}
