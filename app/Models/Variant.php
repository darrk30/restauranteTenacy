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
        'codigo_barras',
        'internal_code',
        'stock_inicial',
        'product_id',
        'status',
        'restaurant_id'
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

    public function promotionproducts()
    {
        return $this->hasMany(PromotionProduct::class);
    }

    public function stocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function ajustesitems()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function kardexes()
    {
        return $this->hasMany(Kardex::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function getFullNameAttribute()
    {
        $values = $this->values->map(function ($value) {
            return $value->attribute->name . ': ' . $value->name;
        })->implode(', ');

        return ($values ? " ({$values})" : ' (Unica)');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($variant) {
            if (filament()->getTenant()) {
                $variant->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
