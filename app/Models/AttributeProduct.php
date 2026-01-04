<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AttributeProduct extends Pivot
{
    protected $casts = [
        'values' => 'array',
    ];
}