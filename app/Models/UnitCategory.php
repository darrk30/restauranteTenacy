<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'restaurant_id',
    ];

    /**
     * 🔗 Relación: una categoría pertenece a un restaurante.
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * 🔗 Relación: una categoría tiene muchas unidades.
     */
    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * 🔗 Obtener la unidad base de esta categoría.
     */
    public function baseUnit()
    {
        return $this->hasOne(Unit::class)->where('is_base', true);
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
                return; // omitir asignación
            }

            if (filament()->getTenant() && ! $model->restaurant_id) {
                $model->restaurant_id = filament()->getTenant()->id;
            }
        });

    }
}
