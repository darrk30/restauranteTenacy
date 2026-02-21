<?php

namespace App\Filament\Restaurants\Resources;

use App\Enums\PromotionRuleType;
use App\Enums\StatusProducto;
use App\Filament\Restaurants\Resources\PromotionResource\Pages;
use App\Models\Promotion;
use App\Models\Product;
use App\Models\Production;
use App\Models\Variant;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Promociones';
    protected static ?string $pluralModelLabel = 'Promociones';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Promoción')
                    ->tabs([
                        // ------------------------------------------------------------------
                        // TAB 1: INFORMACIÓN GENERAL (Se mantiene igual, resumido aquí)
                        // ------------------------------------------------------------------
                        Tab::make('Información')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 2])->schema([
                                    FileUpload::make('image_path')
                                        ->label('Imagen del la promoción')
                                        ->image()
                                        ->disk('public')
                                        ->directory('tenants/' . Filament::getTenant()->slug . '/promociones')
                                        ->visibility('public')
                                        ->preserveFilenames()
                                        ->columnSpanFull(),

                                    TextInput::make('name')
                                        ->label('Nombre')
                                        ->required(),

                                    TextInput::make('price')->label('Precio')->numeric()->prefix('S/')->required(),

                                    Select::make('category_id')
                                        ->label('Categoría POS')
                                        ->relationship('category', 'name')
                                        ->searchable()
                                        ->createOptionForm([TextInput::make('name')->required()]),

                                    // 1. El componente original (Solo visible si hay datos)
                                    ToggleButtons::make('production_id')
                                        ->label('Área de Producción')
                                        ->options(fn() => Production::pluck('name', 'id'))
                                        ->inline(true)
                                        ->visible(fn() => Production::exists()), // Ocultar si la tabla está vacía

                                    // 2. El mensaje de error/aviso (Solo visible si NO hay datos)
                                    Placeholder::make('no_production_alert')
                                        ->label('Área de Producción')
                                        ->content(new HtmlString('<span class="text-gray-500 italic">⚠ Sin datos encontrados. Crea una área de producción primero.</span>'))
                                        ->hidden(fn() => Production::exists()),

                                    Grid::make(3) // <--- Esto divide el espacio en 3 columnas iguales
                                        ->schema([
                                            TextInput::make('code')
                                                ->label('Codigo de Promoción'),

                                            Select::make('status')
                                                ->label('Estado')
                                                ->options(StatusProducto::class)
                                                ->default(StatusProducto::Activo)
                                                ->required(),

                                            Toggle::make('visible')
                                                ->label('Visible en POS')
                                                ->inline(false)
                                                ->default(true),
                                        ]),
                                    DateTimePicker::make('date_start')
                                        ->label('Fecha de Inicio')
                                        ->native(false)
                                        ->displayFormat('d/m/Y H:i'),

                                    DateTimePicker::make('date_end')
                                        ->label('Fecha de Fin')
                                        ->native(false)
                                        ->displayFormat('d/m/Y H:i'),

                                    Textarea::make('description')->columnSpanFull(),
                                ]),
                            ]),

                        // ------------------------------------------------------------------
                        // TAB 2: PRODUCTOS (Se mantiene igual, resumido aquí)
                        // ------------------------------------------------------------------
                        Tab::make('Productos')
                            ->icon('heroicon-m-shopping-bag')
                            ->schema([
                                Repeater::make('promotionProducts')
                                    ->relationship()
                                    ->columns(3)
                                    ->schema([
                                        Select::make('product_id')
                                            ->options(Product::where('status', 'activo')->pluck('name', 'id'))
                                            ->searchable()
                                            ->reactive()
                                            ->required()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                $set('variant_id', null);
                                                if ($state) {
                                                    $variants = Variant::where('product_id', $state)->where('status', 'activo')->get();
                                                    if ($variants->count() === 1) $set('variant_id', $variants->first()->id);
                                                }
                                            }),
                                        Select::make('variant_id')
                                            ->options(fn(Get $get) => Variant::where('product_id', $get('product_id'))
                                                ->get()
                                                ->pluck('full_name', 'id'))
                                            ->searchable()
                                            ->required(),
                                        TextInput::make('quantity')->numeric()->default(1)->required(),
                                    ]),
                            ]),

                        // ------------------------------------------------------------------
                        // TAB 3: REGLAS (OPTIMIZADO CON ENUMS)
                        // ------------------------------------------------------------------
                        Tab::make('Reglas y Restricciones')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                Repeater::make('rules')
                                    ->relationship()
                                    ->label('Condiciones')
                                    ->defaultItems(0)
                                    ->schema([
                                        Grid::make(2)->schema([

                                            // SELECTOR DE TIPO (Usando Enum)
                                            Select::make('type')
                                                ->label('Tipo de Restricción')
                                                ->options(PromotionRuleType::class) // <--- Carga las opciones del Enum automáticamente
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function ($state, Set $set) {
                                                    if (!$state) return;

                                                    // Obtenemos la configuración desde el Enum
                                                    // Esto reemplaza tu match gigante anterior
                                                    $behavior = PromotionRuleType::from($state)->getBehavior();

                                                    $set('key', $behavior['key']);
                                                    $set('operator', $behavior['operator']);
                                                    $set('value', $behavior['value']);
                                                }),
                                        ]),

                                        Hidden::make('key'),
                                        Hidden::make('operator'),

                                        // --- UI DINÁMICA (Comparando contra Enum values) ---

                                        // CASO 1: DÍAS
                                        Section::make()
                                            ->schema([
                                                CheckboxList::make('value.days')
                                                    ->label('Días permitidos')
                                                    ->options([
                                                        1 => 'Lunes',
                                                        2 => 'Martes',
                                                        3 => 'Miércoles',
                                                        4 => 'Jueves',
                                                        5 => 'Viernes',
                                                        6 => 'Sábado',
                                                        0 => 'Domingo'
                                                    ])
                                                    ->columns(4)
                                                    ->bulkToggleable()
                                                    ->required(),
                                            ])
                                            ->visible(fn(Get $get) => $get('type') === PromotionRuleType::Days->value),

                                        // CASO 2: HORARIO
                                        Section::make()
                                            ->schema([
                                                Grid::make(2)->schema([
                                                    TimePicker::make('value.start')->label('Inicio')->required(),
                                                    TimePicker::make('value.end')->label('Fin')->required(),
                                                ])
                                            ])
                                            ->visible(fn(Get $get) => $get('type') === PromotionRuleType::TimeRange->value),

                                        // CASO 3: LÍMITE
                                        Section::make()
                                            ->schema([
                                                TextInput::make('value.limit')
                                                    ->label('Cantidad máxima diaria')
                                                    ->numeric()
                                                    ->required(),
                                            ])
                                            ->visible(fn(Get $get) => $get('type') === PromotionRuleType::Limit->value),

                                    ])
                                    // LABEL DEL REPEATER (Usando Enum)
                                    ->itemLabel(function (array $state): ?string {
                                        $typeValue = $state['type'] ?? null;
                                        if (!$typeValue) return 'Nueva Regla';

                                        // Intenta obtener el label del Enum
                                        return PromotionRuleType::tryFrom($typeValue)?->getLabel() ?? 'Regla desconocida';
                                    }),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->circular(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Categoría'),
                Tables\Columns\TextColumn::make('price')->money('PEN'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Activo' => 'success',
                        'Inactivo' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('visible')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            // Generalmente no necesitas relation managers si usas Repeaters en el form principal
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
