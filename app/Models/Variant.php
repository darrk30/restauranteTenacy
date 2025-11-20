<?php

namespace App\Models;

use App\Observers\VariantObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy([VariantObserver::class])]
class Variant extends Model
{
    protected $fillable = [
        'image_path',
        'sku',
        'internal_code',
        'extra_price',
        'stock_inicial',
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

    public function stocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function getFullNameAttribute()
    {
        $values = $this->values->map(function ($value) {
            return $value->attribute->name . ': ' . $value->name;
        })->implode(', ');

        return $this->product->name . ($values ? " ({$values})" : '');
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
