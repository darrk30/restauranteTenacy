<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductResource;
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
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('importar')
                ->label('Importar')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('archivo')
                        ->label('Archivo Excel (.xlsx o .csv)')
                        ->helperText('Asegúrate de que las columnas no se muevan y que el formato sea correcto. Puedes descargar un formato de ejemplo.')
                        ->disk('public')
                        ->directory('imports')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'application/csv'
                        ])
                        // 🟢 AQUÍ AGREGAMOS EL BOTÓN DE DESCARGA
                        ->hintAction(
                            \Filament\Forms\Components\Actions\Action::make('descargar_formato')
                                ->label('Descargar formato de ejemplo')
                                ->icon('heroicon-o-document-arrow-down')
                                ->url(fn() => asset('assets/formato_productos.xlsx')) // Apunta a public/assets/...
                                ->openUrlInNewTab()
                        ),
                ])
                ->action(function (array $data) {
                    $filePath = Storage::disk('public')->path($data['archivo']);

                    try {
                        $rows = Excel::toArray([], $filePath)[0];
                        array_shift($rows); // Eliminar cabecera

                        DB::beginTransaction();
                        $tenantId = filament()->getTenant()->id;
                        $service = new ProductService();

                        // Helper para listas separadas por comas "10, 5" -> [10, 5]
                        $parseExcelList = function ($value) {
                            if (is_null($value) || trim((string)$value) === '') return [];
                            return array_map('trim', explode(',', (string)$value));
                        };

                        foreach ($rows as $row) {
                            $nombre = $row[0] ?? null;
                            if (empty($nombre)) continue;

                            // 🟢 1. ÁREA DE PRODUCCIÓN (Col 4)
                            // Busca si existe por nombre, si no crea.
                            $productionId = null;
                            if (!empty($row[4])) {
                                $production = Production::firstOrCreate(
                                    ['name' => trim($row[4]), 'restaurant_id' => $tenantId]
                                );
                                $productionId = $production->id;
                            }

                            // 🟢 2. MARCA (Col 5)
                            // Busca si existe por nombre, si no crea.
                            $brandId = null;
                            if (!empty($row[5])) {
                                $brand = Brand::firstOrCreate(
                                    ['name' => trim($row[5]), 'restaurant_id' => $tenantId]
                                );
                                $brandId = $brand->id;
                            }

                            // 🟢 3. PRODUCTO
                            $product = Product::updateOrCreate(
                                ['name' => $nombre, 'restaurant_id' => $tenantId],
                                [
                                    'unit_id'         => Unit::where('code', $row[1])->value('id'),
                                    'type'            => $row[2],
                                    'price'           => (float)($row[3] ?? 0),
                                    'production_id'   => $productionId, // Asignamos Area
                                    'brand_id'        => $brandId,      // Asignamos Marca
                                    // Ajustamos indices según imagen nueva
                                    'status'          => ($row[7] ?? 'TRUE') === 'TRUE' ? 'activo' : 'inactivo',
                                    'cortesia'        => ($row[8] ?? 'FALSE') === 'TRUE',
                                    'visible'         => ($row[9] ?? 'FALSE') === 'TRUE',
                                    'receta'          => ($row[10] ?? 'FALSE') === 'TRUE',
                                    'control_stock'   => ($row[11] ?? 'FALSE') === 'TRUE',
                                    'venta_sin_stock' => ($row[12] ?? 'FALSE') === 'TRUE',
                                    'slug'            => str($nombre . '-' . $tenantId)->slug(),
                                ]
                            );

                            // 4. CATEGORÍAS (Col 6)
                            if (!empty($row[6])) {
                                foreach ($parseExcelList($row[6]) as $catName) {
                                    $category = Category::firstOrCreate(['name' => $catName, 'restaurant_id' => $tenantId]);
                                    $product->categories()->syncWithoutDetaching([$category->id]);
                                }
                            }

                            // 🟢 5. VARIANTES Y ATRIBUTOS
                            $attrName        = $row[13] ?? null;
                            $listValues      = $parseExcelList($row[14] ?? '');
                            $listPricesExtra = $parseExcelList($row[15] ?? '');
                            $listCodBarras   = $parseExcelList($row[16] ?? '');
                            $listCodInterno  = $parseExcelList($row[17] ?? '');
                            $listCostos      = $parseExcelList($row[18] ?? '');
                            $listStocks      = $parseExcelList($row[19] ?? '');

                            $hasAttributes = !empty($attrName) && count($listValues) > 0;

                            if ($hasAttributes) {
                                // CASO A: CON VARIANTES
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

                                // Asignar Stock/Costo a cada variante específica
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
                                // CASO B: VARIANTE ÚNICA
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
                        Storage::disk('public')->delete($data['archivo']);
                        Notification::make()->title('Importación completada con éxito')->success()->send();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    /**
     * Helper para actualizar variante y crear/actualizar stock
     */
    protected function procesarVariante($variant, $product, $tenantId, $costo, $stockInicial, $codBarra, $codInterno)
    {
        // 1. Actualizar datos base
        $dataUpdate = ['costo' => $costo];
        if (!empty($codBarra)) $dataUpdate['codigo_barras'] = $codBarra;
        if (!empty($codInterno)) $dataUpdate['internal_code'] = $codInterno;
        $variant->update($dataUpdate);

        // 2. Gestionar Stock Inicial (UpdateOrCreate para forzar la creación o corrección)
        if ($stockInicial > 0) {

            // a) Crear o Actualizar registro en WarehouseStock
            $stock = WarehouseStock::updateOrCreate(
                [
                    'variant_id'    => $variant->id,
                    'restaurant_id' => $tenantId
                ],
                [
                    'stock_real'       => $stockInicial,
                    'stock_reserva'    => $stockInicial,
                    'costo_promedio'   => $costo,
                    'valor_inventario' => $stockInicial * $costo,
                ]
            );

            // b) Crear registro en Kardex SOLO si no existe uno de 'Stock Inicial' para esta variante
            // Esto evita duplicar el ingreso si se corre el importador dos veces
            $existeKardex = $variant->kardexes()
                ->where('comprobante', 'IMPORT-INICIAL')
                ->exists();

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
