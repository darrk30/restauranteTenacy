<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DocumentoService
{
    public static function consultarRuc(string $ruc): ?array
    {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6ImtyaXZlcmFyb2phczQ0QGdtYWlsLmNvbSJ9.vdnwHkb3e0nx9VtKCN7U-FGdMzQdn_K9M_I_hdkzG-Q';
        $url = "https://dniruc.apisperu.com/api/v1/ruc/{$ruc}?token={$token}";

        $response = Http::get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    public static function consultarDni(string $dni): ?array
    {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6ImtyaXZlcmFyb2phczQ0QGdtYWlsLmNvbSJ9.vdnwHkb3e0nx9VtKCN7U-FGdMzQdn_K9M_I_hdkzG-Q';
        $url = "https://dniruc.apisperu.com/api/v1/dni/{$dni}?token={$token}";

        $response = Http::get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }
}
