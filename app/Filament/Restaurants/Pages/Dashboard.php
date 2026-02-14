<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm; // 👈 Importante

class Dashboard extends BaseDashboard
{
    use HasFiltersForm; // 👈 Habilita los filtros

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
                            ->visible(fn ($get) => $get('rango') === 'custom'),
                            
                        DatePicker::make('fecha_fin')
                            ->visible(fn ($get) => $get('rango') === 'custom'),
                    ])
                    ->columns(3),
            ]);
    }
}