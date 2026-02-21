<?php

namespace App\Filament\Restaurants\Pages;

use App\Filament\Restaurants\Widgets\AnulacionesStats;
use App\Filament\Restaurants\Widgets\CantidadVentasCanalChart;
use App\Filament\Restaurants\Widgets\ComprasMensualesChart;
use App\Filament\Restaurants\Widgets\GananciasStats;
use App\Filament\Restaurants\Widgets\IngresosEgresosStats;
use App\Filament\Restaurants\Widgets\VentasCanalStats;
use App\Filament\Restaurants\Widgets\VentasPorDiaChart;
use App\Filament\Restaurants\Widgets\VentasPorMetodoPagoStats;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm; // 👈 Importante

class Dashboard extends BaseDashboard
{
    use HasFiltersForm; // 👈 Habilita los filtros

    public function getWidgets(): array
    {
        return [
            VentasCanalStats::make(),
            VentasPorMetodoPagoStats::make(),
            GananciasStats::make([
                'soloResumen' => true,
            ]),
            IngresosEgresosStats::make(),
            AnulacionesStats::make(),
            VentasPorDiaChart::make(),
            CantidadVentasCanalChart::make(),
            ComprasMensualesChart::make(),
        ];
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
                            ->live(), // 👈 Actualiza en vivo

                        DatePicker::make('fecha_inicio')
                            ->visible(fn($get) => $get('rango') === 'custom'),

                        DatePicker::make('fecha_fin')
                            ->visible(fn($get) => $get('rango') === 'custom'),
                    ])
                    ->columns(3),
            ]);
    }
}
