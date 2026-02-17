<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Kardex;
use App\Models\Variant;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Set;
use Filament\Tables\Filters\Filter;

class KardexPage extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationLabel = 'Kardex';
    protected static ?string $navigationGroup = 'Inventarios';
    protected static ?string $title = 'Reporte de Kardex';
    protected static string $view = 'filament.kardex.kardex-page';

    public ?int $productId = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Kardex::query()
                    ->with(['product', 'variant'])
                    ->when(! $this->areFiltersActive(), fn($q) => $q->whereRaw('0=1'))
                    ->when(
                        $this->productId,
                        fn($q) => $q->where('product_id', $this->productId)
                    )->orderBy('id', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('comprobante')
                    ->label('Doc. Asociado')
                    ->sortable()
                    ->wrap(),


                Tables\Columns\TextColumn::make('modelo_type')
                    ->label('Origen')
                    ->formatStateUsing(function ($state) {

                        if (! $state) return '—';

                        $type = class_basename($state);

                        // Mapeo bonito en español
                        return [
                            'StockAdjustmentItem' => 'Ajuste de stock',
                            'ProductVariants' => 'Productos',
                            'PurchaseDetail'      => 'Compra',
                            'SaleDetail'            => 'Venta',
                        ][$type] ?? $type;  // fallback por si aparece otro
                    })
                    ->badge()
                    ->color(function ($state) {

                        if (! $state) return 'gray';

                        return match (class_basename($state)) {
                            'StockAdjustmentItem' => 'warning',
                            'PurchaseDetail'      => 'success',
                            'ProductVariants'      => 'success',
                            'SaleDetail'            => 'danger',
                            default               => 'gray',
                        };
                    }),

                Tables\Columns\TextColumn::make('tipo_movimiento')
                    ->label('Movimiento')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'entrada' => 'success',
                        'Stock Inicial' => 'success',
                        'salida' => 'danger',
                        'compra-anulada' => 'danger',
                        'ajuste-anulado' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('entrada')
                    ->label('Entrada')
                    ->getStateUsing(fn($record) => $record->cantidad > 0 ? $record->cantidad : 0)
                    ->numeric(3)
                    ->color('success'),

                Tables\Columns\TextColumn::make('salida')
                    ->label('Salida')
                    ->getStateUsing(fn($record) => $record->cantidad < 0 ? $record->cantidad : 0)
                    ->numeric(3)
                    ->color('danger'),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->getStateUsing(fn($record) => $record->stock_restante)
                    ->numeric(3),
            ])
            ->filters([
                Filter::make('producto_variante')
                    ->form([
                        Select::make('product_id')
                            ->label('Producto')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('variant_id', null);
                            })
                            ->placeholder('Todos los productos'),

                        Select::make('variant_id')
                            ->label('Variante')
                            ->options(function (callable $get) {
                                $productId = $get('product_id');
                                if (!$productId) {
                                    return [];
                                }
                                return Variant::where('product_id', $productId)
                                    ->where('status', 'activo')
                                    ->get()
                                    ->mapWithKeys(fn($v) => [$v->id => $v->full_name]);
                            })
                            ->searchable()
                            ->placeholder('Todas las variantes'),
                    ])
                    ->query(function ($query, $data) {
                        return $query
                            ->when($data['product_id'] ?? null, fn($q) => $q->where('product_id', $data['product_id']))
                            ->when($data['variant_id'] ?? null, fn($q) => $q->where('variant_id', $data['variant_id']));
                    }),
                Filter::make('fecha')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(
                        fn($query, $data) =>
                        $query
                            ->when($data['desde'] ?? null, fn($q) => $q->whereDate('created_at', '>=', $data['desde']))
                            ->when($data['hasta'] ?? null, fn($q) => $q->whereDate('created_at', '<=', $data['hasta']))
                    ),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No hay registros para mostrar')
            ->emptyStateDescription('Aplica filtros o cambia los parámetros de búsqueda.')
            ->emptyStateIcon('heroicon-o-clipboard-document')
            ->actions([])
            ->bulkActions([]);
    }

    public function areFiltersActive(): bool
    {
        $filters = $this->tableFilters ?? [];

        foreach ($filters as $state) {
            if (! empty(array_filter((array) $state))) {
                return true;
            }
        }

        return false;
    }


    public function getAppliedFilters(): array
    {
        return $this->tableFilters ?? [];
    }
}
