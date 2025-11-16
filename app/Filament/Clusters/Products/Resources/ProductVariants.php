<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Notifications\Notification;

class ProductVariants extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.products.pages.product-variants';

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
                    ->with('product')
                    ->where('status', 'activo')
            )

            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Imagen')
                    ->circular()
                    ->disk('public')
                    ->visibility('public')
                    ->default('https://lundazon.se/uploads/default_product.png'),


                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto'),

                Tables\Columns\TextColumn::make('values')
                    ->label('Variante de producto')
                    ->getStateUsing(
                        fn($record) =>
                        $record->values && $record->values->isNotEmpty()
                            ? $record->values
                            ->map(fn($value) => "{$value->attribute->name}: {$value->name}")
                            ->toArray()
                            : ['Sin variantes']
                    )
                    ->badge()
                    ->colors(['primary']),

                Tables\Columns\TextColumn::make('stock_real')
                    ->label('Stock real')
                    ->getStateUsing(fn($record) => $record->stock_real ?? 0)
                    ->visible(fn() => $this->record->control_stock ?? false),


                Tables\Columns\TextColumn::make('precio_total')
                    ->label('Precio total')
                    ->getStateUsing(function ($record) {
                        $productPrice = $record->product->price ?? 0;
                        $extraPrice = $record->extra_price ?? 0;
                        return 'S/ ' . number_format($productPrice + $extraPrice, 2);
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'activo',
                        'danger' => 'inactivo',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->color('primary')
                    ->modalHeading('Editar Variante')
                    ->fillForm(fn($record) => [
                        'image' => $record->image,
                        'sku' => $record->sku,
                        'internal_code' => $record->internal_code,
                        'extra_price' => $record->extra_price,
                        'sale_without_stock' => $record->sale_without_stock,
                        'status' => $record->status,
                    ])
                    ->form([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Imagen')
                            ->image()
                            ->imageEditor()
                            ->directory('products/variants')
                            ->disk('public')
                            ->preserveFilenames()
                            ->previewable(true),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('internal_code')
                            ->label('Código interno')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('extra_price')
                            ->label('Precio adicional')
                            ->numeric()
                            ->prefix('S/'),

                        Forms\Components\ToggleButtons::make('status')
                            ->label('Estado')
                            ->options([
                                'activo' => 'Activo',
                                'inactivo' => 'Inactivo',
                            ])
                            ->colors([
                                'activo' => 'success',
                                'inactivo' => 'danger',
                            ])
                            ->inline(),
                    ])
                    ->fillForm(fn($record) => $record->toArray())
                    ->action(function (array $data, $record): void {
                        $record->update($data);

                        Notification::make()
                            ->title('Variante actualizada correctamente')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false);
    }

    public function getBreadcrumbs(): array
    {
        return [
            ProductResource::getUrl('index') => 'Productos',
            ProductResource::getUrl('edit', ['record' => $this->record]) => $this->record->name,
            url()->current() => 'Listado de Variantes',
        ];
    }
}
