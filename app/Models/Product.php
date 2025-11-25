<?php

namespace App\Models;

use App\Enums\StatusProducto;
use App\Enums\TipoProducto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image_path',
        'type',
        'production_id',
        'brand_id',
        'category_id',
        'unit_id',
        'status',
        'price',
        'control_stock',
        'venta_sin_stock',
        'cortesia',
        'visible',
        'order',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'attribute_product')
            ->withPivot('values')
            ->withTimestamps();
    }

    public function promotionproducts()
    {
        return $this->hasMany(PromotionProduct::class);
    }

    protected $casts = [
        'type' => TipoProducto::class,
        'status' => StatusProducto::class,
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($printer) {
            if (filament()->getTenant()) {
                $printer->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
