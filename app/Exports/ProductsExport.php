<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return Product::with([
            'unit',
            'production',
            'brand',
            'categories',
            'attributes', // 🟢 1. Cargar atributos para tener acceso al pivote JSON
            'variants.values.attribute',
            'variants.stock'
        ])
            ->where('restaurant_id', filament()->getTenant()->id)
            ->latest()
            ->get();
    }

    public function map($product): array
    {
        // 1. Extraer categorías en un texto
        $categorias = $product->categories->pluck('name')->implode(', ');

        // 2. Extraer variantes
        $atributo = '';
        $valores = [];
        $codBarras = [];
        $codInterno = [];
        $costos = [];
        $stocks = [];
        $preciosExtra = [];

        if ($product->variants->count() > 0) {
            $firstVariant = $product->variants->first();
            if ($firstVariant && $firstVariant->values->count() > 0) {
                $atributo = $firstVariant->values->first()->attribute->name ?? '';
            }

            foreach ($product->variants as $variant) {
                $variantValue = $variant->values->first(); // Modelo Value
                $valName = $variantValue?->name;

                if ($valName) {
                    $valores[] = $valName;
                    $codBarras[] = $variant->codigo_barras ?? '';
                    $codInterno[] = $variant->internal_code ?? '';
                    $costos[] = $variant->costo ?? '0';

                    // 🟢 MAGIA CORREGIDA: Extracción a prueba de balas del JSON Pivot
                    $precioExtra = 0;
                    if ($product->attributes && $variantValue) {
                        $prodAttr = $product->attributes->where('id', $variantValue->attribute_id)->first();

                        if ($prodAttr && $prodAttr->pivot && $prodAttr->pivot->values) {

                            // 1. Forzamos a que sea un Array (si viene como texto JSON, lo decodificamos)
                            $pivotValues = $prodAttr->pivot->values;
                            if (is_string($pivotValues)) {
                                $pivotValues = json_decode($pivotValues, true);
                            }

                            // 2. Buscamos el precio extra
                            if (is_array($pivotValues)) {
                                foreach ($pivotValues as $pv) {
                                    // Validamos que coincida el ID o el Nombre por mayor seguridad
                                    if ((isset($pv['id']) && $pv['id'] == $variantValue->id) ||
                                        (isset($pv['name']) && trim($pv['name']) === trim($valName))
                                    ) {

                                        $precioExtra = $pv['extra'] ?? 0;
                                        break; // Encontramos el precio, salimos del bucle
                                    }
                                }
                            }
                        }
                    }
                    $preciosExtra[] = $precioExtra;

                    // 🟢 Stock de esta variante
                    $stock = $variant->stock;
                    $stocks[] = $stock ? $stock->stock_real : '0';
                }
            }
        }

        $boolToStr = fn($val) => $val ? 'TRUE' : 'FALSE';

        return [
            $product->name,
            $product->unit?->code ?? 'NIU',
            $product->type?->value,
            $product->price,
            $product->production?->name ?? '',
            $product->brand?->name ?? '',
            $categorias,
            $product->status?->value,
            $boolToStr($product->cortesia),
            $boolToStr($product->visible),
            $boolToStr($product->receta),
            $boolToStr($product->control_stock),
            $boolToStr($product->venta_sin_stock),
            $atributo,
            implode(', ', $valores),
            implode(', ', $preciosExtra), // 🟢 Ahora sí imprimirá "2, 0" o lo que tenga la BD
            implode(', ', $codBarras),
            implode(', ', $codInterno),
            implode(', ', $costos),
            implode(', ', $stocks),
        ];
    }

    public function headings(): array
    {
        return [
            'NOMBRE',
            'UNIDAD',
            'TIPO',
            'PRECIO_BASE',
            'AREA_PRODUCCION',
            'MARCA',
            'CATEGORIAS',
            'ESTADO',
            'CORTESIA',
            'VISIBLE',
            'RECETA',
            'CONTROL_STOCK',
            'VENTA_SIN_STOCK',
            'ATRIBUTO',
            'VALORES',
            'PRECIOS_EXTRA',
            'COD_BARRAS',
            'COD_INTERNO',
            'COSTOS',
            'STOCKS'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => '000000']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFF00'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000'],
                    ],
                ],
            ],
        ];
    }
}
