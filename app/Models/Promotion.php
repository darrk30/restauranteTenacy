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
        'active',
        'date_start',
        'date_end',
    ];

    protected $casts = [
        'visible' => 'boolean',
        'active' => 'boolean',
        'date_start' => 'datetime',
        'date_end' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_products')
            ->withPivot('quantity')
            ->withTimestamps();
    }

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

    /*
    |--------------------------------------------------------------------------
    | Global Scope multi-tenant
    |--------------------------------------------------------------------------
    */
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
