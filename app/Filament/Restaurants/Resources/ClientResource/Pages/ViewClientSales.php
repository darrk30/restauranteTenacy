<?php

namespace App\Filament\Restaurants\Resources\ClientResource\Pages;

use App\Filament\Restaurants\Resources\ClientResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Usamos ManageRelatedRecords para gestionar la relación de ventas fácilmente
class ViewClientSales extends ManageRelatedRecords
{
    protected static string $resource = ClientResource::class;

    protected static string $relationship = 'sales';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $title = 'Historial de Facturas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serie_correlativo') // Cambia por tu campo de nombre de factura
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('serie_correlativo') // Nombre ficticio para la columna
                    ->label('Comprobante')
                    ->getStateUsing(function ($record) {
                        // Unimos serie y correlativo con un guion
                        return "{$record->serie}-{$record->correlativo}";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        // 🟢 VITAL: Esto permite buscar aunque los campos estén separados en la BD
                        return $query->where('serie', 'like', "%{$search}%")
                            ->orWhere('correlativo', 'like', "%{$search}%");
                    })
                    ->weight('bold')
                    ->copyable()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN') // Moneda local (Soles)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total Acumulado')),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                // Botón para ir al detalle real de la venta si es necesario
                Tables\Actions\Action::make('ver_detalle')
                    ->label('Ver Detalle')
                    ->icon('heroicon-m-eye')
                    ->color('info')
                    ->url(fn($record) => ClientResource::getUrl('factura-detalle', [
                        'record' => $this->record, // El cliente
                        'sale' => $record->id,     // La venta específica
                    ])),
            ]);
    }

     public function getBreadcrumbs(): array
    {
        return [
            ClientResource::getUrl('index') => 'Clientes',
            ClientResource::getUrl('edit', ['record' => $this->record]) => $this->record->nombres ?? $this->record->razon_social,
            ClientResource::getUrl('facturas', ['record' => $this->record]) => 'Facturas',
        ];
    }
}
