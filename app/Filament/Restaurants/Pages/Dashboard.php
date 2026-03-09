<?php

namespace App\Filament\Restaurants\Pages;

use App\Filament\Restaurants\Widgets\AnulacionesStats;
use App\Filament\Restaurants\Widgets\CantidadVentasCanalChart;
use App\Filament\Restaurants\Widgets\ComprasMensualesChart;
use App\Filament\Restaurants\Widgets\GananciasStats;
use App\Filament\Restaurants\Widgets\IngresosEgresosStats;
use App\Filament\Restaurants\Widgets\QrMenuWidget;
use App\Filament\Restaurants\Widgets\VentasCanalStats;
use App\Filament\Restaurants\Widgets\VentasPorDiaChart;
use App\Filament\Restaurants\Widgets\VentasPorMetodoPagoStats;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    // ==========================================
    // 🟢 1. MOSTRAR U OCULTAR DEL MENÚ LATERAL
    // ==========================================
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if ($user->hasRole('Super Admin')) return true;

        $sufijo = Filament::getTenant() ? '_rest' : '_admin';
        try {
            return $user->hasPermissionTo('ver_dashboard' . $sufijo);
        } catch (\Exception $e) {
            return false;
        }
    }

    // ==========================================
    // 🟢 2. PERMITIR ACCESO A LA URL BASE (Evita error 403 al login)
    // ==========================================
    public static function canAccess(): bool
    {
        return true;
    }

    // ==========================================
    // 🟢 3. REDIRECCIÓN INSTANTÁNEA AL LOGUEARSE
    // ==========================================
    public function mount()
    {
        $user = auth()->user();
        $sufijo = Filament::getTenant() ? '_rest' : '_admin';

        $puedeVerDashboard = false;
        if ($user->hasRole('Super Admin')) {
            $puedeVerDashboard = true;
        } else {
            try {
                $puedeVerDashboard = $user->hasPermissionTo('ver_dashboard' . $sufijo);
            } catch (\Exception $e) {}
        }

        // Si NO tiene permiso para ver el Dashboard, lo enviamos de inmediato a su área
        if (! $puedeVerDashboard) {

            // 🎯 Prioridad 1: Si es cajero/mozo y tiene acceso al POS, va directo allí.
            try {
                if ($user->hasPermissionTo('ver_punto_venta' . $sufijo)) {
                    return redirect()->to('/app/point-of-sale');
                }
            } catch (\Exception $e) {}

            // 🎯 Prioridad 2: Escanear su menú lateral y mandarlo a la primera opción que tenga asignada
            $navigationGroups = Filament::getNavigation();
            foreach ($navigationGroups as $group) {
                foreach ($group->getItems() as $item) {
                    $url = $item->getUrl();
                    // Redirige al primer enlace que no sea este mismo Dashboard
                    if ($url && $url !== request()->url()) {
                        return redirect()->to($url);
                    }
                }
            }

            // 🛑 Si llega aquí, es porque su rol no tiene asignado ABSOLUTAMENTE NADA
            abort(403, 'Tu cuenta no tiene ningún permiso asignado en el sistema.');
        }
    }

    // ==========================================
    // 🟢 ESCUDO ANTI-CRASH PARA WIDGETS
    // ==========================================
    private function checkPermiso(string $permisoBase): bool
    {
        $user = auth()->user();
        if ($user->hasRole('Super Admin')) return true;
        $sufijo = Filament::getTenant() ? '_rest' : '_admin';
        try {
            return $user->hasPermissionTo($permisoBase . $sufijo);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getWidgets(): array
    {
        $widgets = [];

        if ($this->checkPermiso('ver_dashboard_ventas')) {
            $widgets[] = VentasCanalStats::make();
        }

        if ($this->checkPermiso('ver_dashboard_metodos_pago')) {
            $widgets[] = VentasPorMetodoPagoStats::make();
        }

        if ($this->checkPermiso('ver_dashboard_ganancias')) {
            $widgets[] = GananciasStats::make(['soloResumen' => true]);
        }

        if ($this->checkPermiso('ver_dashboard_ingresos_egresos')) {
            $widgets[] = IngresosEgresosStats::make();
        }

        if ($this->checkPermiso('ver_dashboard_anulaciones')) {
            $widgets[] = AnulacionesStats::make();
        }

        if ($this->checkPermiso('ver_dashboard_tendencias')) {
            $widgets[] = VentasPorDiaChart::make();
            $widgets[] = CantidadVentasCanalChart::make();
            $widgets[] = ComprasMensualesChart::make();
        }

        if ($this->checkPermiso('ver_dashboard_qr')) {
            $widgets[] = QrMenuWidget::make();
        }

        return $widgets;
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Periodo de Reporte')
                    ->schema([
                        Select::make('rango')
                            ->label('Periodo')
                            ->options([
                                'hoy' => 'Hoy',
                                'semana' => 'Esta Semana',
                                'mes' => 'Este Mes',
                                'custom' => 'Personalizado',
                            ])
                            ->default('hoy')
                            ->live(),

                        DatePicker::make('fecha_inicio')
                            ->label('Fecha de Inicio')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->visible(fn($get) => $get('rango') === 'custom'),

                        DatePicker::make('fecha_fin')
                            ->label('Fecha de Fin')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->visible(fn($get) => $get('rango') === 'custom'),
                    ])
                    ->columns(3),
            ]);
    }
}