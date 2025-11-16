<?php

namespace App\Filament\Clusters\Products\ProductsCluster\Resources\ProductResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductVariants extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.products.pages.product-variants';

    public Product $record;

    public function mount(Product $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return "Variantes de {$this->record->name}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn() => $this->record
                    ->variants()
                    ->where('status', 'activo')
                    ->with('product')
            )
            ->columns([
                Tables\Columns\TextColumn::make('product')
                    ->label('Producto')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        "{$record->product->name}"
                    ),

                Tables\Columns\TextColumn::make('values')
                    ->label('Variante de producto')
                    ->getStateUsing(
                        fn($record) =>
                        $record->values
                            ->map(fn($value) => "{$value->attribute->name}: {$value->name}")
                            ->toArray()
                    )
                    ->badge() // convierte cada item del array en badge
                    ->colors([
                        'primary', // color default
                    ]),

                Tables\Columns\TextColumn::make('stock_real')
                    ->label('Stock real')
                    ->getStateUsing(fn($record) => $record->stock_real ?? 0),

                Tables\Columns\TextColumn::make('precio_total')
                    ->label('Precio total')
                    ->getStateUsing(function ($record) {
                        $productPrice = $record->product->price ?? 0;
                        $extraPrice = $record->extra_price ?? 0;
                        return 'S/ ' . number_format($productPrice + $extraPrice, 2);
                    }),
            ])
            ->paginated(false);
    }




    public function getBreadcrumbs(): array
    {
        return [
            ProductResource::getUrl('index') => 'Products',
            ProductResource::getUrl('edit', ['record' => $this->record]) => $this->record->name,
            url()->current() => 'Listado de Variantes',
        ];
    }
}
