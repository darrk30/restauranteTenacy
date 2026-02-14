<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'image_path',
        'payment_condition',
        'requiere_referencia',
        'restaurant_id',
        'status',
    ];

    public function paymentPurchases()
    {
        return $this->hasMany(PaymentMethodPurchase::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function cashRegisterMovements()
    {
        return $this->hasMany(CashRegisterMovement::class);
    }

    public function cierreCajaDetalles()
    {
        return $this->hasMany(CierreCajaDetalle::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($paymentMethod) {
            if (app()->has('bypass_tenant_scope')) {
                return; // omitir asignación
            }
            if (filament()->getTenant()) {
                $paymentMethod->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
