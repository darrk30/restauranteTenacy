<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'restaurant_id',
        'product_id',
        'variant_id',
        'product_name',
        'price',
        'cantidad',
        'subTotal',
        'cortesia',
        'status',
        'notes',
        'fecha_envio_cocina',
        'fecha_listo',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

         /*
    |--------------------------------------------------------------------------
    | Scope para multitenant (Filament)
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void
    {
        // Filtrar por tenant automáticamente
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        // Asignar tenant automáticamente al crear
        static::creating(function ($record) {
            if (filament()->getTenant()) {
                $record->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
