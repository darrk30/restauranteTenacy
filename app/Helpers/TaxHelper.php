<?php

use App\Models\Restaurant;
use Filament\Facades\Filament;

if (!function_exists('get_tax_percentage')) {
    function get_tax_percentage(?int $tenantId = null): float
    {
        $restaurant = null;

        if ($tenantId) {
            $restaurant = Restaurant::find($tenantId);
        } else {
            $restaurant = Filament::getTenant();
        }

        if (!$restaurant) {
            return 18.00;
        }
        $config = $restaurant->cached_config;

        return $config ? floatval($config->porcentaje_impuesto) : 18.00;
    }
}

if (!function_exists('get_tax_divisor')) {
    function get_tax_divisor(?int $tenantId = null): float
    {
        $percentage = get_tax_percentage($tenantId);
        return 1 + ($percentage / 100);
    }
}
