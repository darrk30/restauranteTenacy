<?php

namespace App\Filament\Restaurants\Resources;

use App\Enums\StatusProducto;
use App\Filament\Restaurants\Resources\PromotionResource\Pages;
use App\Models\Promotion;
use App\Models\Variant;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Inventarios';
    protected static ?string $navigationLabel = 'Promociones';
    protected static ?string $pluralModelLabel = 'Promociones';
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Tabs::make('Promoción')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Información')
                            ->schema([

                                Forms\Components\Grid::make([
                                    'default' => 1,
                                    'sm' => 2,
                                ])
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre')
                                            ->required(),

                                        Forms\Components\TextInput::make('price')
                                            ->label('Precio')
                                            ->numeric()
                                            ->prefix('S/'),

                                        Forms\Components\TextInput::make('slug')
                                            ->unique(ignoreRecord: true)
                                            ->required(),

                                        Forms\Components\FileUpload::make('image_path')
                                            ->label('Imagen')
                                            ->image()
                                            ->imageEditor()
                                            ->directory('promotions')
                                            ->disk('public')
                                            ->preserveFilenames()
                                            ->previewable(true),

                                        Forms\Components\Toggle::make('visible')
                                            ->label('Visible'),

                                        Forms\Components\Select::make('status')
                                            ->label('Publicado')
                                            ->options(StatusProducto::class)
                                            ->required()
                                            ->selectablePlaceholder(false)
                                            ->default(StatusProducto::Activo),

                                        Forms\Components\Textarea::make('description'),
                                        Forms\Components\DateTimePicker::make('date_start')
                                            ->label('Inicio'),
                                        Forms\Components\DateTimePicker::make('date_end')
                                            ->label('Fin'),
                                    ]),

                            ]),
                        Forms\Components\Tabs\Tab::make('Productos')
                            ->schema([
                                Forms\Components\Repeater::make('Productos de la promoción')
                                    ->label('')
                                    ->relationship('promotionproducts')
                                    ->columnSpan('full')
                                    ->columns(3)
                                    ->schema([
                                        // PRODUCTO
                                        Forms\Components\Select::make('product_id')
                                            ->label('Producto')
                                            ->relationship('product', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->required()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if (!$state) {
                                                    $set('variant_id', null);
                                                    return;
                                                }
                                                $variants = Variant::where('product_id', $state)->where('status', 'activo')->get();
                                                if ($variants->count() === 1) { $set('variant_id', $variants->first()->id); } 
                                                else { $set('variant_id', null); }
                                            }),

                                        // VARIANTE
                                        Select::make('variant_id')
                                            ->label('Variante')
                                            ->options(function (callable $get) {
                                                $productId = $get('product_id');
                                                if (!$productId) { return []; }
                                                return Variant::where('product_id', $productId)->where('status', 'activo')->get()->pluck('full_name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        // CANTIDAD
                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required(),

                                    ])
                            ]),

                        Tab::make('Reglas')
                            ->schema([
                                Repeater::make('rules')
                                    ->label('Reglas de promoción')
                                    ->relationship()
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->itemLabel(function (array $state): ?string {
                                        if (!isset($state['type'])) { return 'Sin tipo'; }
                                        return config("promotion_rules.types.{$state['type']}.label") ?? $state['type'];
                                    })
                                    ->schema([
                                        Select::make('type')
                                            ->label('Tipo de regla')
                                            ->options(
                                                collect(config('promotion_rules.types'))->mapWithKeys(fn($item, $key) => [$key => $item['label']])
                                            )
                                            ->live()
                                            ->required()
                                            ->afterStateHydrated(function ($state, $set) {
                                                if (!$state) return;
                                                $rule = config("promotion_rules.types.$state.fields");
                                                $set('key', $rule['key']);
                                                $set('operator', $rule['operator']);
                                            })
                                            ->afterStateUpdated(function ($state, $set) {
                                                $rule = config("promotion_rules.types.$state.fields");
                                                $set('key', $rule['key']);
                                                $set('operator', $rule['operator']);
                                                $set('value', null);
                                            }),
                                        // CHECK: Seleccionar todos
                                        Checkbox::make('select_all_days')
                                            ->label('Todos los días')
                                            ->visible(fn($get) => $get('type') === 'days')
                                            ->default(false)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $allDays = array_keys(config('promotion_rules.types.days.fields.options'));
                                                $set('value', $state ? $allDays : []);
                                            }),
                                        // Datos de regla
                                        Fieldset::make('Datos de regla')
                                            ->schema([
                                                Forms\Components\Hidden::make('key'),
                                                Forms\Components\Hidden::make('operator'),
                                                Forms\Components\CheckboxList::make('value')
                                                    ->label('Selecciona los días')
                                                    ->options(function (callable $get) {
                                                        $type = $get('type');
                                                        return config("promotion_rules.types.$type.fields.options") ?? [];
                                                    })
                                                    ->columns(4)
                                                    ->visible(fn($get) => $get('type') === 'days')
                                                    ->requiredIf('type', 'days')
                                                    ->afterStateHydrated(function ($state, $set, $get) {
                                                        if ($get('type') === 'days' && is_string($state)) {
                                                            $set('value', json_decode($state, true));
                                                        }
                                                    })
                                                    ->extraAttributes([
                                                        'class' => 'checkbox-days-list'
                                                    ])
                                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                        if ($get('type') !== 'days') return;
                                                        $allDays = array_keys(config('promotion_rules.types.days.fields.options'));
                                                        $set('select_all_days', $state === $allDays);
                                                    })
                                                    ->columnSpan('full'),
                                                // LÍMITE
                                                TextInput::make('value')
                                                    ->label('Cantidad')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->visible(fn($get) => $get('type') === 'limit')
                                                    ->requiredIf('type', 'limit'),
                                            ]),


                                    ]),
                            ]),
                    ])
                    ->columnSpan('full'),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Imagen')
                    ->circular()
                    ->disk('public')
                    ->visibility('public')
                    ->default(asset('img/productdefault.jpg')),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado'),

                Tables\Columns\TextColumn::make('date_start')
                    ->dateTime('d/m/Y H:i')
                    ->label('Inicio')
                    ->placeholder('Sin fecha'),

                Tables\Columns\TextColumn::make('date_end')
                    ->dateTime('d/m/Y H:i')
                    ->label('Fin')
                    ->placeholder('Sin fecha'),

            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                    // Tables\Actions\ForceDeleteBulkAction::make(),
                    // Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    /** ------------------------------
     *  RELACIONES
     * ------------------------------ */
    public static function getRelations(): array
    {
        return [
            // PromotionProductRelationManager::class,
            // PromotionRulesRelationManager::class,
        ];
    }

    /** ------------------------------
     *  PÁGINAS
     * ------------------------------ */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
