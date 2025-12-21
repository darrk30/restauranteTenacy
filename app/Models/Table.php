<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Table extends Model
{
    protected $fillable = ['name', 'status', 'asientos', 'floor_id', 'restaurant_id', 'order_id'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
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
