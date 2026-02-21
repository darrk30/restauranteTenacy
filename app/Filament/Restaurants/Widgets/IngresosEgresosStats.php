<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\ConceptoCaja;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class IngresosEgresosStats extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $filtros = $this->filters ?? [];
        
        $query = ConceptoCaja::query()
            ->where('estado', 'aprobado'); //

        // --- FILTROS EXISTENTES ---
        if (!empty($filtros['fecha_desde'])) {
            $query->where('created_at', '>=', $filtros['fecha_desde']);
        }
        if (!empty($filtros['fecha_hasta'])) {
            $query->where('created_at', '<=', $filtros['fecha_hasta']);
        }
        if (!empty($filtros['tipo_movimiento'])) {
            $query->where('tipo_movimiento', $filtros['tipo_movimiento']);
        }
        if (!empty($filtros['usuario_id'])) {
            $query->where('usuario_id', $filtros['usuario_id']);
        }

        // --- 🟢 NUEVO: FILTRO POR CAJA ---
        if (!empty($filtros['cash_register_id'])) {
            $query->whereHas('sessionCashRegister', function ($q) use ($filtros) {
                $q->where('cash_register_id', $filtros['cash_register_id']);
            });
        }

        $movimientos = $query->get(['tipo_movimiento', 'monto']);
        
        $ingresos = $movimientos->where('tipo_movimiento', 'ingreso')->sum('monto');
        $egresos = $movimientos->where('tipo_movimiento', 'egreso')->sum('monto');
        $cantidad = $movimientos->count();

        // Cálculo del balance para el tercer cuadro (opcional, pero recomendado)
        $balance = $ingresos - $egresos;

        return [
            Stat::make('Ingresos Registrados', 'S/ ' . number_format($ingresos, 2))
                ->icon('heroicon-o-arrow-up-circle')
                ->color('success'),

            Stat::make('Egresos Registrados', 'S/ ' . number_format($egresos, 2))
                ->icon('heroicon-o-arrow-down-circle')
                ->color('danger'),

            Stat::make('Balance Neto', 'S/ ' . number_format($balance, 2))
                ->description('Total de ' . $cantidad . ' movimientos')
                ->icon('heroicon-o-banknotes')
                ->color($balance >= 0 ? 'info' : 'danger'),
        ];
    }
}