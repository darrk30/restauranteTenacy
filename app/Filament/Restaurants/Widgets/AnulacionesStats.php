<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\Order;
use App\Models\OrderDetail;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Livewire\Attributes\On;

class AnulacionesStats extends BaseWidget
{
    use InteractsWithPageFilters;

    // Propiedad interna para no mutar los filtros reactivos
    public string $currentTab = 'ordenes';

    #[On('update-anulaciones-stats')]
    public function updateStats(array $filters): void
    {
        // 🟢 Solo actualizamos la pestaña activa, los filtros se leen de InteractsWithPageFilters
        if (isset($filters['activeTab'])) {
            $this->currentTab = $filters['activeTab'];
        }
    }

    protected function getStats(): array
    {
        // Los filtros se obtienen automáticamente mediante el trait InteractsWithPageFilters
        $filtros = $this->filters ?? [];
        $tenantId = Filament::getTenant()->id;

        if ($this->currentTab === 'ordenes') {
            // Consulta para Órdenes Completas
            $query = Order::query()
                ->where('restaurant_id', $tenantId)
                ->where('status', 'cancelado');

            $this->aplicarFiltrosQuery($query, $filtros);

            $cantidad = $query->count();
            $monto = (float) $query->sum('total');
            $label = 'Pedidos Anulados';
        } else {
            // Consulta para Productos Individuales
            $query = OrderDetail::query()
                ->where('status', 'cancelado')
                ->whereHas('order', function ($q) use ($tenantId, $filtros) {
                    $q->where('restaurant_id', $tenantId);
                    $this->aplicarFiltrosQuery($q, $filtros);
                });

            $cantidad = $query->sum('cantidad');
            $monto = (float) $query->sum('subTotal');
            $label = 'Productos Anulados';
        }

        return [
            Stat::make($label, number_format($cantidad, 0))
                ->description('Total en el periodo seleccionado')
                ->icon('heroicon-o-x-circle')
                ->color('danger'),

            Stat::make('Monto Total Anulado', 'S/ ' . number_format($monto, 2))
                ->description('Pérdida económica detectada')
                ->icon('heroicon-o-banknotes')
                ->color('danger'),
        ];
    }

    private function aplicarFiltrosQuery($query, $filtros)
    {
        if (!empty($filtros['fecha_desde'])) $query->where('created_at', '>=', $filtros['fecha_desde']);
        if (!empty($filtros['fecha_hasta'])) $query->where('created_at', '<=', $filtros['fecha_hasta']);
        if (!empty($filtros['canal'])) $query->where('canal', $filtros['canal']);
        if (!empty($filtros['user_id'])) $query->where('user_id', $filtros['user_id']);
    }
}
