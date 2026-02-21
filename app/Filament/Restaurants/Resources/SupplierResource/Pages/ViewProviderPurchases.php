<?php

namespace App\Filament\Restaurants\Resources\SupplierResource\Pages;

use App\Filament\Restaurants\Resources\SupplierResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewProviderPurchases extends ManageRelatedRecords
{
    protected static string $resource = SupplierResource::class;
    protected static string $relationship = 'purchases';
    protected static ?string $title = 'Historial de Compras';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero_documento')
            ->defaultSort('created_at', 'desc') // 🟢 Últimas compras primero
            ->columns([
                Tables\Columns\TextColumn::make('fecha_compra')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('numero_documento') // Nombre de columna para identificarla
                    ->label('Comprobante')
                    ->weight('bold')
                    ->color('primary')
                    ->getStateUsing(function ($record): string {
                        // Unimos serie y numero con un guion
                        return "{$record->serie}-{$record->numero}";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('serie', 'like', "%{$search}%")
                            ->orWhere('numero', 'like', "%{$search}%");
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Monto Total')
                    ->money('PEN')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total Invertido')),
                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->sortable()
                    ->icon(fn($state) => match ($state) {
                        'pagado' => 'heroicon-o-check-circle',
                        'pendiente' => 'heroicon-o-clock',
                        'cancelado' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn($state) => match ($state) {
                        'pagado' => 'success',
                        'pendiente' => 'warning',
                        'cancelado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('estado_despacho')
                    ->label('Despacho')
                    ->badge()
                    ->icon(fn($state) => match ($state) {
                        'pendiente' => 'heroicon-o-clock',
                        'recibido' => 'heroicon-o-truck',
                        'cancelado' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn($state) => match ($state) {
                        'pendiente' => 'warning',
                        'recibido' => 'success',
                        'cancelado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('estado_comprobante')
                    ->label('Estado')
                    ->badge()
                    ->sortable()
                    ->icon(fn($state) => match ($state) {
                        'aceptado' => 'heroicon-o-check-circle',
                        'anulado' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn($state) => match ($state) {
                        'aceptado' => 'success',
                        'anulado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_detalle')
                    ->label('Detalle')
                    ->icon('heroicon-m-eye')
                    ->color('info')
                    ->url(fn($record) => SupplierResource::getUrl('compra-detalle', [
                        'record' => $this->getRecord(),
                        'purchase' => $record->id,
                    ])),
            ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            SupplierResource::getUrl('index') => 'Proveedores',
            SupplierResource::getUrl('edit', ['record' => $this->record]) => $this->record->name,
            SupplierResource::getUrl('compras', ['record' => $this->record]) => 'Compras',
        ];
    }
}
