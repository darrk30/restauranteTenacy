<?php

namespace App\Models;

use App\Enums\PromotionRuleType;
use App\Enums\TipoProducto;
use App\Traits\ActualizarFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Promotion extends Model
{
    use ActualizarFile;
    protected $fillable = [
        'name',
        'price',
        'slug',
        'code',
        'image_path',
        'production_id',
        'ventas_diarias_actuales',
        'type',
        'fecha_ultima_venta',
        'category_id',
        'visible',
        'description',
        'status',
        'date_start',
        'date_end',
        'restaurant_id',
    ];

    protected $casts = [
        'type' => TipoProducto::class,
        'date_start' => 'datetime', // <--- AGREGAR ESTO
        'date_end' => 'datetime',   // <--- AGREGAR ESTO
        'visible' => 'boolean',     // <--- RECOMENDADO
        'fecha_ultima_venta' => 'date',
    ];

    public function rules()
    {
        return $this->hasMany(PromotionRule::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function promotionproducts()
    {
        return $this->hasMany(PromotionProduct::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class);
    }

    // --- LÓGICA DEL NEGOCIO (MÉTODOS NUEVOS) ---

    public function isAvailable(): bool
    {
        $esVisible = $this->getAttribute('visible');
        $statusDB = strtolower($this->status);

        if (!$esVisible || $statusDB !== 'activo') {
            return false;
        }

        $now = now();
        // dd($now);
        // 3. VALIDACIÓN DE FECHAS (Ahora seguro gracias a los $casts)
        if ($this->date_start && $now->lt($this->date_start)) return false;
        if ($this->date_end && $now->gt($this->date_end)) return false;

        // 4. REGLAS
        // Si no hay reglas (colección vacía), el foreach no corre y retorna true.
        if ($this->relationLoaded('rules')) {
            foreach ($this->rules as $rule) {
                if (!$rule->check($now)) return false;
            }
        }

        return true;
    }


    /**
     * Verifica si la promoción tiene una regla de límite diario activa.
     */
    public function tieneLimiteDiario(): bool
    {
        // Usamos la relación query builder (rules()) en vez de la colección (rules)
        // para preguntar directamente a la BD.
        return $this->rules()
            ->where(function ($q) {
                // Buscamos por Enum (si el casting funciona) O por string directo 'daily_limit'
                // para asegurar compatibilidad.
                $q->where('type', PromotionRuleType::Limit)
                    ->orWhere('key', 'daily_limit');
            })
            ->exists();
    }

    public function getStockDiarioRestante(): ?int
    {
        // 1. Buscamos si existe una regla de límite diario
        // Nota: Asumimos que has cargado la relación 'rules' con eager loading
        $reglaLimite = $this->rules->first(function ($rule) {
            return $rule->type === PromotionRuleType::Limit || $rule->key === 'daily_limit'; // Compatibilidad con lo que tengas guardado
        });

        // Si no hay regla de límite, retornamos null (significa infinito)
        if (!$reglaLimite) {
            return null;
        }

        // 2. Obtenemos el valor máximo configurado (Ej: 10)
        $config = is_string($reglaLimite->value) ? json_decode($reglaLimite->value, true) : $reglaLimite->value;
        $limiteMaximo = intval($config['limit'] ?? 0);

        if ($limiteMaximo <= 0) return null;

        // 3. Calculamos las ventas efectivas de HOY
        // Si la fecha en BD es de ayer, contamos como 0. Si es hoy, usamos el contador.
        $ventasHoy = ($this->fecha_ultima_venta && $this->fecha_ultima_venta->isToday())
            ? $this->ventas_diarias_actuales
            : 0;

        // 4. Retornamos el restante (mínimo 0, no negativos)
        return max(0, $limiteMaximo - $ventasHoy);
    }

    // En App\Models\Promotion.php

    /**
     * Calcula cuántos combos se pueden armar basándose en el STOCK FÍSICO REAL.
     */
    public function getStockFisicoRestante(): int
    {
        if ($this->promotionproducts->isEmpty()) return 9999;

        $minimoPosible = 999999;

        foreach ($this->promotionproducts as $detalle) {
            $producto = $detalle->product;

            // Si no controla stock, lo saltamos
            if (!$producto || $producto->control_stock == 0) continue;

            $cantidadRequerida = $detalle->quantity;
            if ($cantidadRequerida <= 0) continue;

            $stockItem = 0;

            // --- CORRECCIÓN AQUÍ ---
            if ($detalle->variant_id && $detalle->variant) {
                // Usamos 'stock_reserva' igual que en tu OrdenMesa
                $stockItem = $detalle->variant->stock->sum('stock_reserva');
            } elseif ($producto) {
                // Si el producto simple tuviera stock directo
                $stockItem = $producto->stock ?? 0;
            }
            // -----------------------

            $alcanzaPara = intval($stockItem / $cantidadRequerida);

            if ($alcanzaPara < $minimoPosible) {
                $minimoPosible = $alcanzaPara;
            }
        }

        return ($minimoPosible === 999999) ? 9999 : $minimoPosible;
    }

    // ... (decrementStock y booted se mantienen igual) ...
    public function decrementStock(int $qtySold = 1)
    {
        foreach ($this->promotionproducts as $item) { // Nota: verifica si la relación es promotionProducts o promotionproducts
            $totalToDeduct = $item->quantity * $qtySold;
            if ($item->variant) {
                $item->variant()->decrement('stock_real', $totalToDeduct); // Asegura usar la columna correcta de stock
            } elseif ($item->product) {
                $item->product()->decrement('stock', $totalToDeduct);
            }
        }
    }

    protected static function booted(): void
    {
        static::addGlobalScope('restaurant', function (Builder $query) {
            if (filament()->getTenant()) {
                $query->where('restaurant_id', filament()->getTenant()->id);
            }
        });

        static::creating(function ($model) {
            if (filament()->getTenant()) {
                $model->restaurant_id = filament()->getTenant()->id;
            }
        });
    }
}
