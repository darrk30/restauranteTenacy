<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Variant;
use App\Models\WarehouseStock;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class WarehouseStockPage extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Stock General';
    protected static ?string $navigationGroup = 'Inventarios';

    protected static string $view = 'filament.warehouse.pages.warehouse-stock-page';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Variant::query()
                    ->with([
                        'product',
                        'values.attribute',
                        'stocks.warehouse',
                    ])
                    ->where('status', 'activo')
                    ->whereHas('product', function ($q) {
                        $q->where('control_stock', true); // 🔥 Solo productos con control de stock
                    })
                    ->whereHas('stocks') // 🔥 Solo variantes con registros en warehouse_stocks
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Variante')
                    ->searchable(),

                Tables\Columns\TextColumn::make('min_stock_promedio')
                    ->label('Stock Min. Promedio')
                    ->getStateUsing(
                        fn($record) =>
                        $record->stocks->avg('min_stock') ? number_format($record->stocks->avg('min_stock'), 2) : 0
                    )
                    ->badge()
                    ->color('warning'),


                Tables\Columns\TextColumn::make('stock_total')
                    ->label('Stock Total')
                    ->getStateUsing(
                        fn($record) =>
                        $record->stocks->sum(fn($s) => $s->stock_real ?? $s->stock ?? 0)
                    )
                    ->icon('heroicon-o-eye') // icono bonito opcional
                    ->color('primary')
                    ->action(
                        Action::make('ver_stock')
                            ->label('Detalles')
                            ->modalHeading(fn($record) => "STOCK POR ALMACEN")
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                            ->modalContent(function ($record) {
                                $stocks = $record->stocks;
                                return view('filament.warehouse.pages.stock-modal', [
                                    'variant' => $record,
                                    'stocks' => $stocks,
                                ]);
                            })
                    ),
                Tables\Columns\TextColumn::make('product.unit.name')
                    ->label('Unidad')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('info'),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
