<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'price',
        'slug',
        'image_path',
        'production_id',
        'restaurant_id',
        'visible',
        'description',
        'status',
        'date_start',
        'date_end',
    ];

    public function rules()
    {
        return $this->hasMany(PromotionRule::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function promotionproducts()
    {
        return $this->hasMany(PromotionProduct::class);
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
