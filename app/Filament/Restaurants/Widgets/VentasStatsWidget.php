<?php
namespace App\Filament\Restaurants\Widgets;

use App\Filament\Restaurants\Pages\Reports\VentasReport;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\CashRegisterMovement;
use App\Models\Sale;

class VentasStatsWidget extends BaseWidget
{
    use InteractsWithPageTable;
    protected static bool $isDiscovered = false;

    protected function getTablePage(): string
    {
        return VentasReport::class;
    }

    protected function getStats(): array
    {
        // 1. Obtenemos la query base de la tabla (aplica filtros de fecha, etc.)
        $query = $this->getPageTableQuery();

        // 2. Calculamos el total general de las ventas (columna física)
        $totalVentas = (clone $query)->where('status', 'completado')->sum('total');
        $cantidadVentas = (clone $query)->where('status', 'completado')->count();

        // 3. Calculamos el total del MÉTODO DE PAGO seleccionado
        $metodoId = $this->tableFilters['payment_method_id']['value'] ?? null;
        
        // Obtenemos los IDs de las ventas que están actualmente filtradas en la tabla
        $saleIds = (clone $query)->pluck('id');

        // Sumamos los movimientos de esas ventas filtradas
        $totalMetodoPago = CashRegisterMovement::query()
            ->whereIn('referencia_id', $saleIds)
            ->where('referencia_type', Sale::class)
            ->where('status', 'aprobado')
            ->when($metodoId, fn($q) => $q->where('payment_method_id', $metodoId))
            ->sum('monto');

        // 4. Nombre dinámico para el Stat
        $nombreMetodo = $metodoId 
            ? \App\Models\PaymentMethod::find($metodoId)?->name 
            : 'Total General';

        return [
            Stat::make('Venta Total Bruta', 'S/ ' . number_format($totalVentas, 2))
                ->icon('heroicon-m-banknotes')
                ->description('Suma de columna Total')
                ->color('gray'),

            Stat::make("Recaudado ({$nombreMetodo})", 'S/ ' . number_format($totalMetodoPago, 2))
                ->icon('heroicon-m-check-circle')
                ->description('Monto real según método de pago')
                ->color('success'),
            
            Stat::make('Nro. Ventas', $cantidadVentas)
                ->icon('heroicon-m-shopping-cart')
                ->color('primary'),
        ];
    }
}