<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'tenant_id', // Si usas tenancy
        'title',
        'type',
        'image',
        'image_mobile',
        'bg_color',
        'is_active',
        'sort_order',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}