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

    public string $currentTab = 'ordenes';

    #[On('update-anulaciones-stats')]
    public function updateStats(array $filters, string $tab): void
    {
        $this->currentTab = $tab;
    }

    protected function getStats(): array
    {
        $filtros = $this->filters ?? [];
        $tenantId = Filament::getTenant()->id;

        $fechaDesde = $filtros['fecha_desde'] ?? null;
        $fechaHasta = $filtros['fecha_hasta'] ?? null;
        $canal      = $filtros['canal'] ?? null;
        $userId     = $filtros['user_id'] ?? null;
        $tableId    = $filtros['table_id'] ?? null; // 🟢 Capturamos el filtro de mesa

        if ($this->currentTab === 'ordenes') {
            $query = Order::query()
                ->where('restaurant_id', $tenantId)
                ->where('status', 'cancelado');

            if ($fechaDesde) $query->where('created_at', '>=', $fechaDesde);
            if ($fechaHasta) $query->where('created_at', '<=', $fechaHasta);
            if ($canal) $query->where('canal', $canal);
            if ($userId) $query->where('user_id', $userId);
            if ($tableId) $query->where('table_id', $tableId); // 🟢 Aplicamos filtro

            $cantidad = $query->count();
            $monto = (float) $query->sum('total');
            $label = 'Pedidos Anulados';
        } else {
            $query = OrderDetail::query()
                ->where('status', 'cancelado')
                ->whereHas('order', function ($q) use ($tenantId, $fechaDesde, $fechaHasta, $canal, $tableId) {
                    $q->where('restaurant_id', $tenantId);
                    if ($fechaDesde) $q->where('created_at', '>=', $fechaDesde);
                    if ($fechaHasta) $q->where('created_at', '<=', $fechaHasta);
                    if ($canal) $q->where('canal', $canal);
                    if ($tableId) $q->where('table_id', $tableId); // 🟢 Aplicamos filtro
                })
                ->when($userId, fn($q) => $q->where('updated_by', $userId)); // Filtrar por quien anuló

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
}