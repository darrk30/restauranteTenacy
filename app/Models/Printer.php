<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Printer extends Model
{
    protected $fillable = ['name', 'status'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function floors()
    {
        return $this->hasMany(Floor::class);
    }

    public function productions()
    {
        return $this->hasMany(Production::class);
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
