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
        'nombre_cliente',
        'user_id',
        'delivery_id',
        'nombre_delivery',
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
        'canal',
        'fecha_emision',
    ];

    public $monto_especifico_filtro;

    protected $casts = [
        'fecha_emision' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function movements()
    {
        return $this->morphMany(CashRegisterMovement::class, 'referencia');
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

    public function getMontoFiltradoAttribute()
    {
        // Obtenemos el filtro directamente de la URL o del estado de Livewire
        $metodoId = request()->fingerprint()['map']['tableFilters']['payment_method_id']['value'] ?? null;

        if (!$metodoId) return $this->total;

        return $this->movements()
            ->where('payment_method_id', $metodoId)
            ->where('status', 'active')
            ->sum('monto');
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
