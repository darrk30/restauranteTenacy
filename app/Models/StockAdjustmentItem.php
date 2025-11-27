<?php

namespace App\Models;

use App\Observers\StockAdjustmentItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([StockAdjustmentItemObserver::class])]
class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'variant_id',
        'product_id',
        'unit_id',
        'cantidad',
        'restaurant_id',
    ];

    // Ajuste principal
    public function adjustment()
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    // Variante del producto
    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Unidad seleccionada por el usuario (Ej: Caja, Paquete, Unidad)
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    // Restaurante
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

        static::creating(function ($table) {
            if (app()->has('bypass_tenant_scope')) {
                return; // omitir asignación
            }
            if (filament()->getTenant()) {
                $table->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
