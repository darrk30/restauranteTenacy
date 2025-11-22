<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Production extends Model
{
    protected $fillable = ['name', 'status', 'printer_id'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
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
