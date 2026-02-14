<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SessionCashRegister extends Model
{
    protected $fillable = [
        'restaurant_id',
        'cash_register_id',
        'user_id',
        'session_code',
        'cajero_closing_amount',
        'system_closing_amount',
        'difference',
        'opened_at',
        'closed_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashRegisterMovements()
    {
        return $this->hasMany(CashRegisterMovement::class);
    }

    public function cierreCajaDetalles()
    {
        return $this->hasMany(CierreCajaDetalle::class);
    }

    public function conceptoCajas()
    {
        return $this->hasMany(ConceptoCaja::class);
    }


    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($seesionCashRegister) {
            if (filament()->getTenant()) {
                $seesionCashRegister->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
