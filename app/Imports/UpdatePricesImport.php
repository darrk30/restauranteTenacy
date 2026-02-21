<?php

namespace App\Imports;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\Value;
use App\Models\Variant;
use App\Services\ProductService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UpdatePricesImport implements ToCollection, WithHeadingRow
{
    public int $productosActualizados = 0;
    public int $productosNoEncontrados = 0;
    public array $erroresDetalle = [];

    public function collection(Collection $rows)
    {
        DB::beginTransaction();
        try {
            $tenantId = filament()->getTenant()->id;
            $service = new ProductService();

            $parseExcelList = function ($value) {
                if (is_null($value) || trim((string)$value) === '') return [];
                return array_map('trim', explode(',', (string)$value));
            };

            foreach ($rows as $row) {
                // Obtenemos las columnas del Excel (Excel las convierte a minúsculas con guiones bajos)
                $codigo = trim((string)($row['codigo'] ?? ''));
                $nombre = trim((string)($row['nombre'] ?? ''));
                $precioBase = $row['precio_base'] ?? null;

                if (empty($nombre) && empty($codigo)) continue;

                $product = null;

                // 🟢 1. BÚSQUEDA POR CÓDIGO (Busca en la tabla variantes)
                if (!empty($codigo)) {
                    $variant = Variant::where('restaurant_id', $tenantId)
                        ->where(function($q) use ($codigo) {
                            $q->where('codigo_barras', $codigo)
                              ->orWhere('internal_code', $codigo);
                        })->first();

                    if ($variant) {
                        $product = $variant->product;
                    }
                }

                // 🟢 2. BÚSQUEDA POR NOMBRE (Si no hay código o no lo encontró)
                if (!$product && !empty($nombre)) {
                    $product = Product::where('restaurant_id', $tenantId)
                        ->where('name', $nombre)->first();
                }

                // Si definitivamente no existe, lo omitimos y anotamos el error
                if (!$product) {
                    $this->productosNoEncontrados++;
                    $identificador = !empty($codigo) ? $codigo : $nombre;
                    $this->erroresDetalle[] = "[$identificador] No encontrado";
                    continue;
                }

                // 🟢 3. ACTUALIZAR PRECIO BASE
                if (is_numeric($precioBase)) {
                    $product->update(['price' => (float)$precioBase]);
                }

                // 🟢 4. ACTUALIZAR VARIANTES Y PRECIOS EXTRA
                $attrName = $row['atributo'] ?? null;
                $listValues = $parseExcelList($row['valores'] ?? '');
                $listPricesExtra = $parseExcelList($row['precios_extra'] ?? '');

                if (!empty($attrName) && count($listValues) > 0) {
                    // Busca el atributo exactamente por su nombre
                    $attribute = Attribute::where('restaurant_id', $tenantId)
                        ->where('name', trim($attrName))
                        ->first();

                    if ($attribute) {
                        $valIds = [];
                        $preciosExtraMapeados = [];

                        foreach ($listValues as $idx => $vName) {
                            // Busca el valor exactamente por su nombre
                            $value = Value::where('attribute_id', $attribute->id)
                                ->where('restaurant_id', $tenantId)
                                ->where('name', $vName)->first();

                            if ($value) {
                                $valIds[] = $value->id;
                                $pExtra = isset($listPricesExtra[$idx]) && is_numeric($listPricesExtra[$idx]) ? (float)$listPricesExtra[$idx] : 0;
                                $preciosExtraMapeados[$value->id] = $pExtra;
                            }
                        }

                        // Si encontró los valores, sincroniza todo usando tu ProductService
                        if (count($valIds) > 0) {
                            $valuesByAttribute = $service->syncProductAttributes([[
                                'attribute_id' => $attribute->id,
                                'values'       => $valIds,
                                'extra_prices' => $preciosExtraMapeados,
                            ]], $product);
                            $service->syncVariants($valuesByAttribute, $product);
                        }
                    }
                }

                $this->productosActualizados++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}