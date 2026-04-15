<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySummary extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Casts para manejar los JSON de forma automática
    protected $casts = [
        'fecha_generacion' => 'date',
        'fecha_resumen' => 'date',
        'fecha_comunicacion' => 'date',
        'details' => 'array',
        'notes' => 'array',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class); // Asegúrate de apuntar a tu modelo correcto (Restaurant o Tenant)
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}