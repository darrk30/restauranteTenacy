<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Floor extends Model
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

    public function tables()
    {
        return $this->hasMany(Table::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($floor) {
            if (filament()->getTenant()) {
                $floor->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
