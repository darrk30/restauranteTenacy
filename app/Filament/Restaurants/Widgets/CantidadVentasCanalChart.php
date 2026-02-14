<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\Sale;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class CantidadVentasCanalChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tendencia de Pedidos por Canal';

    protected static ?int $sort = 2;
    // protected static string $color = 'info';

    protected function getData(): array
    {
        $rango = $this->filters['rango'] ?? 'hoy';
        $start = null;
        $end = null;

        // 1. Definir rango de fechas
        switch ($rango) {
            case 'hoy':
                $start = now()->startOfDay(); $end = now()->endOfDay();
                break;
            case 'semana':
                $start = now()->startOfWeek(); $end = now()->endOfWeek();
                break;
            case 'mes':
                $start = now()->startOfMonth(); $end = now()->endOfMonth();
                break;
            case 'custom':
                $start = !empty($this->filters['fecha_inicio']) ? Carbon::parse($this->filters['fecha_inicio']) : now()->startOfDay();
                $end = !empty($this->filters['fecha_fin']) ? Carbon::parse($this->filters['fecha_fin']) : now()->endOfDay();
                break;
            default:
                $start = now()->startOfDay(); $end = now()->endOfDay();
        }

        // 2. Función para obtener la tendencia por canal específico
        $getTrend = function ($canal) use ($start, $end, $rango) {
            $query = Sale::query()
                ->where('restaurant_id', Filament::getTenant()->id)
                ->where('canal', $canal);

            $trend = Trend::query($query)
                ->dateColumn('fecha_emision')
                ->between($start, $end);

            // Ajustar agrupación según el rango
            return match ($rango) {
                'hoy' => $trend->perHour()->count(),
                'year' => $trend->perMonth()->count(),
                default => $trend->perDay()->count(),
            };
        };

        // 3. Obtener los 3 datasets
        $salonData = $getTrend('salon');
        $llevarData = $getTrend('llevar');
        $deliveryData = $getTrend('delivery');

        return [
            'datasets' => [
                [
                    'label' => 'Salón',
                    'data' => $salonData->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#f59e0b', // Naranja
                    'backgroundColor' => '#f59e0b',
                    'tension' => 0.3, // Curvatura de la línea
                ],
                [
                    'label' => 'Para Llevar',
                    'data' => $llevarData->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10b981', // Verde
                    'backgroundColor' => '#10b981',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Delivery',
                    'data' => $deliveryData->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#3b82f6', // Azul
                    'backgroundColor' => '#3b82f6',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $salonData->map(function (TrendValue $value) use ($rango) {
                $date = Carbon::parse($value->date);
                return match ($rango) {
                    'hoy' => $date->format('H:i'),
                    'year' => $date->format('M'),
                    default => $date->format('d/m'),
                };
            }),
        ];
    }

    protected function getType(): string
    {
        // CAMBIAMOS EL TIPO A LINEA
        return 'line'; 
    }
}