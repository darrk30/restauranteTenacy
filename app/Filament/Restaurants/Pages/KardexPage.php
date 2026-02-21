<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Kardex;
use App\Models\Variant;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Set;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class KardexPage extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Kardex';
    protected static ?string $title = 'Kardex Valorizado';
    protected static string $view = 'filament.kardex.kardex-page';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 30;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Kardex::query()
                    ->with(['product', 'variant'])
                    ->when(!$this->areFiltersActive(), fn($q) => $q->whereRaw('1=0'))
                    ->orderBy('id', 'desc')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('comprobante')
                    ->label('Documento/Motivo')
                    ->description(fn($record) => $this->getOrigenLabel($record->modelo_type))
                    ->searchable(),

                // --- GRUPO ENTRADAS ---
                ColumnGroup::make('ENTRADAS')
                    ->columns([
                        TextColumn::make('cant_in')
                            ->label('Cant.')
                            ->getStateUsing(fn($record) => $record->cantidad > 0 ? $record->cantidad : 0)
                            ->formatStateUsing(fn($state) => $state <= 0 ? '-' : number_format($state, 3))
                            ->color(fn($state) => $state === '-' ? 'gray' : 'success')
                            ->alignRight(),

                        TextColumn::make('costo_u_in')
                            ->label('Costo U.')
                            ->getStateUsing(fn($record) => $record->cantidad > 0 ? $record->costo_unitario : 0)
                            ->formatStateUsing(fn($state) => $state <= 0 ? '-' : 'S/ ' . number_format($state, 2))
                            ->color(fn($state) => $state === '-' ? 'gray' : null)
                            ->alignRight(),

                        TextColumn::make('total_in')
                            ->label('Total')
                            ->getStateUsing(fn($record) => $record->cantidad > 0 ? ($record->cantidad * $record->costo_unitario) : 0)
                            ->formatStateUsing(fn($state) => $state <= 0 ? '-' : 'S/ ' . number_format($state, 2))
                            ->color(fn($state) => $state === '-' ? 'gray' : null)
                            ->alignRight(),
                    ]),

                // --- GRUPO SALIDAS ---
                ColumnGroup::make('SALIDAS')
                    ->columns([
                        TextColumn::make('cant_out')
                            ->label('Cant.')
                            ->getStateUsing(fn($record) => $record->cantidad < 0 ? abs($record->cantidad) : 0)
                            ->formatStateUsing(fn($state) => $state <= 0 ? '-' : number_format($state, 3))
                            ->color(fn($state) => $state === '-' ? 'gray' : 'danger')
                            ->alignRight(),

                        TextColumn::make('costo_u_out')
                            ->label('Costo U.')
                            ->getStateUsing(fn($record) => $record->cantidad < 0 ? $record->costo_unitario : 0)
                            ->formatStateUsing(fn($state) => $state <= 0 ? '-' : 'S/ ' . number_format($state, 2))
                            ->color(fn($state) => $state === '-' ? 'gray' : null)
                            ->alignRight(),

                        TextColumn::make('total_out')
                            ->label('Total')
                            ->getStateUsing(fn($record) => $record->cantidad < 0 ? (abs($record->cantidad) * $record->costo_unitario) : 0)
                            ->formatStateUsing(fn($state) => $state <= 0 ? '-' : 'S/ ' . number_format($state, 2))
                            ->color(fn($state) => $state === '-' ? 'gray' : null)
                            ->alignRight(),
                    ]),

                // --- GRUPO SALDOS (EXISTENCIAS) ---
                ColumnGroup::make('SALDO FINAL (VALORIZADO)')
                    ->columns([
                        TextColumn::make('stock_restante')
                            ->label('Stock')
                            ->formatStateUsing(fn($state) => $state == 0 ? '0.000' : number_format($state, 3))
                            ->weight('bold')
                            ->alignRight(),

                        TextColumn::make('costo_promedio')
                            ->label('Costo Prom.')
                            ->getStateUsing(function ($record) {
                                return $record->stock_restante > 0
                                    ? ($record->saldo_valorizado / $record->stock_restante)
                                    : 0;
                            })
                            ->formatStateUsing(fn($state) => $state <= 0 ? 'S/ 0.00' : 'S/ ' . number_format($state, 2))
                            ->color('info')
                            ->alignRight(),

                        TextColumn::make('saldo_valorizado')
                            ->label('Valor Total')
                            ->money('PEN')
                            ->weight('bold')
                            ->alignRight(),
                    ]),
            ])
            ->filters([
                Filter::make('producto_variante')
                    ->form([
                        Select::make('product_id')
                            ->label('Producto')
                            ->relationship(
                                'product',
                                'name',
                                // 🟢 Filtramos la consulta para que solo traiga productos con control_stock = 1
                                fn(Builder $query) => $query->where('control_stock', true)
                            )
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(fn(Set $set) => $set('variant_id', null))
                            ->required(),

                        Select::make('variant_id')
                            ->label('Variante / Almacén')
                            ->options(function (callable $get) {
                                $productId = $get('product_id');
                                if (!$productId) return [];
                                return Variant::where('product_id', $productId)
                                    ->get()
                                    ->mapWithKeys(fn($v) => [$v->id => $v->full_name]);
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->query(function ($query, $data) {
                        return $query
                            ->when($data['product_id'], fn($q) => $q->where('product_id', $data['product_id']))
                            ->when($data['variant_id'], fn($q) => $q->where('variant_id', $data['variant_id']));
                    }),
            ]);
    }

    private function getOrigenLabel(?string $modelType): string
    {
        if (!$modelType) return 'Mov. Manual';
        $type = class_basename($modelType);
        return [
            'StockAdjustmentItem' => 'Ajuste de Stock',
            'PurchaseDetail'      => 'Compra Recibida',
            'SaleDetail'          => 'Venta Realizada',
            'Variant'             => 'Stock Inicial',
        ][$type] ?? $type;
    }

    public function areFiltersActive(): bool
    {
        return !empty($this->tableFilters['producto_variante']['product_id']);
    }

    public function getAppliedFilters(): array
    {
        return $this->tableFilters ?? [];
    }
}
