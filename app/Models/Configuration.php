<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Configuration extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',

        // Impresión Directa
        'impresion_directa_precuenta',
        'impresion_directa_comprobante',
        'impresion_directa_comanda',

        // Impresión con Modal
        'mostrar_modal_impresion_comanda',
        'mostrar_modal_impresion_precuenta',
        'mostrar_modal_impresion_comprobante',

        // KDS
        'mostrar_pantalla_cocina',

        // Web
        'guardar_pedidos_web',
        'habilitar_delivery_web',
        'habilitar_recojo_web',

        // Facturación
        'precios_incluyen_impuesto',
        'porcentaje_impuesto',
        'envio_boletas',
        'envio_facturas',

        'production',
        'api_token',
        'api_url',
        'sol_user',
        'sol_pass',
    ];

    protected $casts = [
        'impresion_directa_precuenta'         => 'boolean',
        'impresion_directa_comprobante'       => 'boolean',
        'impresion_directa_comanda'           => 'boolean',

        'mostrar_modal_impresion_comanda'     => 'boolean',
        'mostrar_modal_impresion_precuenta'   => 'boolean',
        'mostrar_modal_impresion_comprobante' => 'boolean',

        'mostrar_pantalla_cocina'             => 'boolean',

        'guardar_pedidos_web'                 => 'boolean',
        'habilitar_delivery_web'              => 'boolean',
        'habilitar_recojo_web'                => 'boolean',

        'precios_incluyen_impuesto'           => 'boolean',
        'porcentaje_impuesto'                 => 'decimal:2',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    protected static function booted()
    {
        static::saved(function ($configuration) {
            $cacheKey = "tenant_{$configuration->restaurant_id}_config";
            Cache::forget($cacheKey);
        });
    }
}
