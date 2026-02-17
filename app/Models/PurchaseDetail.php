<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'variant_id',
        'subtotal',
        'unit_id',
        'cantidad',
        'costo',
        'restaurant_id',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
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

        static::creating(function ($purchasedetail) {
            if (filament()->getTenant()) {
                $purchasedetail->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}

