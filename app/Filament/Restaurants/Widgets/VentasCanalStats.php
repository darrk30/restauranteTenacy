<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\Sale;
use Filament\Facades\Filament; // Importante para el Tenant
use Filament\Widgets\Concerns\InteractsWithPageFilters; // 👈 NECESARIO PARA EL DASHBOARD
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class VentasCanalStats extends BaseWidget
{
    use InteractsWithPageFilters; // 👈 Esto permite leer los filtros del Dashboard

    // 👇 Mantenemos esto para que el Reporte pueda "inyectar" sus filtros
    #[On('update-stats')]
    public function updateStats(array $filters): void
    {
        // Sobrescribimos los filtros con lo que manda el reporte
        $this->filters = $filters;
    }

    protected function getStats(): array
    {
        // 1. DEFINIR RANGO DE FECHAS (Lógica Híbrida)
        $inicio = null;
        $fin = null;
        $filtros = $this->filters; // Copia local para facilitar lectura

        // CASO A: Viene del REPORTE (Tiene fechas exactas)
        if (!empty($filtros['fecha_desde']) || !empty($filtros['fecha_hasta'])) {
            $inicio = !empty($filtros['fecha_desde']) ? $filtros['fecha_desde'] : null;
            $fin = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        } 
        // CASO B: Viene del DASHBOARD (Tiene rangos: hoy, semana, mes)
        elseif (!empty($filtros['rango'])) {
            $rango = $filtros['rango'];
            switch ($rango) {
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
                case 'year':
                    $inicio = now()->startOfYear();
                    $fin = now()->endOfYear();
                    break;
                case 'custom':
                    $inicio = !empty($filtros['fecha_inicio']) ? Carbon::parse($filtros['fecha_inicio']) : null;
                    $fin = !empty($filtros['fecha_fin']) ? Carbon::parse($filtros['fecha_fin']) : null;
                    break;
            }
        } 
        // CASO C: Por defecto (Si entra directo al Dashboard sin filtrar)
        else {
            $inicio = now()->startOfDay();
            $fin = now()->endOfDay();
        }

        // 2. CONSTRUIR CONSULTA
        $query = Sale::query()
            ->where('restaurant_id', Filament::getTenant()->id); // Filtro de seguridad Tenant

        // Aplicar fechas calculadas arriba
        if ($inicio) $query->whereDate('fecha_emision', '>=', $inicio);
        if ($fin) $query->whereDate('fecha_emision', '<=', $fin);

        // Aplicar filtros extra específicos del Reporte (si existen)
        if (!empty($filtros['canal'])) $query->where('canal', $filtros['canal']);
        if (!empty($filtros['serie'])) $query->where('serie', $filtros['serie']);
        if (!empty($filtros['numero'])) $query->where('correlativo', 'like', "%{$filtros['numero']}%");

        // 3. CALCULAR DATOS
        $totalGeneral = (clone $query)->sum('total');

        $porCanal = (clone $query)
            ->selectRaw('canal, sum(total) as total')
            ->groupBy('canal')
            ->pluck('total', 'canal');

        // 4. RETORNAR TARJETAS
        return [
            Stat::make('Total Ventas', 'S/ ' . number_format($totalGeneral, 2))
                ->description('En el periodo seleccionado')
                ->chart([7, 3, 10, 5, 15, 8, 20])
                ->color('gray'),

            Stat::make('Salón', 'S/ ' . number_format($porCanal['salon'] ?? 0, 2))
                ->description('Consumo en mesa')
                ->color('warning')
                ->icon('heroicon-m-building-storefront'),

            Stat::make('Para Llevar', 'S/ ' . number_format($porCanal['llevar'] ?? 0, 2))
                ->description('Recojo en local')
                ->color('success')
                ->icon('heroicon-m-shopping-bag'),

            Stat::make('Delivery', 'S/ ' . number_format($porCanal['delivery'] ?? 0, 2))
                ->description('Envíos a domicilio')
                ->color('info')
                ->icon('heroicon-m-truck'),
        ];
    }
}