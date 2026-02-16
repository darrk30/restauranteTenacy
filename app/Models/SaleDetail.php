<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{

    protected $fillable = [
        'sale_id',
        'product_id',
        'variant_id',
        'promotion_id',
        'product_name',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

}
