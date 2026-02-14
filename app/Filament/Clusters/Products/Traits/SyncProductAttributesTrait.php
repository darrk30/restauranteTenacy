<?php

namespace App\Filament\Clusters\Products\Traits;

use App\Models\Value;
use App\Models\Variant;

trait SyncProductAttributesTrait
{
    public function syncProductAttributes(array $attributeValues, $product): array
    {
        $allValueIds = collect($attributeValues)->pluck('values')->flatten()->unique()->toArray();
        $values = Value::whereIn('id', $allValueIds)->get(['id', 'name', 'value'])->keyBy('id');

        // $syncData = collect($attributeValues)->mapWithKeys(function ($item) use ($values) {
        //     $selectedValues = collect($item['values'] ?? [])
        //         ->map(fn($id) => $values->get($id))
        //         ->filter()
        //         ->values()
        //         ->toArray();

        //     return [$item['attribute_id'] => ['values' => json_encode($selectedValues)]];
        // })->toArray();
        $syncData = collect($attributeValues)->mapWithKeys(function ($item) use ($values) {

            $selectedIds = $item['values'] ?? [];
            $preciosExtra = $item['extra_prices'] ?? []; // <-- Recibimos los precios del Hidden

            // Construimos el array final combinando ID + Nombre + Precio
            $finalData = collect($selectedIds)->map(function ($id) use ($values, $preciosExtra) {
                $valueModel = $values->get($id);
                if (!$valueModel) return null;

                return [
                    'id' => $valueModel->id,
                    'name' => $valueModel->name,
                    // Aquí fusionamos: si existe precio en el array oculto, lo usamos, si no 0
                    'extra' => isset($preciosExtra[$id]) ? (float)$preciosExtra[$id] : 0,
                ];
            })->filter()->values()->toArray();

            // Guardamos el JSON completo
            return [$item['attribute_id'] => ['values' => json_encode($finalData)]];
        })->toArray();

        $product->attributes()->sync($syncData);

        return collect($attributeValues)->mapWithKeys(fn($item) => [
            $item['attribute_id'] => array_values($item['values']),
        ])->toArray();
    }

    public function syncVariants(array $valuesByAttribute, $product): void
    {
        $combinaciones = $this->generarCombinaciones($valuesByAttribute);
        $existingVariants = Variant::where('product_id', $product->id)->with('values:id')->get();

        foreach ($existingVariants as $variant) {
            $variantValueIds = $variant->values->pluck('id')->sort()->values()->toArray();
            $key = implode('-', $variantValueIds);

            $existsInNew = false;
            foreach ($combinaciones as $combo) {
                sort($combo);
                if (implode('-', $combo) === $key) {
                    $existsInNew = true;
                    break;
                }
            }

            $variant->status = $existsInNew ? 'activo' : 'archivado';
            $variant->save();
        }

        foreach ($combinaciones as $combo) {
            sort($combo);
            $key = implode('-', $combo);
            $exists = $existingVariants->first(fn($v) => implode('-', $v->values->pluck('id')->sort()->values()->toArray()) === $key);
            if (!$exists) {
                $variant = Variant::create([
                    'product_id' => $product->id,
                    'status' => 'activo',
                ]);
                $variant->values()->sync($combo);
                do {
                    $code = "producto_{$variant->id}";
                } while (
                    Variant::where('internal_code', $code)->exists()
                );
                $variant->update([
                    'internal_code' => $code,
                ]);
            }
        }
    }

    private function generarCombinaciones(array $valuesByAttribute): array
    {
        if (empty($valuesByAttribute)) return [];
        $listas = array_values($valuesByAttribute);
        $combinaciones = [];
        $recursiva = function ($nivel, $actual) use (&$recursiva, &$combinaciones, $listas) {
            if ($nivel === count($listas)) {
                $combinaciones[] = $actual;
                return;
            }
            foreach ($listas[$nivel] as $valueId) {
                $nuevo = $actual;
                $nuevo[] = $valueId;
                $recursiva($nivel + 1, $nuevo);
            }
        };
        $recursiva(0, []);
        return $combinaciones;
    }
}
