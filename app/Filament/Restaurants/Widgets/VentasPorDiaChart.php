<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\Sale;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters; // 👈 1. Importar esto
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class VentasPorDiaChart extends ChartWidget
{
    use InteractsWithPageFilters; // 👈 2. Activar la escucha de filtros del Dashboard

    protected ?string $heading = 'Tendencia de Ventas';
    protected static ?int $sort = 1;
    protected string $color = 'info';

    // ❌ BORRAMOS O COMENTAMOS ESTO (Ya no usaremos el filtro propio del widget)
    // public ?string $filter = 'month';
    // protected function getFilters(): ?array { ... }

    protected function getData(): array
    {
        // 3. RECUPERAR LOS DATOS DEL FILTRO DEL DASHBOARD
        $rango = $this->filters['rango'] ?? 'hoy'; // 'hoy' es el default si no hay filtro
        
        // Variables para el inicio y fin
        $start = null;
        $end = null;

        // Lógica de fechas (Igual que en tu Dashboard)
        switch ($rango) {
            case 'hoy':
                $start = now()->startOfDay();
                $end = now()->endOfDay();
                break;
            case 'semana':
                $start = now()->startOfWeek();
                $end = now()->endOfWeek();
                break;
            case 'mes':
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();
                break;
            case 'custom':
                $start = isset($this->filters['fecha_inicio']) ? Carbon::parse($this->filters['fecha_inicio']) : now()->startOfDay();
                $end = isset($this->filters['fecha_fin']) ? Carbon::parse($this->filters['fecha_fin']) : now()->endOfDay();
                break;
            default:
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();
        }

        // 4. CONFIGURAR LA TENDENCIA (Trend)
        // Usamos Trend::query() para poder filtrar por el Tenant manualmente y asegurar que funcione
        $query = Sale::query()
            ->where('restaurant_id', Filament::getTenant()->id);

        $trend = Trend::query($query)
            ->dateColumn('fecha_emision')
            ->between(start: $start, end: $end);

        // 5. AJUSTAR LA AGRUPACIÓN SEGÚN EL RANGO
        if ($rango === 'hoy') {
            $data = $trend->perHour()->sum('total'); // Por hora si es hoy
        } elseif ($rango === 'year') {
            $data = $trend->perMonth()->sum('total'); // Por mes si es año
        } else {
            $data = $trend->perDay()->sum('total'); // Por día para el resto
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ventas (S/)',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'fill' => 'start',
                    'borderColor' => '#3b82f6', // Color azul bonito
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)', // Fondo transparente
                ],
            ],
            'labels' => $data->map(function (TrendValue $value) use ($rango) {
                $date = Carbon::parse($value->date);

                if ($rango === 'hoy') {
                    return $date->format('H:i'); // 14:00
                } elseif ($rango === 'year') {
                    return $date->format('M'); // Ene
                }
                return $date->format('d/m'); // 15/02
            }),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}