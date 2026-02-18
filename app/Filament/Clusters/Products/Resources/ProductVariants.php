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
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;

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
                        TableRepeater::make('recetas')
                            ->label('Ingredientes')
                            ->addActionLabel('Agregar ingrediente')
                            ->defaultItems(0)
                            ->schema([

                                // COLUMNA 1: INSUMO
                                Select::make('insumo_id')
                                    ->label('Insumo') // Se mantiene el label para la vista móvil
                                    ->placeholder('Seleccionar insumo...')
                                    ->options(function () {
                                        return \App\Models\Variant::query()
                                            ->whereHas('product', function ($query) {
                                                $query->where('type', \App\Enums\TipoProducto::Insumo)
                                                    ->where('status', \App\Enums\StatusProducto::Activo);
                                            })
                                            ->where('status', 'activo')
                                            ->get()
                                            ->mapWithKeys(function ($variant) {
                                                return [$variant->id => $variant->product->name];
                                            });
                                    })
                                    // ESTO ES CLAVE: Permite ver el nombre guardado en lugar del ID "2"
                                    ->getOptionLabelUsing(function ($value) {
                                        $variant = \App\Models\Variant::with('product')->find($value);
                                        return $variant ? $variant->product->name : null;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn(Set $set) => $set('unit_id', null))
                                    ->native(false), // false = Diseño bonito de Filament

                                // COLUMNA 2: UNIDAD
                                Select::make('unit_id')
                                    ->label('Unidad')
                                    ->placeholder('Selec.')
                                    ->options(function (Get $get) {
                                        $insumoId = $get('insumo_id');
                                        if (!$insumoId) return [];

                                        $insumo = \App\Models\Variant::with('product.unit')->find($insumoId);
                                        $unidadBase = $insumo?->product?->unit;

                                        if (!$unidadBase) return [];

                                        // Lógica para traer unidades de la misma categoría o conversiones
                                        if ($unidadBase->unit_category_id) {
                                            return \App\Models\Unit::where('unit_category_id', $unidadBase->unit_category_id)
                                                ->pluck('name', 'id');
                                        }

                                        return \App\Models\Unit::where('id', $unidadBase->id)
                                            ->orWhere('reference_unit_id', $unidadBase->id)
                                            ->orWhere('id', $unidadBase->reference_unit_id)
                                            ->pluck('name', 'id');
                                    })
                                    // ESTO ES CLAVE: Permite ver el nombre de la unidad guardada
                                    ->getOptionLabelUsing(fn($value) => \App\Models\Unit::find($value)?->name)
                                    ->searchable()
                                    ->required()
                                    ->native(false),

                                // COLUMNA 3: CANTIDAD
                                TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->placeholder('0.00')
                                    ->numeric()
                                    ->required(),
                            ])
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
                        // 1. Extraer el costo que viene del formulario
                        $costoIngresado = (float) ($data['costo'] ?? 0);

                        // 2. Lógica de Stock Inicial (Solo si no se ha ingresado antes)
                        if (isset($data['stock_inicial']) && !$record->stock_inicial) {
                            $cantidad = (float) $data['stock_inicial'];

                            if ($cantidad > 0) {
                                // Buscamos o creamos el registro de stock
                                $stock = WarehouseStock::firstOrNew(
                                    ['variant_id' => $record->id],
                                    ['restaurant_id' => filament()->getTenant()->id]
                                );

                                // Al ser Stock Inicial, el costo promedio es simplemente el costo ingresado
                                $stock->stock_real = $cantidad;
                                $stock->stock_reserva = $cantidad;
                                $stock->costo_promedio = $costoIngresado;
                                $stock->valor_inventario = $cantidad * $costoIngresado; // Cantidad * Costo
                                $stock->save();

                                // Registrar en Kardex con valores financieros
                                $record->kardexes()->create([
                                    'product_id'      => $record->product_id,
                                    'variant_id'      => $record->id,
                                    'restaurant_id'   => filament()->getTenant()->id,
                                    'tipo_movimiento' => 'Stock Inicial',
                                    'comprobante'     => 'STOCK-INICIAL',
                                    'cantidad'        => $cantidad,
                                    'costo_unitario'  => $costoIngresado,
                                    'saldo_valorizado' => $stock->valor_inventario,
                                    'stock_restante'  => $stock->stock_real,
                                    'modelo_type'     => get_class($record),
                                    'modelo_id'       => $record->id,
                                ]);

                                $record->stock_inicial = true;
                            }
                            unset($data['stock_inicial']);
                        }

                        // 3. Actualizar los datos de la Variante (incluyendo el campo costo)
                        $record->update($data);

                        Notification::make()->title('Variante actualizada con éxito')->success()->send();
                    })
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
