<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use Filament\Actions\Action;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Filament\Clusters\Products\Traits\SyncProductAttributesTrait;
use App\Models\Variant;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use SyncProductAttributesTrait;

    protected static string $resource = ProductResource::class;

    protected function afterSave(): void
    {
        $data = $this->form->getState();
        $attributeValues = collect($data['attribute_values'] ?? [])->filter(fn($item) => isset($item['attribute_id']) && !empty($item['values']));

        if ($attributeValues->isEmpty()) {
            // 1️⃣ Sin atributos → activar la variante principal
            $this->record->attributes()->sync([]);

            // Buscar variante sin valores (la principal) y activarla
            $defaultVariant = $this->record->variants()->doesntHave('values')->first();

            if ($defaultVariant) {
                $defaultVariant->update(['status' => 'activo']);
            }

            // Archivar el resto de variantes que sí tienen valores
            $this->record->variants()->whereHas('values')->update(['status' => 'archivado']);

            return;
        }

        // 2️⃣ Con atributos → sincronizar atributos y variantes
        $valuesByAttribute = $this->syncProductAttributes($attributeValues->toArray(), $this->record);
        $this->syncVariants($valuesByAttribute, $this->record);

        // Archivar la variante principal (sin valores)
        $this->record->variants()
            ->doesntHave('values')
            ->update(['status' => 'archivado']);
    }


    protected function mutateFormDataBeforeFill(array $data): array
    {
        $product = $this->record->load('attributes');
        /** @var Product $product */
        if ($product->attributes->isEmpty()) {
            return $data;
        }

        $data['attribute_values'] = $product->attributes->map(function ($attr) {
            $decoded = json_decode($attr->pivot->values ?? '[]', true);
            // dd($decoded);
            return [
                'attribute_id' => $attr->id,
                // extrae solo los IDs de los valores asociados
                'values' => collect($decoded)->pluck('id')->filter()->values()->toArray() ?: [],
                'extra_prices' => collect($decoded)->mapWithKeys(function ($item) {
                    return [$item['id'] => $item['extra'] ?? 0];
                })->toArray(),
            ];
        })->values()->toArray();
        // dd($data['attribute_values']);

        return $data;
    }


    protected function getHeaderActions(): array
    {
        return [
            Action::make('ver_variantes')
                ->label(fn() => "Variantes (" . $this->record->variants()->where('status', 'activo')->count() . ")")
                ->color('info')
                ->icon('heroicon-o-squares-2x2')
                ->visible(fn() => $this->record->exists)
                ->url(fn() => static::getResource()::getUrl('variants', ['record' => $this->record])),


            DeleteAction::make(),
        ];
    }
}
