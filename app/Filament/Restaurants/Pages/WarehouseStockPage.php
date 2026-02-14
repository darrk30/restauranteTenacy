<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use App\Models\Variant;
use App\Models\WarehouseStock;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseStockPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Existencias';

    protected static string | \UnitEnum | null $navigationGroup = 'Inventarios';

    protected static ?string $title = 'Existencias de Almacén';

    protected string $view = 'filament.warehouse.pages.existencias';

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
                        $q->where('status', 'activo')->where('control_stock', true);
                    })
                    ->whereHas('stocks')
            )
            ->columns([
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable(),

                TextColumn::make('full_name')
                    ->label('Variante')
                    ->searchable(),

                TextColumn::make('min_stock_promedio')
                    ->label('Stock Min. Promedio')
                    ->getStateUsing(
                        fn($record) =>
                        $record->stocks->avg('min_stock') ? number_format($record->stocks->avg('min_stock'), 2) : 0
                    )
                    ->badge()
                    ->color('warning'),


                TextColumn::make('stock_total')
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
                TextColumn::make('product.unit.name')
                    ->label('Unidad')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('info'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
