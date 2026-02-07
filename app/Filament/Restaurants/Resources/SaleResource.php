<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\SaleResource\Pages;
use App\Models\CashRegisterMovement;
use App\Models\Sale;
use App\Traits\ManjoStockProductos;
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
                // ACCIÓN DE ANULAR
                Tables\Actions\Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    // 1. Primera confirmación estándar
                    ->requiresConfirmation()
                    ->modalHeading('Anular Venta')
                    ->modalDescription('¿Está seguro de que desea anular esta venta? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Confirmar Anulación')

                    // 2. Formulario dinámico para el stock
                    ->form(function (Sale $record) {
                        // Verificamos si hay productos en el detalle que tengan control de stock
                        $tieneProductosConStock = $record->details()->whereHas('product', function ($query) {
                            $query->where('control_stock', true);
                        })->exists();

                        if ($tieneProductosConStock) {
                            return [
                                Forms\Components\Toggle::make('restablecer_stock')
                                    ->label('¿Desea restablecer el stock de los productos?')
                                    ->helperText('Se sumará la cantidad vendida nuevamente al inventario.')
                                    ->default(true)
                                    ->onColor('success') // <-- ESTE ES EL MÉTODO CORRECTO
                                    ->offColor('danger'), // Opcional: para que se vea rojo si está en "No"
                            ];
                        }

                        return [];
                    })

                    ->hidden(fn(Sale $record) => $record->status === 'anulado')

                    // En SaleResource.php
                    ->action(function (Sale $record, array $data) {
                        $record->load(['details.product.unit', 'details.variant']);

                        DB::beginTransaction();
                        try {
                            $record->update(['status' => 'anulado']);

                            if (isset($data['restablecer_stock']) && $data['restablecer_stock']) {
                                $stockManager = new class {
                                    use \App\Traits\ManjoStockProductos;
                                    public function ejecutarReverseVenta($sale)
                                    {
                                        $this->reverseVenta($sale);
                                    }
                                };

                                foreach ($record->details as $item) {
                                    if ($item->product?->control_stock) {
                                        // 2. Recuperamos el almacén del Kardex (como ya hacíamos)
                                        $kardexEntry = \App\Models\Kardex::where('modelo_type', get_class($item))
                                            ->where('modelo_id', $item->id)
                                            ->whereIn('tipo_movimiento', ['Venta', 'salida'])
                                            ->first();

                                        if ($kardexEntry && $kardexEntry->warehouse) {
                                            $item->setRelation('warehouse', $kardexEntry->warehouse);
                                        }
                                        if (!$item->unit) {
                                            $item->setRelation('unit', $item->product->unit);
                                        }
                                    }
                                }
                                $stockManager->ejecutarReverseVenta($record);
                            }

                            $movimientosCaja = CashRegisterMovement::where('referencia_type', get_class($record))
                                ->where('referencia_id', $record->id)
                                ->where('status', 'aprobado')
                                ->get();

                            foreach ($movimientosCaja as $movimiento) {
                                $movimiento->update([
                                    'status' => 'anulado',
                                    'description' => $movimiento->description . ' (ANULADO)'
                                ]);
                            }

                            DB::commit();
                            Notification::make()->title('Venta Anulada correctamente')->success()->send();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
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
