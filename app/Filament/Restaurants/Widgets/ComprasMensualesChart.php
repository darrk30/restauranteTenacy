<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\Purchase;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ComprasMensualesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Resumen de Pagos y Compras';
    protected static ?int $sort = 4;

    // 🟢 1. Controla cuántas columnas ocupa en la cuadrícula (1 de 2, o 1 de 3)
    protected int | string | array $columnSpan = 1;

    // 🟢 2. Define una altura máxima para que no crezca demasiado hacia abajo
    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $rango = $this->filters['rango'] ?? 'hoy';

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

        $resumen = Purchase::query()
            ->whereBetween('fecha_compra', [$start, $end])
            ->select(
                'estado_pago',
                DB::raw('SUM(total) as monto_total'),
                DB::raw('COUNT(*) as cantidad_compras')
            )
            ->groupBy('estado_pago')
            ->get();

        $pagado = $resumen->where('estado_pago', 'pagado')->first();
        $pendiente = $resumen->where('estado_pago', 'pendiente')->first();

        $montoPagado = (float) ($pagado?->monto_total ?? 0);
        $montoPendiente = (float) ($pendiente?->monto_total ?? 0);

        $cantPagado = $pagado?->cantidad_compras ?? 0;
        $cantPendiente = $pendiente?->cantidad_compras ?? 0;

        return [
            'datasets' => [
                [
                    'data' => [$montoPagado, $montoPendiente],
                    'backgroundColor' => ['#22c55e', '#ef4444'],
                ],
            ],
            // 🟢 MODIFICACIÓN AQUÍ: Array de arrays para multilínea
            'labels' => [
                [
                    "Total: S/ " . number_format($montoPagado, 2),
                    "Pagados: $cantPagado compras",
                ],
                [
                    "Total: S/ " . number_format($montoPendiente, 2),
                    "Pendientes: $cantPendiente compras",
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            // 🟢 3. Ajustes de Chart.js para que sea más pequeño
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12, // Cuadros de leyenda más pequeños
                        'font' => ['size' => 11], // Letra más pequeña
                    ],
                ],
            ],
        ];
    }
}
