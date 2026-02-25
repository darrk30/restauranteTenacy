<?php

namespace App\Filament\Restaurants\Resources\FloorResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TablesRelationManager extends RelationManager
{
    protected static string $relationship = 'Tables';
    protected static ?string $title = 'Mesas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre de Mesa')
                    ->required(),
                Forms\Components\TextInput::make('asientos')
                    ->label('Número de Asientos')
                    ->default(1),
                Forms\Components\Toggle::make('status')
                    ->label('Disponible')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Pisos y Mesas')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Mesa'),
                Tables\Columns\TextColumn::make('asientos')->label('Asientos'),
                Tables\Columns\IconColumn::make('status')->boolean()->label('Disponible'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nueva')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->modalHeading('Nueva Mesa')
                    ->modalSubmitActionLabel('Crear Mesa'),
            ])
            ->actions([
                // 🟢 NUEVA ACCIÓN: VER Y DESCARGAR QR DE LA MESA
                Tables\Actions\Action::make('qr_code')
                    ->label('QR Mesa')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading(fn($record) => 'Código QR - ' . $record->name)
                    ->modalContent(function ($record) {
                        $tenant = filament()->getTenant();

                        // 🟢 AQUÍ ESTÁ LA MAGIA: Le pasamos el ID de la mesa por la URL
                        $url = route('carta.digital', ['tenant' => $tenant->slug, 'mesa' => $record->id]);

                        // Generamos el QR en SVG
                        $qrHtml = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)
                            ->style('round')
                            ->margin(1)
                            ->color(15, 100, 59) // Verde oscuro, puedes cambiarlo
                            ->generate($url);

                        return view('filament.pisos.qr-mesa-modal', [
                            'qrHtml' => $qrHtml,
                            'mesa' => $record,
                            'url' => $url,
                            'tenantSlug' => $tenant->slug,
                        ]);
                    })
                    ->modalSubmitAction(false) // No necesitamos botón "Guardar"
                    ->modalCancelActionLabel('Cerrar'),

                Tables\Actions\EditAction::make()
                    ->modalHeading('Editar Mesa')
                    ->modalSubmitActionLabel('Actualizar Mesa'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
