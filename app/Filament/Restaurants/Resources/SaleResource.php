<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;
use Carbon\Carbon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use App\Traits\ManjoStockProductos;
use Exception;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use App\Filament\Restaurants\Resources\SaleResource\Pages\ListSales;
use App\Filament\Restaurants\Resources\SaleResource\Pages\ViewSale;
use App\Filament\Restaurants\Resources\SaleResource\Pages;
use App\Models\CashRegisterMovement;
use App\Models\Sale;
use App\Models\Kardex;
use App\Models\SessionCashRegister;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

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
                    ->summarize(Sum::make()->label('Total')),

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
                Filter::make('created_at')
                    ->schema([
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
                            $indicators[] = 'Desde: ' . Carbon::parse($data['fecha_desde'])->format('d/m/Y h:i A');
                        }
                        if ($data['fecha_hasta'] ?? null) {
                            $indicators[] = 'Hasta: ' . Carbon::parse($data['fecha_hasta'])->format('d/m/Y h:i A');
                        }
                        return $indicators;
                    }),

                SelectFilter::make('status')
                    ->options([
                        'completado' => 'Completado',
                        'anulado' => 'Anulado',
                    ]),
            ])
            ->recordActions([
                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anular Venta')
                    ->modalDescription('¿Está seguro de que desea anular esta venta?')
                    // RESTRICCIÓN DE SEGURIDAD
                    ->hidden(
                        fn(Sale $record) =>
                        $record->status === 'anulado' ||
                            !$sesionAbierta ||
                            $record->created_at < $sesionAbierta->created_at
                    )
                    ->schema(function (Sale $record) {
                        $tieneProductosConStock = $record->details()->whereHas('product', fn($q) => $q->where('control_stock', true))->exists();
                        return $tieneProductosConStock ? [
                            Toggle::make('restablecer_stock')
                                ->label('¿Desea restablecer el stock?')
                                ->default(true)
                                ->onColor('success')
                                ->offColor('danger'),
                        ] : [];
                    })
                    ->action(function (Sale $record, array $data) {
                        DB::beginTransaction();
                        try {
                            $record->update(['status' => 'anulado']);

                            // Lógica de reversión de stock
                            if ($data['restablecer_stock'] ?? false) {
                                $stockManager = new class {
                                    use ManjoStockProductos;
                                    public function ejecutarReverseVenta($sale)
                                    {
                                        $this->reverseVenta($sale);
                                    }
                                };

                                foreach ($record->details as $item) {
                                    if ($item->product?->control_stock) {
                                        $kardexEntry = Kardex::where('modelo_type', get_class($item))
                                            ->where('modelo_id', $item->id)
                                            ->whereIn('tipo_movimiento', ['Venta', 'salida'])
                                            ->first();
                                        if ($kardexEntry?->warehouse) $item->setRelation('warehouse', $kardexEntry->warehouse);
                                        if (!$item->unit) $item->setRelation('unit', $item->product->unit);
                                    }
                                }
                                $stockManager->ejecutarReverseVenta($record);
                            }

                            // Anulación de movimientos de caja
                            // 1. Obtener los movimientos
                            $movimientos = CashRegisterMovement::where('referencia_type', get_class($record))
                                ->where('referencia_id', $record->id)
                                ->where('status', 'aprobado')
                                ->get();

                            // 2. Iterar y actualizar individualmente
                            foreach ($movimientos as $movimiento) {
                                // Al usar este update() sobre la instancia, SÍ se dispara el Observer
                                $movimiento->update([
                                    'status' => 'anulado'
                                ]);
                            }

                            DB::commit();
                            Notification::make()->title('Venta Anulada')->success()->send();
                        } catch (Exception $e) {
                            DB::rollBack();
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
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
            'index' => ListSales::route('/'),
            'view' => ViewSale::route('/{record}'),
        ];
    }
}
