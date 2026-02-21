<?php

namespace App\Models;

use App\Enums\statusPedido; // Asegúrate de importar tu Enum
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
    protected static function booted(): void
    {
        // 1. EVENTO CREATED (Sumar al crear)
        static::created(function ($detail) {
            if ($detail->promotion_id) {
                $promo = \App\Models\Promotion::find($detail->promotion_id);

                // Verificamos si existe y si tiene límite configurado
                // (Usamos el método que creamos en Promotion.php que usa ->exists() en la BD)
                if ($promo && method_exists($promo, 'tieneLimiteDiario') && $promo->tieneLimiteDiario()) {

                    $ahora = now();
                    // Comparamos fecha segura ignorando hora
                    $esMismoDia = $promo->fecha_ultima_venta && $promo->fecha_ultima_venta->isSameDay($ahora);

                    if (!$esMismoDia) {
                        // Nuevo día: Reseteamos
                        $promo->update([
                            'ventas_diarias_actuales' => $detail->cantidad,
                            'fecha_ultima_venta' => $ahora
                        ]);
                    } else {
                        // Mismo día: Sumamos
                        $promo->increment('ventas_diarias_actuales', $detail->cantidad);
                    }
                }
            }
        });

        // 2. EVENTO UPDATED (Detectar cambio a "Cancelado" y Cambios de Cantidad)
        static::updated(function ($detail) {
            if (!$detail->promotion_id) return;

            $promo = \App\Models\Promotion::find($detail->promotion_id);

            // Condiciones para abortar: No hay promo, no tiene límite, o la venta no es de HOY.
            if (
                !$promo ||
                (method_exists($promo, 'tieneLimiteDiario') && !$promo->tieneLimiteDiario()) ||
                !($promo->fecha_ultima_venta && $promo->fecha_ultima_venta->isSameDay(now()))
            ) {
                return;
            }

            // --- PREPARAR VALORES NORMALIZADOS PARA COMPARAR ---
            $enumCancelado = \App\Enums\statusPedido::Cancelado;
            $valorCancelado = $enumCancelado instanceof \BackedEnum ? $enumCancelado->value : $enumCancelado;

            // Normalizamos el estado ACTUAL del modelo (puede ser Enum o Scalar)
            $statusActual = $detail->status instanceof \BackedEnum ? $detail->status->value : $detail->status;

            // --- LÓGICA A: CAMBIO DE ESTADO A CANCELADO ---
            if ($detail->isDirty('status') && $statusActual == $valorCancelado) {

                // Normalizamos el estado ANTERIOR
                $rawAnterior = $detail->getOriginal('status');
                $statusAnterior = $rawAnterior instanceof \BackedEnum ? $rawAnterior->value : $rawAnterior;

                // Solo restamos si antes NO estaba cancelado
                if ($statusAnterior != $valorCancelado) {
                    $nuevoTotal = max(0, $promo->ventas_diarias_actuales - $detail->cantidad);
                    $promo->update(['ventas_diarias_actuales' => $nuevoTotal]);
                }
            }

            // --- LÓGICA B: CAMBIO DE CANTIDAD (Solo si NO está cancelado actualmente) ---
            elseif ($detail->isDirty('cantidad') && $statusActual != $valorCancelado) {
                $diferencia = $detail->cantidad - $detail->getOriginal('cantidad');

                if ($diferencia > 0) {
                    $promo->increment('ventas_diarias_actuales', $diferencia);
                } elseif ($diferencia < 0) {
                    $nuevoTotal = max(0, $promo->ventas_diarias_actuales + $diferencia); // $diferencia es negativa
                    $promo->update(['ventas_diarias_actuales' => $nuevoTotal]);
                }
            }
        });

        // 3. EVENTO DELETED (Restar al Eliminar registro)
        static::deleted(function ($detail) {
            $enumCancelado = \App\Enums\statusPedido::Cancelado;
            $valorCancelado = $enumCancelado instanceof \BackedEnum ? $enumCancelado->value : $enumCancelado;

            // Normalizamos el status que tenía el registro antes de morir
            $statusFinal = $detail->status instanceof \BackedEnum ? $detail->status->value : $detail->status;

            // Si se borra y NO estaba cancelado, devolvemos el cupo
            if ($statusFinal != $valorCancelado && $detail->promotion_id) {
                $promo = \App\Models\Promotion::find($detail->promotion_id);

                if (
                    $promo &&
                    (method_exists($promo, 'tieneLimiteDiario') && $promo->tieneLimiteDiario()) &&
                    ($promo->fecha_ultima_venta && $promo->fecha_ultima_venta->isSameDay(now()))
                ) {
                    $nuevoTotal = max(0, $promo->ventas_diarias_actuales - $detail->cantidad);
                    $promo->update(['ventas_diarias_actuales' => $nuevoTotal]);
                }
            }
        });

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
