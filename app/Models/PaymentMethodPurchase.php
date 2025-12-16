<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodPurchase extends Model
{
    protected $table = 'payment_method_purchase';

    protected $fillable = [
        'purchase_id',
        'payment_method_id',
        'monto',
        'referencia',
        'restaurant_id',
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($PaymentMethodPurchase) {
            if (filament()->getTenant()) {
                $PaymentMethodPurchase->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
