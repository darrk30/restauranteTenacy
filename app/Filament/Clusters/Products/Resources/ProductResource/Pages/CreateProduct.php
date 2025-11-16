<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Filament\Clusters\Products\Traits\SyncProductAttributesTrait;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use SyncProductAttributesTrait;

    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getState();

        // 1️⃣ Procesar atributos si los hay
        $attributeValues = collect($data['attribute_values'] ?? [])
            ->filter(fn($item) => isset($item['attribute_id']) && !empty($item['values']));

        if ($attributeValues->isNotEmpty()) {
            $valuesByAttribute = $this->syncProductAttributes($attributeValues->toArray(), $this->record);
            $this->syncVariants($valuesByAttribute, $this->record);
        }

        // 2️⃣ Si NO hay atributos, crear la variante principal automáticamente
        if ($attributeValues->isEmpty()) {
            $this->record->variants()->create([
                'extra_price' => 0,       // precio extra 0
                'stock_real' => 0,         // stock inicial
                'stock_virtual' => 0,
                'status' => 'activo',      // estado activo
                'restaurant_id' => $this->record->restaurant_id,
            ]);
        }
    }
}
