<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Variant;
use App\Enums\TipoProducto;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStockPage extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Existencias';
    protected static ?string $title = 'Existencias de Almacén';
    protected static string $view = 'filament.warehouse.pages.existencias';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 25;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargarPdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn() => $this->exportarPdf()),
        ];
    }

    public function exportarPdf()
    {
        $columnasVisibles = collect($this->getTable()->getColumns())
            ->filter(fn($column) => ! $column->isToggledHidden())
            ->map(fn($column) => [
                'label' => $column->getLabel(),
                'name'  => $column->getName(),
            ]);

        $data = $this->getFilteredTableQuery()->get();

        $pdf = Pdf::loadView('filament.reports.inventario.existencias-pdf', [
            'data'     => $data,
            'columns'  => $columnasVisibles,
            'tenant'   => filament()->getTenant()->name,
            'prodType' => $this->tableFilters['type']['value'] ?? null,
        ])->setPaper('a4', $columnasVisibles->count() > 5 ? 'landscape' : 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'Reporte_Inventario_' . now()->format('d-m-Y') . '.pdf');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Variant::query()
                    ->with(['product.unit', 'stock'])
                    ->where('status', 'activo')
                    ->whereHas('product', fn($q) => $q->where('status', 'activo')->where('control_stock', true))
                    ->whereHas('stock')
            )
            ->columnToggleFormColumns(2)
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Variante')
                    ->searchable()
                    ->toggleable(),

                // --- NUEVA COLUMNA: TIPO ---
                Tables\Columns\TextColumn::make('product.type')
                    ->label('Tipo')
                    ->badge() // Usa el método getColor() del Enum automáticamente
                    ->icon(fn($state) => $state->getIcon()) // Usa el método getIcon() del Enum
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('stock.min_stock')
                    ->label('S. Mínimo')
                    ->numeric(decimalPlaces: 3)
                    ->alignRight()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('stock.stock_real')
                    ->label('S. Actual')
                    ->numeric(decimalPlaces: 3)
                    ->alignRight()
                    ->weight('bold')
                    ->color(fn($record) => ($record->stock?->stock_real <= $record->stock?->min_stock) ? 'danger' : 'primary')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('product.unit.name')
                    ->label('Unidad')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('stock.valor_inventario')
                    ->label('Valor Almacén')
                    ->money('PEN')
                    ->alignRight()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Filtrar por Tipo')
                    ->options([
                        TipoProducto::Producto->value => TipoProducto::Producto->getLabel(),
                        TipoProducto::Insumo->value   => TipoProducto::Insumo->getLabel(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['value'], function ($q, $value) {
                            $q->whereHas('product', fn($p) => $p->where('type', $value));
                        });
                    })
            ])
            ->defaultSort('product.name');
    }
}
