<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\Sale;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class GananciasStats extends BaseWidget
{
    use InteractsWithPageFilters;

    public bool $soloResumen = false;

    protected function getStats(): array
    {
        // 🟢 Filament Dashboard usa 'tableFilters' para los filtros globales
        $filtros = $this->filters ?? [];
        $tenantId = Filament::getTenant()->id;

        $query = Sale::query()
            ->where('restaurant_id', $tenantId)
            ->where('status', 'completado');

        // 🟢 Lógica de Filtrado Inteligente para Dashboard y Reporte
        $inicio = null;
        $fin = null;

        $rango = $filtros['rango'] ?? null;

        if ($rango && $rango !== 'custom') {
            // Filtros rápidos del Dashboard
            $inicio = match ($rango) {
                'hoy' => now()->startOfDay(),
                'semana' => now()->startOfWeek(),
                'mes' => now()->startOfMonth(),
                default => null,
            };
            $fin = now()->endOfDay();
        } else {
            // Filtros manuales (Reporte o Dashboard Personalizado)
            if (!empty($filtros['fecha_desde'] ?? $filtros['fecha_inicio'])) {
                $inicio = Carbon::parse($filtros['fecha_desde'] ?? $filtros['fecha_inicio'])->startOfDay();
            }
            if (!empty($filtros['fecha_hasta'] ?? $filtros['fecha_fin'])) {
                $fin = Carbon::parse($filtros['fecha_hasta'] ?? $filtros['fecha_fin'])->endOfDay();
            }
        }

        if ($inicio) $query->where('fecha_emision', '>=', $inicio);
        if ($fin) $query->where('fecha_emision', '<=', $fin);

        $ventas = $query->get(['total', 'costo_total']);

        $ingresos = $ventas->sum('total');
        $costos = $ventas->sum('costo_total');
        $ganancia = $ingresos - $costos;
        $margen = $ingresos > 0 ? ($ganancia / $ingresos) * 100 : 0;

        $stats = [];

        if (!$this->soloResumen) {
            $stats[] = Stat::make('Ingresos Totales', 'S/ ' . number_format($ingresos, 2))
                ->icon('heroicon-o-banknotes')
                ->color('success');

            $stats[] = Stat::make('Costos de Recetas', 'S/ ' . number_format($costos, 2))
                ->icon('heroicon-o-shopping-cart')
                ->color('danger');
        }

        $stats[] = Stat::make('Ganancia Líquida', 'S/ ' . number_format($ganancia, 2))
            ->description('Utilidad real')
            ->icon('heroicon-o-sparkles')
            ->color('primary');

        $stats[] = Stat::make('Margen', number_format($margen, 1) . '%')
            ->color($margen >= 30 ? 'success' : 'warning')
            ->icon('heroicon-o-chart-pie');

        return $stats;
    }
}
