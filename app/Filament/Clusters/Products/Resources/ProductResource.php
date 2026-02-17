<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Enums\StatusProducto;
use App\Filament\Clusters\Products\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Clusters\Products\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Clusters\Products\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\Attribute;
use App\Enums\TipoProducto;
use App\Filament\Clusters\Products\ProductsCluster;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Production;
use App\Models\Unit;
use App\Models\Value;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Get;
use Filament\Forms\Set;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $cluster = ProductsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?int $navigationSort = 1;


    public static function getSchema(): array
    {
        return [
            Tabs::make('ProductTabs')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Información')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            FileUpload::make('image_path')
                                                ->label('Imagen del producto')
                                                ->image()
                                                ->disk('public')
                                                ->directory('tenants/' . Filament::getTenant()->slug . '/products')
                                                ->visibility('public')
                                                ->preserveFilenames()
                                                ->columnSpanFull(),
                                            TextInput::make('name')
                                                ->label('Nombre')
                                                ->required()
                                                ->maxLength(255)
                                                ->reactive()
                                                ->validationMessages([
                                                    'required' => 'Nombre requerido',
                                                ])
                                                ->columnSpan(2),

                                            Select::make('unid_id')
                                                ->label('Unidad de medida')
                                                ->relationship('unit', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->default(fn() => Unit::where('code', 'NIU')->value('id') ?? null)
                                                ->selectablePlaceholder(false)
                                                ->disableOptionWhen(function (Get $get, $value) {
                                                    $type = $get('type');
                                                    $unit = Unit::find($value);
                                                    if (!$unit) return false;
                                                    if ($type === TipoProducto::Servicio->value) {
                                                        return $unit->code !== 'ZZ';
                                                    }
                                                    return false;
                                                }),
                                        ]),
                                    ToggleButtons::make('type')
                                        ->label('Tipo de Producto')
                                        ->inline()
                                        ->options(
                                            fn() => collect(TipoProducto::cases())
                                                ->reject(fn($type) => $type === TipoProducto::Promocion)
                                                ->mapWithKeys(fn($type) => [$type->value => $type->getLabel()])
                                        )
                                        ->icons(
                                            fn() => collect(TipoProducto::cases())
                                                ->reject(fn($type) => $type === TipoProducto::Promocion)
                                                ->mapWithKeys(fn($type) => [$type->value => $type->getIcon()])
                                        )
                                        ->colors(
                                            fn() => collect(TipoProducto::cases())
                                                ->reject(fn($type) => $type === TipoProducto::Promocion)
                                                ->mapWithKeys(fn($type) => [$type->value => $type->getColor()])
                                        )
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Tipo de producto requerido',
                                        ])
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state === TipoProducto::Producto->value) {
                                                $set('unid_id', Unit::where('code', 'NIU')->value('id'));
                                                return;
                                            }
                                            if ($state === TipoProducto::Servicio->value) {
                                                $set('unid_id', Unit::where('code', 'ZZ')->value('id'));
                                                return;
                                            }
                                        }),


                                    ToggleButtons::make('production_id')
                                        ->label('Área de Producción')
                                        ->options(fn() => Production::pluck('name', 'id')->toArray())
                                        ->inline(true),

                                    TextInput::make('price')
                                        ->label('Precio Base')
                                        ->numeric()
                                        ->prefix('S/.')
                                        ->step('0.01')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Precio requerido',
                                        ]),
                                    Select::make('brand_id')
                                        ->label('Marca')
                                        ->relationship('brand', 'name', fn($query) => $query->where('status', true)) // Solo activas
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            TextInput::make('name')
                                                ->label('Nombre de la marca')
                                                ->required(),
                                        ])
                                        ->createOptionUsing(fn(array $data) => Brand::create($data)->id),


                                    Select::make('categories')
                                        ->label('Categorías')
                                        ->multiple()
                                        ->relationship('categories', 'name', fn($query) => $query->where('status', true)) // Solo activas
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            TextInput::make('name')
                                                ->label('Nombre de la categoría')
                                                ->required(),
                                        ])
                                        ->dehydrated(true)
                                        ->createOptionUsing(fn(array $data) => Category::create($data)->id),


                                    Select::make('status')
                                        ->label('Publicado')
                                        ->options(StatusProducto::class)
                                        ->required()
                                        ->selectablePlaceholder(false)
                                        ->validationMessages([
                                            'required' => 'Estado requerido',
                                        ])
                                        ->default(StatusProducto::Activo)
                                        ->helperText('Activa o desactiva la visibilidad del producto.'),
                                ]),
                        ]),


                    Tab::make('Variantes')
                        ->visible(fn(callable $get) => !in_array(
                            $get('type'),
                            [
                                TipoProducto::Insumo->value,
                            ]
                        ))
                        ->schema([
                            self::getAttributesValuesRepeater(),
                        ]),


                    Tab::make('Configuración')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('order')
                                    ->label('Orden')
                                    ->numeric()
                                    ->nullable(),

                                DateTimePicker::make('created_at')
                                    ->label('Fecha de Publicación')
                                    ->disabled(),
                            ]),

                            Section::make('Opciones')
                                ->description('Configuraciones adicionales del producto')
                                ->schema([
                                    Grid::make(3)->schema([
                                        Toggle::make('cortesia')
                                            ->label('Es de cortesía')
                                            ->default(false)
                                            ->inline(true)
                                            ->onIcon('heroicon-m-check')
                                            ->offIcon('heroicon-m-x-mark')
                                            ->visible(fn(Get $get) => $get('type') !== TipoProducto::Insumo->value)
                                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Para productos obsequiados...'),

                                        // 2. VISIBLE (Sin cambios)
                                        Toggle::make('visible')
                                            ->label('Visible en carta')
                                            ->default(false)
                                            ->inline(true)
                                            ->onIcon('heroicon-m-check')
                                            ->offIcon('heroicon-m-x-mark')
                                            ->visible(fn(Get $get) => $get('type') !== TipoProducto::Insumo->value)
                                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Habilita que el producto aparezca...'),

                                        // 3. RECETA (Nuevo Toggle - Mutuamente excluyente con Stock)
                                        Toggle::make('receta')
                                            ->label('Tiene Receta')
                                            ->default(false)
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if ($state) {
                                                    $set('control_stock', false);
                                                    $set('venta_sin_stock', false);
                                                }
                                            })
                                            ->inline(true)
                                            ->onIcon('heroicon-m-beaker')
                                            ->offIcon('heroicon-m-x-mark')
                                            ->visible(fn(Get $get) => $get('type') !== TipoProducto::Insumo->value)
                                            ->hintIcon(
                                                'heroicon-m-information-circle',
                                                tooltip: 'Para platos preparados. El stock se descuenta de los ingredientes. Desactiva "Control de Stock".'
                                            ),

                                        // 4. CONTROL DE STOCK (Modificado)
                                        Toggle::make('control_stock')
                                            ->label('Control de stock')
                                            ->default(false)
                                            ->live() // Reactivo
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if ($state) {
                                                    $set('receta', false);
                                                } else {
                                                    $set('venta_sin_stock', false);
                                                }
                                            })
                                            ->inline(true)
                                            ->onIcon('heroicon-m-check')
                                            ->offIcon('heroicon-m-x-mark')
                                            ->visible(fn(Get $get) => !in_array($get('type'), [TipoProducto::Servicio->value]))
                                            ->hintIcon(
                                                'heroicon-m-information-circle',
                                                tooltip: 'Para productos terminados (ej. Gaseosas). Desactiva la opción de "Receta".'
                                            ),

                                        // 5. VENTA SIN STOCK (Sin cambios mayores, solo depende de control_stock)
                                        Toggle::make('venta_sin_stock')
                                            ->label('Venta sin stock')
                                            ->default(false)
                                            ->inline(true)
                                            ->onIcon('heroicon-m-check')
                                            ->offIcon('heroicon-m-x-mark')
                                            ->live()
                                            ->visible(fn(Get $get) => $get('control_stock') && $get('type') !== TipoProducto::Insumo->value)
                                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Puede venderse el producto aunque no haya stock disponible.'),



                                    ]),
                                ])
                                ->collapsible(),
                        ])
                ])
        ];
    }


    public static function form(Form $form): Form
    {
        return $form->schema(static::getSchema());
    }


    public static function getAttributesValuesRepeater(): Repeater
    {
        return Repeater::make('attribute_values')
            ->label('')
            ->columns(2)
            ->schema([
                Select::make('attribute_id')
                    ->label('Atributo')
                    ->relationship(name: 'attributes', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('vales', null);
                    })
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nombre del atributo')
                            ->required(),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options([
                                'color' => 'Color',
                                'pildoras' => 'Píldoras',
                                'seleccionar' => 'Seleccionar',
                                'radio' => 'Radio',
                            ])
                            ->required(),
                    ])
                    ->createOptionUsing(fn(array $data) => Attribute::create($data)->id),
                Select::make('values')
                    ->label('Valores')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->multiple()
                    ->options(
                        fn(Get $get): array =>
                        Value::query()
                            ->where('attribute_id', $get('attribute_id'))
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->createOptionForm(function (callable $get) {
                        $attribute = Attribute::find($get('attribute_id') ?? $get('../../attribute_id'));
                        return [
                            TextInput::make('name')
                                ->label('Nombre del valor')
                                ->required(),

                            ColorPicker::make('value')
                                ->label('Color')
                                ->visible($attribute?->tipo === 'color')
                                ->required($attribute?->tipo === 'color'),
                            Hidden::make('attribute_id')->default($attribute?->id),
                        ];
                    })
                    ->createOptionUsing(function (array $data) {
                        return Value::create($data)->id;
                    })
                    ->hintAction(
                        Action::make('configurar_precios')
                            ->label('Configurar Precios Extra')
                            ->icon('heroicon-m-currency-dollar')
                            ->color('primary')
                            ->modalHeading(fn(Get $get) => 'Precios para ' . Attribute::find($get('attribute_id'))?->name)
                            ->modalSubmitActionLabel('Guardar Precios')
                            // ESTO LLENA EL MODAL CON LA DATA
                            ->fillForm(function (Get $get) {
                                $selectedIds = $get('values') ?? [];
                                $currentPrices = $get('extra_prices') ?? [];

                                // Buscamos los nombres de los valores seleccionados
                                $valuesData = Value::whereIn('id', $selectedIds)->get();

                                // Preparamos los datos para el Repeater del modal
                                return [
                                    'precios_repeater' => $valuesData->map(function ($val) use ($currentPrices) {
                                        return [
                                            'value_id' => $val->id,
                                            'name_display' => $val->name, // Solo para mostrar
                                            'extra' => $currentPrices[$val->id] ?? 0, // Precio actual o 0
                                        ];
                                    })->toArray()
                                ];
                            })
                            // EL FORMULARIO DENTRO DEL MODAL
                            ->form([
                                Repeater::make('precios_repeater')
                                    ->hiddenLabel()
                                    ->addable(false) // No pueden agregar filas, solo editar las existentes
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->schema([
                                        // Campo Solo Lectura para saber qué estamos editando
                                        TextInput::make('name_display')
                                            ->label('Opción')
                                            ->disabled()
                                            ->columnSpan(1),

                                        // ID Oculto para referencia
                                        Hidden::make('value_id'),

                                        // El Precio Extra
                                        TextInput::make('extra')
                                            ->label('Precio Extra (S/)')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('S/')
                                            ->required()
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2)
                            ])
                            // AL GUARDAR EL MODAL
                            ->action(function (array $data, Set $set) {
                                // Convertimos el array del repeater a un formato clave => valor para guardarlo fácil
                                // Ejemplo salida: [ '105' => 5.00, '106' => 0.00 ]
                                $preciosMapeados = collect($data['precios_repeater'])
                                    ->mapWithKeys(fn($item) => [$item['value_id'] => $item['extra']])
                                    ->toArray();

                                $set('extra_prices', $preciosMapeados);
                            })
                    ),
            ])
            ->addActionLabel('Agregar atributo');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Nombre')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('brand.name')
                ->label('Marca')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('categories.name')
                ->label('Categorías')
                ->badge()
                ->separator(', ')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('production.name')
                ->label('Área de producción')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('type')
                ->label('Tipo')
                ->sortable(),

            Tables\Columns\TextColumn::make('price')
                ->label('Precio')
                ->money('PEN', true)
                ->sortable(),

            Tables\Columns\IconColumn::make('cortesia')
                ->label('Cortesía')
                ->boolean()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\IconColumn::make('visible')
                ->label('Visible')
                ->boolean()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('order')
                ->label('Orden')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('status')
                ->label('Estado')
                ->sortable(),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Creado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

        ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
            'variants' => ProductVariants::route('/{record}/variants'),
        ];
    }
}
