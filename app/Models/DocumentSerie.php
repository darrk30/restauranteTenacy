<?php

namespace App\Models;

use App\Enums\DocumentSeriesType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DocumentSerie extends Model
{
    protected $fillable = ['type_documento', 'serie', 'current_number', 'is_active', 'restaurant_id'];

    protected $casts = [
        'type_documento' => DocumentSeriesType::class,
    ];

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

        static::creating(function ($documentSerie) {
            if (app()->has('bypass_tenant_scope')) {
                return;
            }
            if (filament()->getTenant()) {
                $documentSerie->restaurant_id = filament()->getTenant()->id;
            }
        });

        static::saving(function (DocumentSerie $documentSerie) {
            if ($documentSerie->is_active) {
                static::where('restaurant_id', $documentSerie->restaurant_id) //
                    ->where('type_documento', $documentSerie->type_documento)
                    ->where('id', '!=', $documentSerie->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }
}
