<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BarcodeLookupService
{
    /**
     * Consulta OpenFoodFacts por código de barras.
     */
    public static function lookup(string $barcode): ?array
    {
        // Endpoint oficial
        $url = "https://world.openfoodfacts.org/api/v0/product/{$barcode}.json";

        $response = Http::get($url);

        // Si falla la conexión
        if (!$response->successful()) {
            return null;
        }

        $json = $response->json();

        // Si no existe el producto
        if (!isset($json['status']) || $json['status'] != 1) {
            return null;
        }

        $product = $json['product'] ?? [];

        return self::extractBasicData($json);
    }

    /**
     * Extraer SOLO los datos necesarios.
     */
    private static function extractBasicData(array $json): array
    {
        $product = $json['product'] ?? [];

        return [
            'code'     => $json['code'] ?? null,
            'brand'    => $product['brands'] ?? null,
            'category' => self::cleanCategory($product['categories_tags'][0] ?? null),
            'name'     => $product['product_name'] 
                            ?? ($product['brands'] ?? '') . ' ' . ($product['categories_tags'][0] ?? ''),
        ];
    }

    /**
     * Limpia categorías tipo "en:waters" → "Waters"
     */
    private static function cleanCategory(?string $category): ?string
    {
        if (!$category) return null;

        // Quitar "en:" o cualquier otro prefijo
        $clean = preg_replace('/^[a-z]{2}:/i', '', $category);

        // Capitalizar
        return ucfirst($clean);
    }
}
