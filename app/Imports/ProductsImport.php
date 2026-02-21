<?php

namespace App\Imports;

use App\Enums\TipoProducto;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Production;
use App\Models\Unit;
use App\Models\Value;
use App\Models\Variant;
use App\Models\WarehouseStock;
use App\Services\ProductService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToCollection, WithHeadingRow
{
    public int $productosNuevos = 0;

    // 🟢 Ya no hay "actualizados". Todo va a omitidos si falla o ya existe.
    public int $productosOmitidos = 0;
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

            foreach ($rows as $index => $row) {
                $nombre = $row->values()->get(0);

                // Si la fila no tiene nombre, la ignoramos silenciosamente
                if (empty($nombre)) continue;

                // 🟢 1. VERIFICAR SI EL PRODUCTO YA EXISTE
                $existeProducto = Product::where('name', $nombre)
                    ->where('restaurant_id', $tenantId)
                    ->exists();

                if ($existeProducto) {
                    $this->productosOmitidos++;
                    $this->erroresDetalle[] = "[$nombre] Ya está registrado";
                    continue; // 🔴 Saltamos esta fila completamente
                }

                // 🟢 2. VALIDACIÓN ESTRICTA DEL ENUM
                $tipoRaw = $row->values()->get(2);
                $tipoValido = TipoProducto::tryFrom($tipoRaw);

                if (!$tipoValido) {
                    $this->productosOmitidos++;
                    $this->erroresDetalle[] = "[$nombre] Tipo '$tipoRaw' no válido";
                    continue; // 🔴 Saltamos esta fila
                }

                $productionId = null;
                $areaProduccion = $row->values()->get(4);
                if (!empty($areaProduccion)) {
                    $production = Production::firstOrCreate(['name' => trim($areaProduccion), 'restaurant_id' => $tenantId]);
                    $productionId = $production->id;
                }

                $brandId = null;
                $marcaStr = $row->values()->get(5);
                if (!empty($marcaStr)) {
                    $brand = Brand::firstOrCreate(['name' => trim($marcaStr), 'restaurant_id' => $tenantId]);
                    $brandId = $brand->id;
                }

                // 🟢 3. CREAR EL PRODUCTO (Cambiamos updateOrCreate por create)
                $product = Product::create([
                    'name'            => $nombre,
                    'restaurant_id'   => $tenantId,
                    'unit_id'         => Unit::where('code', $row->values()->get(1))->value('id'),
                    'type'            => $tipoValido->value,
                    'price'           => (float)($row->values()->get(3) ?? 0),
                    'production_id'   => $productionId,
                    'brand_id'        => $brandId,
                    'status'          => ($row->values()->get(7) ?? 'TRUE') === 'TRUE' ? 'activo' : 'inactivo',
                    'cortesia'        => ($row->values()->get(8) ?? 'FALSE') === 'TRUE',
                    'visible'         => ($row->values()->get(9) ?? 'FALSE') === 'TRUE',
                    'receta'          => ($row->values()->get(10) ?? 'FALSE') === 'TRUE',
                    'control_stock'   => ($row->values()->get(11) ?? 'FALSE') === 'TRUE',
                    'venta_sin_stock' => ($row->values()->get(12) ?? 'FALSE') === 'TRUE',
                    'slug'            => str($nombre . '-' . $tenantId)->slug(),
                ]);

                // Como solo llegamos aquí si es nuevo, aumentamos el contador directo
                $this->productosNuevos++;

                // --- EL RESTO QUEDA EXACTAMENTE IGUAL ---
                $categoriasStr = $row->values()->get(6);
                if (!empty($categoriasStr)) {
                    foreach ($parseExcelList($categoriasStr) as $catName) {
                        $category = Category::firstOrCreate(['name' => $catName, 'restaurant_id' => $tenantId]);
                        $product->categories()->syncWithoutDetaching([$category->id]);
                    }
                }

                $attrName        = $row->values()->get(13);
                $listValues      = $parseExcelList($row->values()->get(14) ?? '');
                $listPricesExtra = $parseExcelList($row->values()->get(15) ?? '');
                $listCodBarras   = $parseExcelList($row->values()->get(16) ?? '');
                $listCodInterno  = $parseExcelList($row->values()->get(17) ?? '');
                $listCostos      = $parseExcelList($row->values()->get(18) ?? '');
                $listStocks      = $parseExcelList($row->values()->get(19) ?? '');

                $hasAttributes = !empty($attrName) && count($listValues) > 0;

                if ($hasAttributes) {
                    $attribute = Attribute::firstOrCreate(['name' => trim($attrName), 'restaurant_id' => $tenantId], ['tipo' => 'seleccionar']);
                    $valIds = [];
                    $preciosExtraMapeados = [];

                    foreach ($listValues as $idx => $vName) {
                        $value = Value::firstOrCreate(['name' => $vName, 'attribute_id' => $attribute->id, 'restaurant_id' => $tenantId]);
                        $valIds[] = $value->id;
                        $pExtra = isset($listPricesExtra[$idx]) && is_numeric($listPricesExtra[$idx]) ? (float)$listPricesExtra[$idx] : 0;
                        $preciosExtraMapeados[$value->id] = $pExtra;
                    }

                    $valuesByAttribute = $service->syncProductAttributes([[
                        'attribute_id' => $attribute->id,
                        'values'       => $valIds,
                        'extra_prices' => $preciosExtraMapeados,
                    ]], $product);
                    $service->syncVariants($valuesByAttribute, $product);

                    foreach ($listValues as $idx => $vName) {
                        $variant = Variant::where('product_id', $product->id)
                            ->whereHas('values', fn($q) => $q->where('name', $vName))
                            ->first();

                        if ($variant) {
                            $costoVar  = isset($listCostos[$idx]) && is_numeric($listCostos[$idx]) ? (float)$listCostos[$idx] : 0;
                            $stockVar  = isset($listStocks[$idx]) && is_numeric($listStocks[$idx]) ? (float)$listStocks[$idx] : 0;
                            $codBarVar = $listCodBarras[$idx] ?? null;
                            $codIntVar = $listCodInterno[$idx] ?? null;

                            $this->procesarVariante($variant, $product, $tenantId, $costoVar, $stockVar, $codBarVar, $codIntVar);
                        }
                    }
                } else {
                    $service->handleAfterCreate($product, []);
                    $variant = $product->variants()->first();

                    if ($variant) {
                        $costoVar  = isset($listCostos[0]) && is_numeric($listCostos[0]) ? (float)$listCostos[0] : 0;
                        $stockVar  = isset($listStocks[0]) && is_numeric($listStocks[0]) ? (float)$listStocks[0] : 0;
                        $codBarVar = $listCodBarras[0] ?? null;
                        $codIntVar = $listCodInterno[0] ?? null;

                        $this->procesarVariante($variant, $product, $tenantId, $costoVar, $stockVar, $codBarVar, $codIntVar);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function procesarVariante($variant, $product, $tenantId, $costo, $stockInicial, $codBarra, $codInterno)
    {
        $dataUpdate = ['costo' => $costo];
        if (!empty($codBarra)) $dataUpdate['codigo_barras'] = $codBarra;
        if (!empty($codInterno)) $dataUpdate['internal_code'] = $codInterno;
        $variant->update($dataUpdate);

        if ($stockInicial > 0) {
            $stock = WarehouseStock::updateOrCreate(
                ['variant_id' => $variant->id, 'restaurant_id' => $tenantId],
                [
                    'stock_real'       => $stockInicial,
                    'stock_reserva'    => $stockInicial,
                    'costo_promedio'   => $costo,
                    'valor_inventario' => $stockInicial * $costo,
                ]
            );

            $existeKardex = $variant->kardexes()->where('comprobante', 'IMPORT-INICIAL')->exists();

            if (!$existeKardex) {
                $variant->kardexes()->create([
                    'product_id'       => $product->id,
                    'variant_id'       => $variant->id,
                    'restaurant_id'    => $tenantId,
                    'tipo_movimiento'  => 'Stock Inicial',
                    'comprobante'      => 'IMPORT-INICIAL',
                    'cantidad'         => $stockInicial,
                    'costo_unitario'   => $costo,
                    'saldo_valorizado' => $stock->valor_inventario,
                    'stock_restante'   => $stockInicial,
                    'modelo_type'      => get_class($variant),
                    'modelo_id'        => $variant->id,
                ]);
            }

            $variant->update(['stock_inicial' => true]);
        }
    }
}
