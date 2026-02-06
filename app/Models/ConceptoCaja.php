<?php

namespace App\Models;

use App\Enums\TipoEgreso;
use App\Observers\ConceptoCajaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([ConceptoCajaObserver::class])]
class ConceptoCaja extends Model
{
    protected $fillable = [
        'restaurant_id',
        'session_cash_register_id',
        'usuario_id',
        'personal_id',
        'tipo_movimiento',
        'categoria',
        'monto',
        'motivo',
        'estado',
        'persona_externa',
    ];

    protected $casts = [
        'categoria' => TipoEgreso::class,
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function sessionCashRegister()
    {
        return $this->belongsTo(SessionCashRegister::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function personal()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($conceptoCajas) {
            if (filament()->getTenant()) {
                $conceptoCajas->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
