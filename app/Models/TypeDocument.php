<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TypeDocument extends Model
{
    protected $fillable = [
        'code',
        'description',
        'maximo_carateres',
        'status',
        'restaurant_id'
    ];

    public function clients()
    {
        return $this->hasMany(Client::class);
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
            if (app()->has('bypass_tenant_scope')) {
                return;
            }

            if (filament()->getTenant() && ! $model->restaurant_id) {
                $model->restaurant_id = filament()->getTenant()->id;
            }
        });

    }
}
