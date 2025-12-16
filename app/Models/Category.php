<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Category extends Model
{
    protected $fillable = ['name', 'status'];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($category) {
            if (filament()->getTenant()) {
                $category->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
