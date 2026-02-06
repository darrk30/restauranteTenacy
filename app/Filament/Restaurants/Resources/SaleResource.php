<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\SaleResource\Pages;
use App\Models\Sale;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Support\Facades\DB;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Historial de Ventas';

    // 1. DESHABILITAR CREACIÓN Y EDICIÓN DESDE EL RECURSO
    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_emision')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn(Sale $record): string => "{$record->serie}-{$record->correlativo}")
                    ->searchable(['serie', 'correlativo']),

                TextColumn::make('tipo_comprobante')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Factura' => 'warning',
                        'Boleta' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('nombre_cliente')
                    ->label('Cliente')
                    ->searchable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN') // O tu moneda local
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'completado' => 'success',
                        'anulado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst($state)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completado' => 'Completado',
                        'anulado' => 'Anulado',
                    ]),
                Tables\Filters\Filter::make('fecha_emision')
                    ->form([
                        Forms\Components\DatePicker::make('desde'),
                        Forms\Components\DatePicker::make('hasta'),
                    ])
                    ->query(
                        fn($query, array $data) => $query
                            ->when($data['desde'], fn($q) => $q->whereDate('fecha_emision', '>=', $data['desde']))
                            ->when($data['hasta'], fn($q) => $q->whereDate('fecha_emision', '<=', $data['hasta']))
                    )
            ])
            ->actions([
                // ACCIÓN DE ANULAR
                Tables\Actions\Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn(Sale $record) => $record->status === 'anulado') // Ocultar si ya está anulada
                    ->action(function (Sale $record) {
                        DB::beginTransaction();
                        try {
                            // 1. Cambiar estado de la venta
                            $record->update(['status' => 'anulado']);

                            // 2. Aquí llamarías a tu lógica de reversión (Devolver stock, anular movimientos de caja)
                            // Por ejemplo:
                            // $record->cashMovements()->delete(); 
                            // (Si tu observer de CashRegisterMovement recalcula el saldo al eliminar, esto funcionará)

                            DB::commit();
                            Notification::make()->title('Venta Anulada correctamente')->success()->send();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()->title('Error al anular')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información del Comprobante')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('tipo_comprobante')->badge(),
                                TextEntry::make('serie')->label('Serie'),
                                TextEntry::make('correlativo')->label('Número'),
                                TextEntry::make('fecha_emision')->dateTime(),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'completado' => 'success',
                                        'anulado' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),

                Section::make('Datos del Cliente')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('nombre_cliente')->label('Nombre/Razón Social'),
                                TextEntry::make('numero_documento')->label('DNI/RUC'),
                            ]),
                    ]),

                Section::make('Detalle de Productos')
                    ->schema([
                        RepeatableEntry::make('details') // Asumiendo que la relación en el modelo Sale se llama details
                            ->label('')
                            ->schema([
                                TextEntry::make('product_name')->label('Producto'),
                                TextEntry::make('cantidad')->label('Cant.'),
                                TextEntry::make('precio_unitario')->money('PEN')->label('P. Unit'),
                                TextEntry::make('subtotal')->money('PEN')->label('Total'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('Totales')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('op_gravada')->label('Op. Gravada')->money('PEN'),
                                TextEntry::make('monto_igv')->label('IGV (18%)')->money('PEN'),
                                TextEntry::make('total')->label('Total a Pagar')->money('PEN')
                                    ->weight('bold')
                                    ->color('primary'),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            // Solo dejamos index y view si quieres ver el detalle
            'view' => Pages\ViewSale::route('/{record}'),
        ];
    }
}
