<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'tipo_documento',
        'numero',
        'correo',
        'telefono',
        'direccion',
        'departamento',
        'distrito',
        'provincia',
        'status',
        'restaurant_id',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
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

        static::creating(function ($supplier) {
            if (filament()->getTenant()) {
                $supplier->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}

