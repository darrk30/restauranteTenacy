<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receta extends Model
{
    protected $table = 'recetas';

    protected $fillable = [
        'variant_id',
        'insumo_id',
        'cantidad',
        'unit_id',
        'restaurant_id',
    ];

    // El ingrediente (que es otra variante)
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'insumo_id');
    }

    // La unidad de medida en la receta (ej: Gramos)
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
    
    // El plato padre
    public function plato(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'variant_id');
    }

     protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($receta) {
            if (filament()->getTenant()) {
                $receta->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}