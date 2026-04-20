<?php

namespace App\Filament\Restaurants\Resources;

use App\Enums\StatusProducto;
use App\Enums\TipoProducto;
use App\Filament\Restaurants\Resources\StockAdjustmentResource\Pages;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Variant;
use App\Models\Unit;
use App\Services\StockAdjustmentService;
use App\Traits\ManjoStockProductos;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustmentResource extends Resource
{
    use ManjoStockProductos;
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationLabel = 'Ajustes de Stock';
    protected static ?string $pluralLabel = 'Ajustes de Stock';
    protected static ?string $label = 'Ajuste de Stock';
    protected static ?string $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 35;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(12)
                    ->schema([
                        // --- SECCIÓN IZQUIERDA ---
                        Forms\Components\Section::make('Configuración General')
                            ->schema([
                                Forms\Components\Select::make('tipo')
                                    ->label('Tipo de Movimiento')
                                    ->options([
                                        'entrada' => 'Entrada de stock',
                                        'salida' => 'Salida de stock',
                                    ])
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Escoja una operación',
                                    ])
                                    ->native(false)
                                    ->reactive()
                                    ->preload(),

                                Forms\Components\Textarea::make('motivo')
                                    ->label('Descripcion')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Ingrese una descripción del ajuste',
                                    ])
                                    ->rows(3),
                            ])
                            ->columnSpan(8),

                        // --- SECCIÓN DERECHA: Resumen y TOTAL ---
                        Forms\Components\Section::make('Resumen')
                            ->schema([
                                TextInput::make('total')
                                    ->label('Valor Total del Ajuste')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->readOnly()
                                    ->default(0.00)
                                    ->extraInputAttributes(['class' => 'text-xl font-bold text-primary-600']),

                                Forms\Components\Placeholder::make('usuario')
                                    ->label('Responsable')
                                    ->content(Auth::user()->name),

                                Forms\Components\Placeholder::make('info')
                                    ->label('Nota')
                                    ->content('El costo ingresado afectará la valorización del Kardex.'),
                            ])
                            ->columnSpan(4),
                    ]),

                Forms\Components\Section::make('Listado de Productos a Ajustar')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->columns(8) // Incrementamos columnas para que quepa todo
                            ->addActionLabel('Agregar otro producto')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto')
                                    ->relationship(
                                        'product',
                                        'name',
                                        fn($query) =>
                                        $query->where('control_stock', true)->where('status', StatusProducto::Activo->value)
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Escoja una producto',
                                    ])
                                    ->columnSpan(2)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('variant_id', null);
                                        if ($state) {
                                            $product = Product::find($state);
                                            $set('unit_id', $product?->unit_id);
                                            // Opcional: Cargar último costo del producto
                                            $set('costo', $product?->costo_referencial ?? 0);
                                        }
                                    }),

                                Select::make('variant_id')
                                    ->label('Variante')
                                    ->columnSpan(2)
                                    ->options(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (blank($productId)) return [];
                                        return Variant::where('product_id', $productId)
                                            ->where('status', 'activo')
                                            ->get()
                                            ->mapWithKeys(fn($variant) => [
                                                $variant->id => $variant->full_name
                                            ]);
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Escoja una variante',
                                    ]),

                                TextInput::make('cantidad')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Ingrese una cantidad',
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateLine($get, $set))
                                    ->columnSpan(1),

                                Select::make('unit_id')
                                    ->label('Unidad')
                                    ->options(function ($get) {
                                        $product = Product::with('unit')->find($get('product_id'));
                                        return $product?->unit ? Unit::where('unit_category_id', $product->unit->unit_category_id)->pluck('name', 'id') : [];
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Seleccione una unidad',
                                    ])
                                    ->columnSpan(1),

                                TextInput::make('costo')
                                    ->label('Costo Unit.')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->required()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Ingrese el costo unitario',
                                    ])
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateLine($get, $set))
                                    ->columnSpan(1),

                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->readOnly()
                                    ->columnSpan(1),
                            ])
                            ->reactive()
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateTotals($get, $set))
                    ]),
            ]);
    }

    // --- LÓGICA DE CÁLCULO (Similar a PurchaseResource) ---
    // En la parte superior de StockAdjustmentResource.php
    private static function recalculateLine(Get $get, Set $set): void
    {
        $cantidad = (float) ($get('cantidad') ?? 0);
        $costo = (float) ($get('costo') ?? 0);
        $subtotal = $cantidad * $costo;
        $set('subtotal', round($subtotal, 2));
        self::recalculateTotals($get, $set, true);
    }

    private static function recalculateTotals(Get $get, Set $set, bool $isInsideRepeater = false): void
    {
        $path = $isInsideRepeater ? '../../' : '';
        $items = collect($get($path . 'items') ?? []);
        $total = $items->sum(fn($item) => (float) ($item['subtotal'] ?? 0));
        $set($path . 'total', round($total, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Ajuste')
                    ->sortable()
                    ->description(fn($record) => $record->created_at->diffForHumans())
                    ->dateTime('d/m/Y h:i A'),

                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->copyable()
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->status === 'anulado' ? "<s>$state</s>" : $state
                    )->html(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Responsable')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->color('gray')
                    ->description(fn($record) => $record->user->email ?? ''),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Observación')
                    ->limit(30) // 🟢 Acorta textos largos
                    ->tooltip(fn($state) => $state)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Productos')
                    ->badge()
                    ->color('gray')
                    ->alignment('center')
                    ->action(
                        Tables\Actions\Action::make('verItems')
                            ->modalHeading('Detalle de Productos Ajustados')
                            ->modalWidth('xl')
                            ->icon('heroicon-o-eye')
                            ->modalContent(fn($record) => view('filament.ajustes.items-list', [
                                'items' => $record->items()->with('product', 'variant', 'unit')->get(),
                            ]))
                    ),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->prefix('S/ ')
                    ->sortable()
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('tipo')
                    ->badge()
                    ->label('Operación')
                    ->icon(fn(string $state): string => match ($state) {
                        'entrada' => 'heroicon-m-arrow-trending-up',
                        'salida' => 'heroicon-m-arrow-trending-down',
                        default => 'heroicon-m-minus',
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'entrada' => 'success',
                        'salida' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn($state) => ucfirst($state))
                    ->color(fn(string $state): string => match ($state) {
                        'activo' => 'success',
                        'anulado' => 'danger',
                        default => 'gray',
                    }),

            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('anular')
                    ->tooltip('Anular')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anular Ajuste')
                    ->modalDescription('¿Seguro que deseas anular este ajuste? Esto revertirá el movimiento de stock.')
                    ->visible(fn($record) => $record->status !== 'anulado')
                    ->action(function ($record) {
                        (new StockAdjustmentService())->revert($record);
                        $record->update([
                            'status' => 'anulado',
                        ]);
                        Notification::make()
                            ->title('Ajuste anulado')
                            ->body('El stock ha sido revertido correctamente.')
                            ->success()
                            ->send();
                    }),
            ]); // 🟢 Filtros más accesibles
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            // 'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
