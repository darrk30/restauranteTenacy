<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Variant;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseStockPage extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Existencias';
    protected static ?string $navigationGroup = 'Inventarios';
    protected static ?string $title = 'Existencias de Almacén';

    // Si tu vista existencias.blade.php solo tenía la tabla, puedes borrar esta línea
    // y usar el layout por defecto de Filament. Si tienes contenido extra, mantenla.
    protected static string $view = 'filament.warehouse.pages.existencias';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Variant::query()
                    ->with(['product.unit', 'stock']) // Cargamos la relación única 'stock'
                    ->where('status', 'activo')
                    ->whereHas('product', function ($q) {
                        $q->where('status', 'activo')
                            ->where('control_stock', true);
                    })
                    // Opcional: Solo mostrar si tiene registro de stock creado
                    ->whereHas('stock')
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Variante')
                    ->searchable(),

                // 1. STOCK MINIMO (Directo de la tabla warehouse_stocks)
                Tables\Columns\TextColumn::make('stock.min_stock')
                    ->label('Stock Mínimo')
                    ->numeric()
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                // 2. STOCK REAL (Directo y único)
                Tables\Columns\TextColumn::make('stock.stock_real')
                    ->label('Stock Actual')
                    ->numeric()
                    ->sortable()
                    ->weight('bold')
                    ->color(
                        fn($record) => ($record->stock?->stock_real <= $record->stock?->min_stock) ? 'danger' : 'primary'
                    )
                    ->description(
                        fn($record) => ($record->stock?->stock_real <= $record->stock?->min_stock) ? 'Stock Bajo' : null
                    ),

                // 3. UNIDAD
                Tables\Columns\TextColumn::make('product.unit.name')
                    ->label('Unidad')
                    ->badge()
                    ->color('info')
                    ->sortable(),
            ])
            ->defaultSort('product.name')
            ->actions([
                // Ya no necesitas el botón "Ver detalles por almacén"
            ])
            ->bulkActions([]);
    }
}
