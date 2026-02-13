<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    protected $fillable = [
        'restaurant_id',
        'name',
        'code',
        'status',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function sesionCashRegisters()
    {
        return $this->hasMany(SessionCashRegister::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($cashregister) {
            if (filament()->getTenant()) {
                $cashregister->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}

