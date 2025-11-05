<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Variant extends Model
{
    protected $fillable = [
        'image_path',
        'sku',
        'internal_code',
        'extra_price',
        'stock_real',
        'stock_virtual',
        'stock_minimo',
        'venta_sin_stock',
        'product_id',
        'status'
    ];
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function values()
    {
        return $this->belongsToMany(Value::class, 'variant_value');
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
