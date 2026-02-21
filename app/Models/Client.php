<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'nombres',
        'apellidos',
        'razon_social',
        'telefono',
        'email',
        'direccion',
        'type_document_id',
        'numero',
        'restaurant_id',
        'is_active',
    ];

    public function typeDocument()
    {
        return $this->belongsTo(TypeDocument::class);
    }

    public function getFullNameAttribute(): string
    {
        // Si tiene RUC y razón social, devolvemos eso
        if ($this->tipo_documento === 'RUC' && $this->razon_social) {
            return $this->razon_social;
        }

        // De lo contrario, concatenamos nombres y apellidos
        return trim("{$this->nombres} {$this->apellido_paterno} {$this->apellido_materno}");
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($client) {
            if (app()->has('bypass_tenant_scope')) {
                return; // omitir asignación
            }
            if (filament()->getTenant()) {
                $client->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
