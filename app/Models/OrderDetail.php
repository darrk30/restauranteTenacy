<?php

namespace App\Models;

use App\Enums\StatusPedido; // Asegúrate de importar tu Enum
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
        'item_type',
        'promotion_id',
        'product_name',
        'price',
        'cantidad',
        'subTotal',
        'cortesia',
        'status',
        'notes',
        'user_actualiza_id',
        'user_id',
        'fecha_envio_cocina',
        'fecha_listo',
    ];

    // ... (Tus relaciones order, restaurant, etc. se mantienen igual) ...
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
    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userActualiza()
    {
        return $this->belongsTo(User::class, 'user_actualiza_id');
    }


    /*
    |--------------------------------------------------------------------------
    | Lógica de Eventos (Booted) - VERSIÓN CORREGIDA
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Lógica de Eventos (Booted) - FINAL
    |--------------------------------------------------------------------------
    */
    // ============================================
    // CAMBIO EN OrderDetail booted(): DESACTIVAR UPDATE
    // ============================================

    protected static function booted(): void
    {
        // 1. EVENTO CREATED (Sumar al crear)
        // static::created(function ($detail) {
        //     if ($detail->promotion_id) {
        //         $promo = \App\Models\Promotion::find($detail->promotion_id);

        //         if ($promo && method_exists($promo, 'tieneLimiteDiario') && $promo->tieneLimiteDiario()) {
        //             $ahora = now();
        //             $esMismoDia = $promo->fecha_ultima_venta && $promo->fecha_ultima_venta->isSameDay($ahora);

        //             if (!$esMismoDia) {
        //                 $promo->update([
        //                     'ventas_diarias_actuales' => $detail->cantidad,
        //                     'fecha_ultima_venta' => $ahora
        //                 ]);
        //             } else {
        //                 $promo->increment('ventas_diarias_actuales', $detail->cantidad);
        //             }
        //         }
        //     }
        // });

        // 2. EVENTO UPDATED - DESACTIVADO AQUÍ
        // 🔴 NO HACEMOS NADA: actualizarOrden() controla todo sincrónico
        // Si necesitas este evento para OTROS contextos (API, edición rápida, etc),
        // agrégalo SOLO en esos contextos de forma explícita.

        // 3. EVENTO DELETED (Restar al Eliminar registro)
        // static::deleted(function ($detail) {
        //     $enumCancelado = \App\Enums\StatusPedido::Cancelado;
        //     $valorCancelado = $enumCancelado instanceof \BackedEnum ? $enumCancelado->value : $enumCancelado;
        //     $statusFinal = $detail->status instanceof \BackedEnum ? $detail->status->value : $detail->status;

        //     if ($statusFinal != $valorCancelado && $detail->promotion_id) {
        //         $promo = \App\Models\Promotion::find($detail->promotion_id);

        //         if (
        //             $promo &&
        //             (method_exists($promo, 'tieneLimiteDiario') && $promo->tieneLimiteDiario()) &&
        //             ($promo->fecha_ultima_venta && $promo->fecha_ultima_venta->isSameDay(now()))
        //         ) {
        //             $nuevoTotal = max(0, $promo->ventas_diarias_actuales - $detail->cantidad);
        //             $promo->update(['ventas_diarias_actuales' => $nuevoTotal]);
        //         }
        //     }
        // });

        // Scopes globales...
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (function_exists('filament') && filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });
        static::creating(function ($record) {
            if (function_exists('filament') && filament()->getTenant()) {
                $record->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
