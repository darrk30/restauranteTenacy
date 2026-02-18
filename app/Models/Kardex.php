<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Kardex extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'restaurant_id',
        'tipo_movimiento',
        'comprobante',
        'modelo_texto',
        'modelo_id',
        'modelo_type',
        'cantidad',
        'costo_unitario',
        'saldo_valorizado',
        'stock_restante',
    ];

    // Producto
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Variante (si existe)
    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    // Tenant
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    // Relación polimórfica (purchase, adjustment, sale, etc.)
    public function modelo()
    {
        return $this->morphTo();
    }


    /*
    |--------------------------------------------------------------------------
    | Scope para multitenant (Filament)
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void
    {
        // Filtrar por tenant automáticamente
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        // Asignar tenant automáticamente al crear
        static::creating(function ($record) {
            if (filament()->getTenant()) {
                $record->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
