<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Enums\StatusProducto;
use App\Filament\Clusters\Products\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Clusters\Products\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Clusters\Products\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\Production;
use App\Models\Attribute;
use App\Enums\TipoProducto;
use App\Filament\Clusters\Products\ProductsCluster;
use App\Models\Value;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\ColorColumn;
use Illuminate\Support\Collection;

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
                Forms\Components\Grid::make(1)
                    ->schema([
                        Split::make([
                            Section::make('Información Principal')
                                ->schema([
                                    TextInput::make('name')->label('Nombre')
                                        ->required()
                                        ->maxLength(255)
                                        ->reactive()
                                        ->lazy()
                                        ->afterStateUpdated(fn($state, callable $set) => $set('slug', \Illuminate\Support\Str::slug($state))),
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
                                    ToggleButtons::make('type')
                                        ->label('Tipo de Producto')
                                        ->inline()
                                        ->options(TipoProducto::class)
                                        ->required(),

                                    ToggleButtons::make('production_id')
                                        ->label('Área de Producción')
                                        ->options(fn() => Production::pluck('name', 'id')->toArray())
                                        ->inline(true)
                                        ->required(),

                                    TextInput::make('price')
                                        ->label('Precio Base')
                                        ->numeric()
                                        ->prefix('S/.')
                                        ->step('0.01')
                                        ->nullable(),

                                    Toggle::make('cortesia')
                                        ->label('Es de cortesía')
                                        ->default(false),

                                    Toggle::make('visible')
                                        ->label('Visible en carta')
                                        ->default(true),

                                    TextInput::make('order')
                                        ->label('Orden')
                                        ->numeric()
                                        ->nullable(),
                                ])
                                ->columnSpan('full'),

                            Group::make()
                                ->schema([
                                    Section::make('Estado')
                                        ->schema([
                                            Select::make('status')
                                                ->label('Publicado')
                                                ->options(StatusProducto::class) // toma todos los casos del enum
                                                ->required()
                                                ->helperText('Activa o desactiva la visibilidad del producto.'),

                                            DateTimePicker::make('created_at')
                                                ->label('Día de Publicación')
                                                ->disabled(),
                                        ])
                                        ->columns(1),

                                    Section::make('Asociaciones')
                                        ->schema([
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
                                        ])
                                        ->columns(1),
                                ])->grow(false),
                        ])->from('md')->columnSpan('full'),

                        Section::make('Atributos y Variantes')
                            ->schema([
                                self::getAttributesValuesRepeater(),
                            ])->columnSpan('full')
                    ]),
            ]);
    }

    // public static function getAttributesValuesRepeater(): Repeater
    // {
    //     return Repeater::make('attribute_values')
    //         ->label('')
    //         ->columns(2)
    //         ->schema([
    //             // Seleccionar atributo
    //             Select::make('attribute_id')
    //                 ->label('Atributo')
    //                 ->options(Attribute::pluck('name', 'id'))
    //                 ->searchable()
    //                 ->reactive(), // para actualizar los valores al cambiar

    //             // Seleccionar valores del atributo
    //             Select::make('values')
    //                 ->label('Valores')
    //                 ->multiple()
    //                 ->options(function (callable $get) {
    //                     $attributeId = $get('attribute_id');
    //                     if (! $attributeId) {
    //                         return [];
    //                     }
    //                     return Value::where('attribute_id', $attributeId)->pluck('value', 'id')->toArray();
    //                 }),
    //         ])
    //         ->addActionLabel('Agregar atributo');
    // }



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
                        $set('value_id', null);
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
                Select::make('value_id')
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
        return $table
            ->columns([
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
