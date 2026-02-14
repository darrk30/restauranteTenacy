<?php

namespace App\Models;

use App\Enums\StatusProducto;
use App\Enums\TipoProducto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property \Illuminate\Database\Eloquent\Collection $attributes
 */

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'code',
        'image_path',
        'type',
        'description',
        'production_id',
        'brand_id',
        'unit_id',
        'status',
        'price',
        'control_stock',
        'venta_sin_stock',
        'cortesia',
        'visible',
        'order',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'attribute_product')
            ->using(AttributeProduct::class) // <--- AGREGA ESTA LÍNEA
            ->withPivot('values')
            ->withTimestamps();
        // return $this->belongsToMany(Attribute::class, 'attribute_product')
        //     ->withPivot('values')
        //     ->withTimestamps();
    }

    public function promotionproducts()
    {
        return $this->hasMany(PromotionProduct::class);
    }

    public function kardexes()
    {
        return $this->hasMany(Kardex::class);
    }

    public function stockadjustmentitems()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    protected $casts = [
        'type' => TipoProducto::class,
        'status' => StatusProducto::class,
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class);
    }

    // SCOPE 1: Lógica del Buscador
    public function scopeBuscar(Builder $query, ?string $term)
    {
        if ($term) {
            return $query->where('name', 'like', '%' . $term . '%');
        }
    }

    // SCOPE 2: Lógica de Categoría (Ya que estamos, limpiamos esto también)
    public function scopePorCategoria(Builder $query, ?int $categoriaId)
    {
        if ($categoriaId) {
            return $query->whereHas('categories', function ($q) use ($categoriaId) {
                $q->where('categories.id', $categoriaId);
            });
        }
    }
    
    // SCOPE 3: Status Activo (Opcional, para limpiar más)
    public function scopeActivos(Builder $query)
    {
        return $query->where('status', StatusProducto::Activo);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($product) {
            if (filament()->getTenant()) {
                $product->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
