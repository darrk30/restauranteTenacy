<?php

namespace App\Filament\Restaurants\Widgets;

use App\Models\CashRegisterMovement;
use App\Models\PaymentMethod; // Asegúrate de importar este modelo
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class VentasPorMetodoPagoStats extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        // 1. OBTENER EL RANGO DE FECHAS (Igual que antes)
        $rango = $this->filters['rango'] ?? 'hoy';
        $inicio = now()->startOfDay();
        $fin = now()->endOfDay();

        if ($rango === 'semana') {
            $inicio = now()->startOfWeek();
            $fin = now()->endOfWeek();
        } elseif ($rango === 'mes') {
            $inicio = now()->startOfMonth();
            $fin = now()->endOfMonth();
        } elseif ($rango === 'custom') {
            $inicio = isset($this->filters['fecha_inicio']) ? Carbon::parse($this->filters['fecha_inicio']) : now()->startOfDay();
            $fin = isset($this->filters['fecha_fin']) ? Carbon::parse($this->filters['fecha_fin']) : now()->endOfDay();
        }

        // 2. OBTENER TODOS LOS MÉTODOS DE PAGO DISPONIBLES
        // Si tus métodos son globales usa PaymentMethod::all()
        // Si son por restaurante usa ->where('restaurant_id', Filament::getTenant()->id)
        $metodos = PaymentMethod::where('status', true)->get(); 

        $stats = [];

        foreach ($metodos as $metodo) {
            // 3. CALCULAR EL TOTAL PARA ESTE MÉTODO ESPECÍFICO
            $total = CashRegisterMovement::query()
                ->where('payment_method_id', $metodo->id) // Filtramos por el ID del método actual
                // Filtro de Restaurante (Tenant)
                ->whereHas('sessionCashRegister.cashRegister', function ($q) {
                    $q->where('restaurant_id', Filament::getTenant()->id);
                })
                ->where('tipo', 'ingreso')
                ->whereBetween('created_at', [$inicio, $fin])
                ->sum('monto'); // Sumamos directamente

            // 4. CONFIGURACIÓN VISUAL
            $nombre = $metodo->name;
            
            // Iconos
            $icon = match (strtolower($nombre)) {
                'efectivo' => 'heroicon-m-banknotes',
                'tarjeta', 'visa', 'mastercard' => 'heroicon-m-credit-card',
                'yape', 'plin' => 'heroicon-m-device-phone-mobile',
                default => 'heroicon-m-currency-dollar',
            };

            // Colores (Gris si es 0, Color si tiene ventas)
            $color = $total > 0 
                ? match (strtolower($nombre)) {
                    'efectivo' => 'success',
                    'yape', 'plin' => 'info',
                    'tarjeta' => 'warning',
                    default => 'primary',
                } 
                : 'gray'; // Si es 0 se ve gris

            $stats[] = Stat::make($nombre, 'S/ ' . number_format($total, 2))
                ->description($total > 0 ? 'Total recaudado' : 'Sin movimientos')
                ->descriptionIcon($icon)
                ->color($color);
        }

        return $stats;
    }
}