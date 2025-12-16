<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PromotionRule extends Model
{
    protected $fillable = [
        'promotion_id',
        'restaurant_id',
        'type',
        'key',
        'operator',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];


    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
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

        static::creating(function ($model) {
            if (filament()->getTenant()) {
                $model->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}

