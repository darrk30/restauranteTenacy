<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'restaurant_id',
        'order_id',
        'client_id',
        'user_id',
        'nombre_cliente',
        'tipo_documento',
        'numero_documento',
        'tipo_comprobante',
        'serie',
        'correlativo',
        'monto_descuento',
        'op_gravada',
        'monto_igv',
        'total',
        'status',
        'notas',
        'fecha_emision',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($sale) {
            if (filament()->getTenant()) {
                $sale->restaurant_id = filament()->getTenant()->id;
            }
        });
    }

}
