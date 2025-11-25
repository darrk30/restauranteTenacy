<?php

namespace App\Models;

use App\Observers\RestaurantObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy([RestaurantObserver::class])]
class Restaurant extends Model
{
    protected $fillable = ['name', 'name_comercial', 'ruc', 'address', 'phone', 'email', 'department', 'district', 'province', 'ubigeo', 'status', 'logo', 'slug'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function printers()
    {
        return $this->hasMany(Printer::class);
    }

    public function floors()
    {
        return $this->hasMany(Floor::class);
    }

    public function tables()
    {
        return $this->hasMany(Table::class);
    }

    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    
    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function warehouseStocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }

}
