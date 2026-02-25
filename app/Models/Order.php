<?php

namespace App\Models;

use App\Enums\statusPedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'table_id',
        'code',
        'canal',
        'web',
        'client_id',
        'delivery_id',
        'nombre_delivery',
        'nombre_cliente',
        'telefono',
        'direccion',
        'status',
        'status_llevar_delivery',
        'subtotal',
        'igv',
        'total',
        'notas',
        'fecha_pedido',
        'user_id',
        'user_actualiza_id',
        'payment_method_id',
    ];

    protected $casts = [
        'status' => statusPedido::class,
        'web' => 'boolean',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userActualiza()
    {
        return $this->belongsTo(User::class, 'user_actualiza_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
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
