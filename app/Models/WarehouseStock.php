<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStock extends Model
{
    protected $fillable = [
        'variant_id',
        'stock_real',
        'stock_reserva',
        'costo_promedio',
        'valor_inventario',
        'min_stock',
    ];

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($production) {
            if (filament()->getTenant()) {
                $production->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
