<?php

namespace App\Models;

use App\Enums\Comprobantes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'tipo_documento',
        'serie',
        'numero',
        'fecha_compra',
        'moneda',
        'descuento',
        'subtotal',
        'igv',
        'total',
        'costo_envio',
        'saldo',
        'estado_despacho',
        'estado_pago',
        'estado_comprobante',
        'observaciones',
        'restaurant_id',
    ];
    protected $casts = [
        'tipo_documento' => Comprobantes::class,
        'fecha_compra' => 'datetime', // 🟢 Esto convierte el string a un objeto Carbon automáticamente
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethodPurchase::class);
    }

    public function details()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user()
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

        static::creating(function ($purchase) {
            if (filament()->getTenant()) {
                $purchase->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
