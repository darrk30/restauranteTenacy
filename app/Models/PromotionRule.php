<?php

namespace App\Models;

use App\Enums\PromotionRuleType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PromotionRule extends Model
{
    protected $fillable = [
        'promotion_id',
        // 'restaurant_id',
        'type',
        'key',
        'operator',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
        'type' => PromotionRuleType::class,
    ];


    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Verifica esta regla específica contra una fecha dada.
     */
    public function check(Carbon $date): bool
    {
        // Ahora comparamos contra el Enum, no contra strings sueltos

        // REGLA: Días
        if ($this->type === PromotionRuleType::Days) {
            $allowedDays = $this->value['days'] ?? [];
            return in_array($date->dayOfWeek, $allowedDays);
        }

        // REGLA: Horario
        if ($this->type === PromotionRuleType::TimeRange) {
            $start = Carbon::parse($this->value['start']);
            $end = Carbon::parse($this->value['end']);
            $checkTime = Carbon::createFromTime($date->hour, $date->minute, $date->second);
            return $checkTime->between($start, $end);
        }

        // REGLA: Límite Diario
        if ($this->type === PromotionRuleType::Limit) {
            $limit = intval($this->value['limit'] ?? 0);
            if ($limit <= 0) return true;
            $promo = $this->promotion;

            if (!$promo) return true;
            $ventasHoy = ($promo->fecha_ultima_venta && $promo->fecha_ultima_venta->isSameDay($date))
                ? $promo->ventas_diarias_actuales
                : 0;
            if ($ventasHoy >= $limit) {
                return false;
            }
            return true;
        }
        return true;
    }

    protected static function booted(): void
    {
        // Al ELIMINAR una regla...
        static::deleted(function ($rule) {
            // Verificamos si la regla borrada era de tipo Límite
            if ($rule->type === PromotionRuleType::Limit || $rule->key === 'daily_limit') {
                
                $promo = $rule->promotion;
                
                if ($promo) {
                    // Preguntamos a la Promoción: "¿Te queda alguna OTRA regla de límite?"
                    // Si la respuesta es NO, entonces reseteamos el contador a 0.
                    if (!$promo->tieneLimiteDiario()) {
                        $promo->update(['ventas_diarias_actuales' => 0]);
                    }
                }
            }
        });
    }

    // protected static function booted(): void
    // {
    //     static::addGlobalScope('restaurant', function (Builder $query) {
    //         if (filament()->getTenant()) {
    //             $query->where('restaurant_id', filament()->getTenant()->id);
    //         }
    //     });

    //     static::creating(function ($model) {
    //         if (filament()->getTenant()) {
    //             $model->restaurant_id = filament()->getTenant()->id;
    //         }
    //     });
    // }
}
