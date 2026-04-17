<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BillingSyncService
{
    public static function sync($restaurant): bool
    {
        // 1. Usamos tu atributo de caché para obtener la configuración
        $config = $restaurant->cached_config;

        // 2. Prioridad de URL: BD (Caché) > .env (Fallback)
        $baseUrl = $config->api_url ?? env('GREENTER_API_URL');
        $token = $config->api_token;

        if (!$baseUrl || !$token) {
            Log::warning("Sincronización cancelada: Falta API_URL o Token para el restaurante ID: {$restaurant->id}");
            return false;
        }

        $urlApi = rtrim($baseUrl, '/') . "/api/my-company/update";

        try {
            $requestApi = Http::withToken($token)->timeout(20);

            // Si hay un certificado guardado temporalmente en la BD (campo opcional)
            // Nota: Si moviste cert_path a Configuration, cámbialo aquí
            if (!empty($config->cert_path)) {
                $rutaFisica = Storage::disk('public')->path($config->cert_path);
                if (file_exists($rutaFisica)) {
                    $requestApi->attach('cert', file_get_contents($rutaFisica), basename($rutaFisica));
                }
            }

            $response = $requestApi->post($urlApi, [
                'ruc'              => $restaurant->ruc,
                'razon_social'     => $restaurant->name,
                'nombre_comercial' => $restaurant->name_comercial,
                'direccion'        => $restaurant->address,
                'departamento'     => $restaurant->department,
                'provincia'        => $restaurant->province,
                'distrito'         => $restaurant->district,
                'ubigeo'           => $restaurant->ubigeo, // <--- Agregado
                'telefono'         => $restaurant->phone,
                'email'            => $restaurant->email,
                'sol_user'         => $config->sol_user,
                'sol_pass'         => $config->sol_pass,
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Error en BillingSyncService: " . $e->getMessage());
            return false;
        }
    }
}
