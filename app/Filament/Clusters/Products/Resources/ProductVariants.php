<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Product;
use App\Models\Unit;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

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
                    ->default(asset('img/productdefault.jpg')),


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


                Tables\Columns\TextColumn::make('precio_extra')
                    ->label('Precio extra')
                    ->getStateUsing(function ($record) {
                        $extraPrice = $record->extra_price ?? 0;
                        return 'S/ ' . number_format($extraPrice, 2);
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
                        'unit' => $record->unit,
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

                        Forms\Components\TextInput::make('stock_inicial')
                            ->label(function ($record) {
                                $unitName = $record?->product?->unit?->name ?? '';
                                return $unitName
                                    ? "Stock inicial ($unitName)"
                                    : "Stock inicial";
                            })
                            ->default(0)
                            ->visible(function ($record) {
                                if (!$record->product?->control_stock) { return false; }
                                if ($record->stock_inicial != false) { return false; }
                                $unitName = $record->product?->unit?->name ?? null;
                                if ($unitName === 'Servicio') { return false; }
                                return true;
                            })
                            ->numeric() // sigue siendo necesario
                            ->step(function ($record) {   //BLOQUEA DECIMALES
                                $unitName = $record?->product?->unit?->name ?? null;
                                if ($unitName === 'Unidad' || $unitName === 'Unidades') { return 1; }
                                return 'any'; // ← Permite decimales
                            })
                            ->rules(function ($record) {
                                $unitName = $record?->product?->unit?->name ?? null;
                                if ($unitName === 'Unidad' || $unitName === 'Unidades') {
                                    return ['required', 'integer', 'min:0'];
                                }
                                return ['required', 'numeric', 'min:0'];
                            })
                            ->helperText(function ($record) {
                                if (!$record->product?->control_stock) { return null; }
                                $unitName = $record?->product?->unit?->name ?? null;
                                if ($unitName === 'Servicio') { return null; }

                                if ($unitName === 'Unidad' || $unitName === 'Unidades') {
                                    return '⚠️ Solo se permiten cantidades enteras porque la unidad del producto es "Unidad".';
                                }
                                return "⚠️ Puede ingresar decimales según la unidad del producto ($unitName). El stock inicial solo se podrá ingresar una única vez.";
                            }),

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

                        // Guardar stock inicial solo si viene en el formulario
                        if (isset($data['stock_inicial']) && $record->stock_inicial_asignado == false) {

                            $cantidad = (int) $data['stock_inicial'];

                            if ($cantidad > 0) {
                                $warehouse = \App\Models\Warehouse::where('restaurant_id', filament()->getTenant()->id)
                                    ->orderBy('order')
                                    ->first();

                                if ($warehouse) {
                                    $stock = \App\Models\WarehouseStock::firstOrCreate(
                                        [
                                            'warehouse_id' => $warehouse->id,
                                            'variant_id' => $record->id,
                                        ],
                                        [
                                            'stock_real' => 0,
                                            'stock_reserva' => 0,
                                            'min_stock' => 0,
                                            'restaurant_id' => filament()->getTenant()->id,
                                        ]
                                    );
                                    $stock->update([
                                        'stock_real' => $stock->stock_real + $cantidad
                                    ]);
                                }
                                $record->update([ 'stock_inicial' => true, ]);
                            }

                            // No permitir guardar este dato nuevamente
                            unset($data['stock_inicial']);
                        }
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
