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
    ];

    public function stocks()
    {
        return $this->hasMany(WarehouseStock::class);
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
