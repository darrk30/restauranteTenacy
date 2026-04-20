<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Pages\Facturacion\Comprobantes;
use App\Filament\Restaurants\Resources\SaleResource\Pages;
use App\Models\CashRegisterMovement;
use App\Models\Sale;
use App\Models\SessionCashRegister;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
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
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Historial de Ventas';
    protected static ?string $navigationGroup = 'Caja';
    protected static ?string $pluralModelLabel = 'Historial de Ventas';
    protected static ?int $navigationSort = 6;

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
                    ->summarize(
                        Sum::make()
                            ->label('Total Completado')
                            ->query(fn($query) => $query->where('status', 'completado'))
                    ),

                TextColumn::make('status')
                    ->label('Estado Local')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'completado' => 'success',
                        'anulado' => 'danger',
                        'anulada_por_nota' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst($state)),

                TextColumn::make('status_sunat')
                    ->label('Estado SUNAT')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'aceptado' => 'success',
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
                ActionGroup::make([
                    Tables\Actions\Action::make('print')
                        ->label('Reimprimir Ticket')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->visible(fn() => Auth::user()->can('reimprimir_ticket_rest'))
                        ->url(fn(Sale $record) => route('sale.ticket.print', $record), shouldOpenInNewTab: true),

                    Tables\Actions\Action::make('anular')
                        ->label('Anular')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Anular Venta')
                        ->visible(fn() => Auth::user()->can('anular_venta_rest'))
                        ->modalDescription('¿Está seguro de que desea anular esta venta?')
                        ->hidden(
                            fn(Sale $record) =>
                            $record->status === 'anulado' ||
                                !$sesionAbierta ||
                                $record->created_at < $sesionAbierta->created_at
                        )
                        ->form(function (Sale $record) {
                            if ($record->status_sunat === 'aceptado') {
                                return [];
                            }

                            // Detectar si hay algo que controle stock
                            $hayStock = $record->details()->whereHas('product', fn($q) => $q->where('control_stock', true))->exists() ||
                                $record->details()->whereNotNull('promotion_id')->exists();

                            return $hayStock ? [
                                Toggle::make('restablecer_stock')
                                    ->label('¿Desea restablecer el stock?')
                                    ->helperText('Se generarán movimientos de entrada para devolver los productos.')
                                    ->default(true) // Por defecto true para asegurar el inventario
                                    ->onColor('success')
                                    ->offColor('gray'),
                            ] : [];
                        })
                        ->action(function (Sale $record, array $data) {
                            // 🛡️ VALIDACIÓN SUNAT
                            if ($record->status_sunat === 'aceptado') {
                                Notification::make()
                                    ->warning()
                                    ->title('Comprobante aceptado en SUNAT')
                                    ->body('Este comprobante ya fue aceptado. Debe anular mediante Comunicación de Baja o Nota de Crédito.')
                                    ->persistent()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('ir_a_anular')
                                            ->label('Ir a Comprobantes')
                                            ->color('primary')
                                            ->button()
                                            ->url(fn() => Comprobantes::getUrl()),
                                    ])
                                    ->send();
                                return;
                            }

                            DB::beginTransaction();
                            try {
                                // 1. Actualizar estado de la venta
                                $record->update([
                                    'status' => 'anulado',
                                    'status_sunat' => 'anulado',
                                    'user_actualiza_id' => Auth::id(),
                                    'message' => 'Venta anulada internamente.'
                                ]);

                                // 2. 🟢 RESTABLECER STOCK USANDO EL TRAIT
                                if ($data['restablecer_stock'] ?? false) {
                                    $stockManager = new class {
                                        use \App\Traits\ManjoStockProductos;
                                    };

                                    // Llamada limpia al reverso (el trait hace todo el trabajo)
                                    $stockManager->reverseVenta(
                                        sale: $record,
                                        movimiento: "Venta Anulada: {$record->serie}-{$record->correlativo}"
                                    );
                                }

                                // 3. Lógica de Caja (se mantiene igual)
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
                    Tables\Actions\ViewAction::make(),
                ]),
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
                            TextEntry::make('status')
                                ->label('Estado Local')
                                ->badge()
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
