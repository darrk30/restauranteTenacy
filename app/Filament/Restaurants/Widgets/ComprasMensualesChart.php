<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\Purchase;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class ComprasMensualesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Gastos en Compras de Insumos';
    protected static ?int $sort = 4;
    protected static string $color = 'danger';

    protected function getData(): array
    {
        $rango = $this->filters['rango'] ?? 'hoy';
        
        // 1. Configuración de fechas según el filtro del Dashboard
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
                $start = !empty($this->filters['fecha_inicio']) ? Carbon::parse($this->filters['fecha_inicio']) : now()->startOfMonth();
                $end = !empty($this->filters['fecha_fin']) ? Carbon::parse($this->filters['fecha_fin']) : now()->endOfMonth();
                break;
            default:
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();
        }

        // 2. Consulta usando Trend
        // Nota: No necesitamos filtrar por restaurant_id aquí porque tu modelo Purchase 
        // ya tiene un GlobalScope que lo hace automáticamente.
        $data = Trend::model(Purchase::class)
            ->dateColumn('fecha_compra') // Usamos tu columna real
            ->between(start: $start, end: $end);

        // 3. Ajustar agrupación
        if ($rango === 'hoy') {
            $data = $data->perHour()->sum('total');
        } else {
            $data = $data->perDay()->sum('total');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Compras Realizadas (S/)',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'backgroundColor' => '#ef4444',
                    'borderColor' => '#ef4444',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $data->map(function (TrendValue $value) use ($rango) {
                $date = Carbon::parse($value->date);
                return $rango === 'hoy' ? $date->format('H:i') : $date->format('d/m');
            }),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}