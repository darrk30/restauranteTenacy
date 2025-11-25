<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PromotionProduct extends Model
{
    protected $fillable = [
        'promotion_id',
        'product_id',
        'variant_id',
        'restaurant_id',
        'quantity',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Global Scope multi-tenant
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($model) {
            if (filament()->getTenant()) {
                $model->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}

