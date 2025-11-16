<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'quantity',
        'is_base',
        'unit_category_id',
        'reference_unit_id',
        'restaurant_id',
    ];

    /**
     * 🔗 Relación: la unidad pertenece a una categoría.
     */
    public function category()
    {
        return $this->belongsTo(UnitCategory::class, 'unit_category_id');
    }

    /**
     * 🔗 Relación: la unidad pertenece a un restaurante.
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * 🔗 Relación: unidad base o de referencia sobre la cual se define esta unidad.
     * Ejemplo: si esta unidad es “Kilogramo”, su unidad base es “Gramo”.
     */
    public function unidadBase()
    {
        return $this->belongsTo(Unit::class, 'reference_unit_id');
    }

    /**
     * 🔗 Relación: unidades derivadas que usan esta unidad como referencia.
     * Ejemplo: si esta unidad es “Gramo”, las derivadas pueden ser “Kilogramo”, “Miligramo”, etc.
     */
    public function unidadesDerivadas()
    {
        return $this->hasMany(Unit::class, 'reference_unit_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * 💡 Helper: verificar si esta unidad es base.
     */
    public function isBase(): bool
    {
        return (bool) $this->is_base;
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
