<?php

namespace App\Models;

use App\Observers\StockAdjustmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([StockAdjustmentObserver::class])]
class StockAdjustment extends Model
{
    protected $fillable = [
        'restaurant_id',
        'user_id',
        'tipo',
        'motivo',
        'codigo',
        'status', 
    ];

    // Sucursal / Restaurante
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    // Usuario que realizó el ajuste
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
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
