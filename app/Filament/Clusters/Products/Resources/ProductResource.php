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
use App\Models\Value;
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

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('ProductTabs')
                    ->columnSpanFull()
                    ->tabs([
                        // 🔵 TAB 1 — INFORMACIÓN
                        Tab::make('Información')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                FileUpload::make('image_path')
                                                    ->label('Imagen del producto')
                                                    ->image()
                                                    ->directory('products')
                                                    ->disk('public')
                                                    ->preserveFilenames()
                                                    ->previewable(true)
                                                    ->columnSpanFull(),

                                                TextInput::make('name')
                                                    ->label('Nombre')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->reactive()
                                                    ->lazy()
                                                    ->afterStateUpdated(
                                                        fn($state, callable $set) =>
                                                        $set('slug', \Illuminate\Support\Str::slug($state))
                                                    )
                                                    ->columnSpan(2),

                                                Select::make('unid_id')
                                                    ->label('Unidad de medida')
                                                    ->relationship('unit', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                            ]),

                                        ToggleButtons::make('type')
                                            ->label('Tipo de Producto')
                                            ->inline()
                                            ->options(TipoProducto::class)
                                            ->required(),
                                        
                                        ToggleButtons::make('production_id')
                                            ->label('Área de Producción')
                                            ->options(fn() => \App\Models\Production::pluck('name', 'id')->toArray())
                                            ->inline(true)
                                            ->required(),

                                        TextInput::make('price')
                                            ->label('Precio Base')
                                            ->numeric()
                                            ->prefix('S/.')
                                            ->step('0.01')
                                            ->nullable(),



                                        Select::make('brand_id')
                                            ->label('Marca')
                                            ->relationship('brand', 'name')
                                            ->searchable()
                                            ->preload(),

                                        Select::make('categories')
                                            ->label('Categorías')
                                            ->multiple()
                                            ->relationship('categories', 'name')
                                            ->searchable()
                                            ->preload(),
                                        Select::make('status')
                                            ->label('Publicado')
                                            ->options(StatusProducto::class)
                                            ->required()
                                            ->helperText('Activa o desactiva la visibilidad del producto.'),



                                    ]),
                            ]),

                        // 🟣 TAB 2 — VARIANTES
                        Tab::make('Variantes')
                            ->schema([
                                self::getAttributesValuesRepeater(),
                            ]),

                        // 🟢 TAB 3 — CONFIGURACIÓN
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

                                    TextInput::make('slug')
                                        ->label('Slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->disabled()
                                        ->dehydrated()
                                        ->unique(ignoreRecord: true, table: Product::class)
                                        ->validationMessages([
                                            'unique' => 'Este slug ya existe. Por favor ingresa uno diferente.',
                                        ]),
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
                                                ->hintAction(
                                                    Action::make('info')
                                                        ->icon('heroicon-m-information-circle')
                                                        ->tooltip('Para productos obsequiados al cliente. En el POS podrás marcarlos como cortesía y su precio será S/ 0.')
                                                ),

                                            Toggle::make('visible')
                                                ->label('Visible en carta')
                                                ->default(true)
                                                ->inline(true)
                                                ->onIcon('heroicon-m-check')
                                                ->offIcon('heroicon-m-x-mark')
                                                ->hintAction(
                                                    Action::make('info')
                                                        ->icon('heroicon-m-information-circle')
                                                        ->tooltip('Habilita que el producto aparezca en la carta digital para pedidos.')
                                                ),

                                            Toggle::make('control_stock')
                                                ->label('Control de stock')
                                                ->default(true)
                                                ->inline(true)
                                                ->onIcon('heroicon-m-check')
                                                ->offIcon('heroicon-m-x-mark')
                                                ->hintAction(
                                                    Action::make('info')
                                                        ->icon('heroicon-m-information-circle')
                                                        ->tooltip('Controla salidas, ingresos y traslados del producto. Evita ventas sin stock.')
                                                ),

                                            Toggle::make('venta_sin_stock')
                                                ->label('Venta sin stock')
                                                ->default(true)
                                                ->inline(true)
                                                ->onIcon('heroicon-m-check')
                                                ->offIcon('heroicon-m-x-mark')
                                                ->hintAction(
                                                    Action::make('info')
                                                        ->icon('heroicon-m-information-circle')
                                                        ->tooltip('Puede venderse el producto aunque no haya stock disponible.')
                                                ),

                                        ]),
                                    ])
                                    ->collapsible(),

                            ])


                    ])
            ]);
    }


    public static function getAttributesValuesRepeater(): Repeater
    {
        return Repeater::make('attribute_values')
            ->label('')
            ->columns(2)
            ->schema([
                // Seleccionar atributo
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

                // Seleccionar valores del atributo
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

                            // Hidden para asegurar que el nuevo value tenga el attribute_id correcto
                            Hidden::make('attribute_id')->default($attribute?->id),
                        ];
                    })
                    ->createOptionUsing(function (array $data) {
                        return Value::create($data)->id;
                    }),

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
