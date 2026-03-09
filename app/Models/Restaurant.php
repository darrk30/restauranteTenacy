<?php

namespace App\Models;

use App\Observers\RestaurantObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

#[ObservedBy([RestaurantObserver::class])]
class Restaurant extends Model
{
    protected $fillable = [
        'name',
        'name_comercial',
        'ruc',
        'address',
        'phone',
        'email',
        'department',
        'district',
        'province',
        'ubigeo',
        'status',
        'logo',
        'slug',
        'carta_activa_cliente',
        'carta_activa_admin',
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function getCachedConfigAttribute()
    {
        $cacheKey = "tenant_{$this->id}_config";

        return Cache::rememberForever($cacheKey, function () {
            // Si existe en la BD, lo retorna. 
            // Si no, devuelve un modelo temporal (solo en RAM) CON sus atributos por defecto.
            return $this->configuration ?? new Configuration([
                'impresion_directa_precuenta' => false,
                'impresion_directa_comprobante' => false,
                'impresion_directa_comanda' => false,
                'mostrar_modal_impresion_comanda' => false,
                'mostrar_modal_impresion_precuenta' => false,
                'mostrar_modal_impresion_comprobante' => false,
                'mostrar_pantalla_cocina' => false,
                'guardar_pedidos_web' => true,
                'habilitar_delivery_web' => true,
                'habilitar_recojo_web' => true,
                'precios_incluyen_impuesto' => true,
                'porcentaje_impuesto' => 18.00,
            ]);
        });
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

    public function warehouseStocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }

    public function unitCategories()
    {
        return $this->hasMany(UnitCategory::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function stockAdjustmentItems()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function cashRegisters()
    {
        return $this->hasMany(CashRegister::class);
    }

    public function sessionCashRegisters()
    {
        return $this->hasMany(SessionCashRegister::class);
    }

    public function kardexes()
    {
        return $this->hasMany(Kardex::class);
    }

    public function conceptoCajas()
    {
        return $this->hasMany(ConceptoCaja::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function typeDocuments()
    {
        return $this->hasMany(TypeDocument::class);
    }

    public function documentSeries()
    {
        return $this->hasMany(DocumentSerie::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function recetas()
    {
        return $this->hasMany(Receta::class);
    }

    public function banners()
    {
        return $this->hasMany(Banner::class);
    }

    public function configuration()
    {
        return $this->hasOne(Configuration::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
}
