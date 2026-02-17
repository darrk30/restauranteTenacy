<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Enums\StatusProducto;
use App\Enums\TipoProducto;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Variant;
use App\Models\WarehouseStock;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Toggle;

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
                    ->with('product', 'stock')
                    ->whereIn('status', ['activo', 'inactivo'])
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
                    ->label('Variante')
                    ->getStateUsing(
                        fn($record) =>
                        $record->values && $record->values->isNotEmpty()
                            ? $record->values->map(fn($value) => "{$value->attribute->name}: {$value->name}")->toArray()
                            : ['Sin variantes']
                    )
                    ->badge()
                    ->colors(['primary']),

                // 🟢 STOCK ACTUALIZADO (Visible solo si el producto controla stock)
                Tables\Columns\TextColumn::make('stock.stock_real')
                    ->label('Stock Actual')
                    ->numeric()
                    ->alignCenter()
                    ->placeholder('0')
                    ->visible(fn() => $this->record->control_stock),

                Tables\Columns\TextColumn::make('costo')
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format($state, 2))
                    ->label('Costo'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'activo',
                        'danger' => 'inactivo',
                    ]),
            ])
            ->actions([
                // --- BOTÓN RECETA (NUEVO) ---
                Tables\Actions\Action::make('receta')
                    ->label('Receta')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->button()
                    ->visible(fn() => $this->record->receta)
                    ->modalHeading(fn($record) => "Receta: " . $this->record->name . ($record->values->isNotEmpty() ? ' - ' . $record->full_name : ''))
                    ->modalWidth('4xl')
                    ->fillForm(fn($record) => [
                        'recetas' => $record->recetas->map(function ($receta) {
                            return [
                                'insumo_id' => $receta->insumo_id,
                                'unit_id'   => $receta->unit_id,
                                'cantidad'  => $receta->cantidad,
                            ];
                        })->toArray()
                    ])
                    ->form([
                        Repeater::make('recetas')
                            ->label('Ingredientes')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Select::make('insumo_id')
                                        ->label('Insumo')
                                        ->options(function () {
                                            return \App\Models\Variant::query()
                                                ->whereHas('product', function ($query) {
                                                    $query->where('type', \App\Enums\TipoProducto::Insumo)
                                                        ->where('status', \App\Enums\StatusProducto::Activo);
                                                })
                                                ->where('status', 'activo')
                                                ->get()
                                                ->mapWithKeys(function ($variant) {
                                                    return [$variant->id => $variant->product->name . ' ' . $variant->full_name];
                                                });
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function (Set $set) {
                                            $set('unit_id', null);
                                        })
                                        ->columnSpan(1),

                                    Select::make('unit_id')
                                        ->label('Unidad')
                                        ->options(function (Get $get) {
                                            $insumoId = $get('insumo_id');
                                            if (!$insumoId) return [];
                                            $insumo = \App\Models\Variant::with('product.unit')->find($insumoId);
                                            $unidadBase = $insumo?->product?->unit;
                                            if (!$unidadBase) return [];
                                            if ($unidadBase->unit_category_id) {
                                                return \App\Models\Unit::where('unit_category_id', $unidadBase->unit_category_id)->pluck('name', 'id');
                                            }
                                            return \App\Models\Unit::where('id', $unidadBase->id)
                                                ->orWhere('reference_unit_id', $unidadBase->id)
                                                ->orWhere('id', $unidadBase->reference_unit_id)
                                                ->pluck('name', 'id');
                                        })
                                        ->searchable()
                                        ->required()
                                        ->columnSpan(1),

                                    // 3. CANTIDAD
                                    TextInput::make('cantidad')
                                        ->label('Cantidad')
                                        ->numeric()
                                        ->required()
                                        ->columnSpan(1),
                                ]),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Agregar ingrediente')
                            ->columns(1)
                    ])
                    ->action(function (Variant $record, array $data) {
                        // $record aquí es la Variante de la fila (el plato)
                        $record->recetas()->delete();

                        if (!empty($data['recetas'])) {
                            $record->recetas()->createMany($data['recetas']);
                        }

                        Notification::make()->title('Receta actualizada correctamente')->success()->send();
                    }),

                // --- BOTÓN EDITAR EXISTENTE ---
                Tables\Actions\Action::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->color('primary')
                    ->modalHeading('Editar Variante')
                    ->fillForm(fn($record) => [
                        'image_path' => $record->image_path,
                        'codigo_barras' => $record->codigo_barras,
                        'internal_code' => $record->internal_code,
                        'costo' => $record->costo,
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
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('codigo_barras')
                                    ->label('Código de barras')->maxLength(100),
                                Forms\Components\TextInput::make('internal_code')
                                    ->label('Código interno')->maxLength(100),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('stock_inicial')
                                    ->label(fn($record) => $record?->product?->unit?->name ? "Stock inicial ({$record->product->unit->name})" : "Stock inicial")
                                    ->default(0)
                                    ->visible(fn($record) => $record->product->control_stock && !$record->stock_inicial)
                                    ->numeric()
                                    ->step(fn($record) => $record?->product?->unit?->code === 'NIU' ? 1 : 'any')
                                    ->rules(fn($record) => $record?->product?->unit?->code === 'NIU' ? ['required', 'integer', 'min:0'] : ['required', 'numeric', 'min:0'])
                                    ->helperText("Solo se ingresa una vez."),

                                Forms\Components\TextInput::make('costo')
                                    ->label('Costo')
                                    ->numeric()
                                    ->prefix('S/.')
                                    ->default(0),

                                Toggle::make('status')
                                    ->label('Estado')
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->inline(false)
                                    ->formatStateUsing(fn($state) => $state === 'activo')
                                    ->dehydrateStateUsing(fn($state) => $state ? 'activo' : 'inactivo'),
                            ]),
                    ])
                    ->action(function (array $data, $record): void {
                        if (isset($data['stock_inicial']) && !$record->stock_inicial) {
                            $cantidad = (float) $data['stock_inicial'];
                            if ($cantidad > 0) {
                                $stock = WarehouseStock::firstOrCreate(
                                    ['variant_id' => $record->id],
                                    [
                                        'stock_real' => 0,
                                        'stock_reserva' => 0,
                                        'min_stock' => 0,
                                        'restaurant_id' => filament()->getTenant()->id,
                                    ]
                                );
                                $stock->increment('stock_real', $cantidad);
                                $stock->increment('stock_reserva', $cantidad);

                                $record->kardexes()->create([
                                    'product_id'      => $record->product_id,
                                    'variant_id'      => $record->id,
                                    'restaurant_id'   => filament()->getTenant()->id,
                                    'tipo_movimiento' => 'Stock Inicial',
                                    'cantidad'        => $cantidad,
                                    'stock_restante'  => $stock->stock_real,
                                    'modelo_type'     => get_class($record),
                                    'modelo_id'       => $record->id,
                                    'comprobante'     => 'STOCK-INICIAL',
                                ]);
                                $record->stock_inicial = true;
                            }
                            unset($data['stock_inicial']);
                        }
                        $record->update($data);
                        Notification::make()->title('Variante actualizada')->success()->send();
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
