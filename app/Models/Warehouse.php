<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'code',
        'direccion',
        'order',
        'restaurant_id',
    ];

    public function stocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function ajustesitems()
    {
        return $this->hasMany(StockAdjustment::class);
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

        static::creating(function ($model) {
            if (app()->has('bypass_tenant_scope')) {
                return; // omitir asignación
            }

            if (filament()->getTenant() && ! $model->restaurant_id) {
                $model->restaurant_id = filament()->getTenant()->id;
            }
        });

    }
}
