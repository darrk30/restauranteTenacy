<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\CashRegisterMovement;
use App\Models\Sale;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;
use Illuminate\Support\Carbon; // Importante para las fechas

class VentasCanalStats extends BaseWidget
{
    use InteractsWithPageFilters;

    #[On('update-stats')]
    public function updateStats(array $filters): void
    {
        $this->filters = $filters;
    }

    protected function getStats(): array
    {
        $filtros = $this->filters ?? [];
        $tenantId = Filament::getTenant()->id;

        $query = Sale::query()->where('restaurant_id', $tenantId);

        // 1. LÓGICA DE FECHAS (Dashboard vs Reporte) -------------------------
        
        $inicio = null;
        $fin = null;

        // A. Si viene del DASHBOARD (Usa 'rango')
        if (!empty($filtros['rango'])) {
            switch ($filtros['rango']) {
                case 'hoy':
                    $inicio = now()->startOfDay();
                    $fin = now()->endOfDay();
                    break;
                case 'semana':
                    $inicio = now()->startOfWeek();
                    $fin = now()->endOfWeek();
                    break;
                case 'mes':
                    $inicio = now()->startOfMonth();
                    $fin = now()->endOfMonth();
                    break;
                case 'custom':
                    $inicio = !empty($filtros['fecha_inicio']) ? Carbon::parse($filtros['fecha_inicio'])->startOfDay() : null;
                    $fin = !empty($filtros['fecha_fin']) ? Carbon::parse($filtros['fecha_fin'])->endOfDay() : null;
                    break;
            }
        } 
        // B. Si viene del REPORTE (Usa 'fecha_desde')
        elseif (!empty($filtros['fecha_desde'])) {
            $inicio = Carbon::parse($filtros['fecha_desde'])->startOfDay();
            if (!empty($filtros['fecha_hasta'])) {
                $fin = Carbon::parse($filtros['fecha_hasta'])->endOfDay();
            }
        }
        // C. Fallback (Si no hay filtros, por defecto HOY o TODO)
        else {
             // Opcional: Si quieres que por defecto en Dashboard muestre HOY si no se ha filtrado nada
             // $inicio = now()->startOfDay();
             // $fin = now()->endOfDay();
        }

        // Aplicar fechas a la query
        if ($inicio) $query->whereDate('fecha_emision', '>=', $inicio);
        if ($fin) $query->whereDate('fecha_emision', '<=', $fin);

        // ---------------------------------------------------------------------

        // 2. LÓGICA DE ESTADO (CORRECCIÓN AQUÍ) -------------------------------
        
        // Si hay un status seleccionado (Reporte), lo usamos.
        // Si NO hay status (Dashboard), forzamos 'completado' para ignorar anulados.
        $statusFilter = $filtros['status'] ?? 'completado'; 
        $query->where('status', $statusFilter);

        // ---------------------------------------------------------------------

        // Filtros específicos adicionales
        if (!empty($filtros['serie'])) $query->where('serie', $filtros['serie']);
        if (!empty($filtros['numero'])) $query->where('correlativo', 'like', "%{$filtros['numero']}%");

        // 3. DATOS GENERALES
        $totalGeneralQuery = clone $query;
        if (!empty($filtros['canal'])) {
            $totalGeneralQuery->where('canal', $filtros['canal']);
        }

        $totalVentas = $totalGeneralQuery->sum('total');
        $cantidadVentas = $totalGeneralQuery->count();

        // 4. DATOS POR CANAL 
        $porCanalQuery = clone $query;
        if (!empty($filtros['canal'])) {
            $porCanalQuery->where('canal', $filtros['canal']);
        }

        $porCanal = $porCanalQuery
            ->selectRaw('canal, sum(total) as total')
            ->groupBy('canal')
            ->pluck('total', 'canal');

        // 5. DATOS POR MÉTODO DE PAGO
        $statMetodoPago = null;
        if (!empty($filtros['payment_method_id'])) {
            $metodoId = $filtros['payment_method_id'];
            $nombreMetodo = \App\Models\PaymentMethod::find($metodoId)?->name;

            $saleIds = $totalGeneralQuery->pluck('id');

            $totalMetodo = CashRegisterMovement::query()
                ->whereIn('referencia_id', $saleIds)
                ->where('referencia_type', Sale::class)
                ->where('status', 'aprobado')
                ->where('payment_method_id', $metodoId)
                ->sum('monto');

            $statMetodoPago = Stat::make("Recaudado ({$nombreMetodo})", 'S/ ' . number_format($totalMetodo, 2))
                ->icon('heroicon-m-credit-card')
                ->description('Monto específico por método')
                ->color('success');
        }

        // 6. RETORNAR STATS
        $stats = [
            Stat::make('Venta Total', 'S/ ' . number_format($totalVentas, 2))
                ->description($cantidadVentas . ' transacciones')
                ->icon('heroicon-m-banknotes')
                ->color('gray'),
        ];

        if ($statMetodoPago) {
            $stats[] = $statMetodoPago;
        }

        if (empty($filtros['canal']) || $filtros['canal'] === 'salon') {
            $stats[] = Stat::make('Salón', 'S/ ' . number_format($porCanal['salon'] ?? 0, 2))
                ->color('warning')->icon('heroicon-m-building-storefront');
        }
        if (empty($filtros['canal']) || $filtros['canal'] === 'llevar') {
            $stats[] = Stat::make('Para Llevar', 'S/ ' . number_format($porCanal['llevar'] ?? 0, 2))
                ->color('success')->icon('heroicon-m-shopping-bag');
        }
        if (empty($filtros['canal']) || $filtros['canal'] === 'delivery') {
            $stats[] = Stat::make('Delivery', 'S/ ' . number_format($porCanal['delivery'] ?? 0, 2))
                ->color('info')->icon('heroicon-m-truck');
        }

        return $stats;
    }
}