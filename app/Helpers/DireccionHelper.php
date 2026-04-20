<?php

namespace App\Helpers;

class DireccionHelper
{
    /**
     * Extrae las coordenadas GPS del string de dirección.
     * Formato esperado: "Av. Example 123 [GPS: -12.089922, -77.036961]"
     */
    public static function urlMapa(string $direccion): string
    {
        if (preg_match('/\[GPS: ([-\d.]+), ([-\d.]+)\]/', $direccion, $m)) {
            return "https://www.google.com/maps?q={$m[1]},{$m[2]}";
        }

        return 'https://maps.google.com/?q=' . urlencode($direccion);
    }

    /**
     * Retorna la dirección limpia sin el bloque [GPS: ...]
     */
    public static function texto(string $direccion): string
    {
        return trim(preg_replace('/\[GPS:.*?\]/', '', $direccion));
    }
}
