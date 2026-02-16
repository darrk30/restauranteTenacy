<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\SaleResource\Pages;
use App\Models\CashRegisterMovement;
use App\Models\Sale;
use App\Models\SessionCashRegister;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Historial de Ventas';

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
        // Obtenemos la última apertura una sola vez para optimizar
        $sesionAbierta = SessionCashRegister::where('user_id', Auth::id())
            ->whereNull('closed_at')
            ->first();

        return $table
            ->columns([
                TextColumn::make('fecha_emision')
                    ->label('Fecha/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn(Sale $record): string => ($sesionAbierta && $record->created_at >= $sesionAbierta->created_at)
                        ? 'Turno Abierto' : 'Histórico')
                    ->color(fn(Sale $record): string => ($sesionAbierta && $record->created_at >= $sesionAbierta->created_at)
                        ? 'success' : 'gray'),

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
                    ->money('PEN')
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
                // FILTRO POR DEFECTO: Solo turno actual
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DateTimePicker::make('fecha_desde')->label('Desde')->hourMode(12)->displayFormat('d/m/y h:i A')->seconds(false)->default($sesionAbierta ? $sesionAbierta->opened_at : now()->startOfDay()),
                        DatetimePicker::make('fecha_hasta')->label('Hasta')->hourMode(12)->displayFormat('d/m/y h:i A')->seconds(false)->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['fecha_desde'],
                                fn(Builder $query, $date) => $query->where('created_at', '>=', $date),
                            )
                            ->when(
                                $data['fecha_hasta'],
                                fn(Builder $query, $date) => $query->where('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        // Mostramos formato amigable incluyendo la hora
                        if ($data['fecha_desde'] ?? null) {
                            $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['fecha_desde'])->format('d/m/Y h:i A');
                        }
                        if ($data['fecha_hasta'] ?? null) {
                            $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['fecha_hasta'])->format('d/m/Y h:i A');
                        }
                        return $indicators;
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completado' => 'Completado',
                        'anulado' => 'Anulado',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anular Venta')
                    ->modalDescription('¿Está seguro de que desea anular esta venta?')
                    ->hidden(
                        fn(Sale $record) =>
                        $record->status === 'anulado' ||
                            !$sesionAbierta ||
                            $record->created_at < $sesionAbierta->created_at
                    )
                    ->form(function (Sale $record) {
                        $detalles = $record->details()->with([
                            'product',
                            'promotion.promotionproducts.product',
                            'promotion.promotionproducts.variant'
                        ])->get();

                        $hayStockParaRestablecer = $detalles->contains(function ($detalle) {
                            if ($detalle->promotion_id && $detalle->promotion) {
                                return $detalle->promotion->promotionproducts->contains(function ($hijo) {
                                    $prod = $hijo->product;
                                    $varProd = $hijo->variant?->product;
                                    return ($prod && $prod->control_stock) || ($varProd && $varProd->control_stock);
                                });
                            }
                            return $detalle->product && $detalle->product->control_stock;
                        });

                        return $hayStockParaRestablecer ? [
                            Forms\Components\Toggle::make('restablecer_stock')
                                ->label('¿Desea restablecer el stock?')
                                ->helperText('Se devolverán los productos y componentes de promociones al inventario.')
                                ->default(true)
                                ->onColor('success')
                                ->offColor('danger'),
                        ] : [];
                    })
                    ->action(function (Sale $record, array $data) {
                        DB::beginTransaction();
                        try {
                            $record->update(['status' => 'anulado']);

                            if ($data['restablecer_stock'] ?? false) {

                                // --- CLASE ANÓNIMA ADAPTADA A TU TRAIT REAL ---
                                $stockManager = new class {
                                    use \App\Traits\ManjoStockProductos;

                                    // Exponemos 'processItem' para hacer una ENTRADA de devolución
                                    public function restaurarStock($item, $comprobante, $movimiento)
                                    {
                                        // Llamamos a processItem con tipo 'entrada' para que SUME al stock
                                        $this->processItem($item, 'entrada', $comprobante, $movimiento);
                                    }
                                };
                                // ------------------------------------------------

                                $referencia = "{$record->serie}-{$record->correlativo}";

                                // Almacén por defecto (o lógica personalizada si la tienes)
                                $almacenDefault = \App\Models\Warehouse::where('restaurant_id', $record->restaurant_id)->first();

                                if (!$almacenDefault) {
                                    throw new \Exception("No se encontró un almacén para devolver el stock.");
                                }

                                foreach ($record->details as $detalle) {

                                    // --- CASO 1: ES UNA PROMOCIÓN ---
                                    if ($detalle->promotion_id) {
                                        $promo = \App\Models\Promotion::with('promotionproducts.product', 'promotionproducts.variant')
                                            ->find($detalle->promotion_id);

                                        if ($promo) {
                                            foreach ($promo->promotionproducts as $hijo) {
                                                $productoHijo = $hijo->product;

                                                if ($productoHijo && $productoHijo->control_stock) {

                                                    $cantidadTotal = $detalle->cantidad * $hijo->quantity;

                                                    // Objeto virtual
                                                    $itemVirtual = new \App\Models\SaleDetail([
                                                        'product_id' => $hijo->product_id,
                                                        'variant_id' => $hijo->variant_id,
                                                        'cantidad'   => $cantidadTotal,
                                                        // Importante: ID para que el Kardex guarde referencia
                                                        'id'         => $detalle->id,
                                                    ]);

                                                    $itemVirtual->setRelation('product', $productoHijo);
                                                    $itemVirtual->setRelation('variant', $hijo->variant);
                                                    $itemVirtual->setRelation('warehouse', $almacenDefault);
                                                    $itemVirtual->setRelation('unit', $productoHijo->unit);

                                                    $stockManager->restaurarStock(
                                                        $itemVirtual,
                                                        $referencia,
                                                        "Anulación: {$promo->name} (Componente)"
                                                    );
                                                }
                                            }
                                        }
                                    }
                                    // --- CASO 2: ES UN PRODUCTO NORMAL ---
                                    elseif ($detalle->product_id && $detalle->product && $detalle->product->control_stock) {

                                        $detalle->setRelation('warehouse', $almacenDefault);

                                        if (!$detalle->relationLoaded('unit')) {
                                            $detalle->setRelation('unit', $detalle->product->unit);
                                        }

                                        $stockManager->restaurarStock(
                                            $detalle,
                                            $referencia,
                                            "Anulación: {$detalle->product_name}"
                                        );
                                    }
                                }
                            }

                            // Anulación de Movimientos de Caja
                            $movimientosCaja = CashRegisterMovement::where('referencia_type', Sale::class)
                                ->where('referencia_id', $record->id)
                                ->where('status', 'aprobado')
                                ->get();

                            foreach ($movimientosCaja as $movimiento) {
                                $movimiento->update(['status' => 'anulado']);
                            }

                            DB::commit();
                            Notification::make()->title('Venta anulada correctamente')->success()->send();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()->title('Error al anular')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // Tables\Actions\Action::make('anular')
                //     ->label('Anular')
                //     ->icon('heroicon-o-x-circle')
                //     ->color('danger')
                //     ->requiresConfirmation()
                //     ->modalHeading('Anular Venta')
                //     ->modalDescription('¿Está seguro de que desea anular esta venta?')
                //     // RESTRICCIÓN DE SEGURIDAD
                //     ->hidden(
                //         fn(Sale $record) =>
                //         $record->status === 'anulado' ||
                //             !$sesionAbierta ||
                //             $record->created_at < $sesionAbierta->created_at
                //     )
                //     ->form(function (Sale $record) {
                //         $tieneProductosConStock = $record->details()->whereHas('product', fn($q) => $q->where('control_stock', true))->exists();
                //         return $tieneProductosConStock ? [
                //             Forms\Components\Toggle::make('restablecer_stock')
                //                 ->label('¿Desea restablecer el stock?')
                //                 ->default(true)
                //                 ->onColor('success')
                //                 ->offColor('danger'),
                //         ] : [];
                //     })
                //     ->action(function (Sale $record, array $data) {
                //         DB::beginTransaction();
                //         try {
                //             $record->update(['status' => 'anulado']);

                //             // Lógica de reversión de stock
                //             if ($data['restablecer_stock'] ?? false) {
                //                 $stockManager = new class {
                //                     use \App\Traits\ManjoStockProductos;
                //                     public function ejecutarReverseVenta($sale)
                //                     {
                //                         $this->reverseVenta($sale);
                //                     }
                //                 };

                //                 foreach ($record->details as $item) {
                //                     if ($item->product?->control_stock) {
                //                         $kardexEntry = Kardex::where('modelo_type', get_class($item))
                //                             ->where('modelo_id', $item->id)
                //                             ->whereIn('tipo_movimiento', ['Venta', 'salida'])
                //                             ->first();
                //                         if ($kardexEntry?->warehouse) $item->setRelation('warehouse', $kardexEntry->warehouse);
                //                         if (!$item->unit) $item->setRelation('unit', $item->product->unit);
                //                     }
                //                 }
                //                 $stockManager->ejecutarReverseVenta($record);
                //             }

                //             // Anulación de movimientos de caja
                //             // 1. Obtener los movimientos
                //             $movimientos = CashRegisterMovement::where('referencia_type', get_class($record))
                //                 ->where('referencia_id', $record->id)
                //                 ->where('status', 'aprobado')
                //                 ->get();

                //             // 2. Iterar y actualizar individualmente
                //             foreach ($movimientos as $movimiento) {
                //                 // Al usar este update() sobre la instancia, SÍ se dispara el Observer
                //                 $movimiento->update([
                //                     'status' => 'anulado'
                //                 ]);
                //             }

                //             DB::commit();
                //             Notification::make()->title('Venta Anulada')->success()->send();
                //         } catch (\Exception $e) {
                //             DB::rollBack();
                //             Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                //         }
                //     }),

                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información del Comprobante')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('tipo_comprobante')->badge(),
                            TextEntry::make('serie'),
                            TextEntry::make('correlativo'),
                            TextEntry::make('fecha_emision')->dateTime(),
                            TextEntry::make('status')->badge()
                                ->color(fn($state) => match ($state) {
                                    'completado' => 'success',
                                    'anulado' => 'danger',
                                    default => 'gray'
                                }),
                        ]),
                    ]),
                Section::make('Detalle de Productos')
                    ->schema([
                        RepeatableEntry::make('details')
                            ->schema([
                                TextEntry::make('product_name')->label('Producto'),
                                TextEntry::make('cantidad'),
                                TextEntry::make('precio_unitario')->money('PEN'),
                                TextEntry::make('subtotal')->money('PEN'),
                            ])->columns(4),
                    ]),
                Section::make('Totales')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('op_gravada')->money('PEN'),
                            TextEntry::make('monto_igv')->label('IGV')->money('PEN'),
                            TextEntry::make('total')->money('PEN')->weight('bold')->color('primary'),
                        ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'view' => Pages\ViewSale::route('/{record}'),
        ];
    }
}
