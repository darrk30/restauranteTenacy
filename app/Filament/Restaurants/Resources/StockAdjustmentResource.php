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
use Filament\Tables;
use Filament\Forms\Form;
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
                Forms\Components\Grid::make(12) // 🟢 Definimos una rejilla de 12 columnas
                    ->schema([
                        // --- SECCIÓN IZQUIERDA (50%) ---
                        Forms\Components\Section::make('Configuración General')
                            ->description('Detalles del movimiento')
                            ->schema([
                                Forms\Components\Select::make('tipo')
                                    ->label('Tipo de Movimiento')
                                    ->options([
                                        'entrada' => '➕ Entrada (Aumenta Stock)',
                                        'salida' => '➖ Salida (Disminuye Stock)',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->preload(),

                                Forms\Components\Textarea::make('motivo')
                                    ->label('Razón del Ajuste')
                                    ->placeholder('Ej: Rotura de stock, inventario mensual, merma...')
                                    ->required()
                                    ->rows(3),
                            ])
                            ->columnSpan(6), // 🟢 6 de 12 es el 50%

                        // --- SECCIÓN DERECHA (50%) ---
                        Forms\Components\Section::make('Resumen')
                            ->description('Información de control')
                            ->schema([
                                Forms\Components\Placeholder::make('created_at')
                                    ->label('Fecha de registro')
                                    ->content(now()->format('d/m/Y h:i A')),

                                Forms\Components\Placeholder::make('usuario')
                                    ->label('Responsable')
                                    ->content(Auth::user()->name),

                                // Agregamos un pequeño aviso visual
                                Forms\Components\Placeholder::make('info')
                                    ->label('Nota')
                                    ->content('Los cambios afectarán el stock actual de forma inmediata.')
                            ])
                            ->columnSpan(6), // 🟢 6 de 12 es el 50%
                    ]),

                Forms\Components\Section::make('Listado de Productos a Ajustar')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->columns(6) // 🟢 Todo en una línea para ahorrar espacio
                            ->addActionLabel('Agregar otro producto')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto / Insumo')
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
                                    ->columnSpan(2)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('variant_id', null);
                                        if ($state) {
                                            $product = Product::find($state);
                                            $set('unit_id', $product?->unit_id);
                                        }
                                    }),

                                Select::make('variant_id')
                                    ->columnSpan(2)
                                    ->options(function ($get) {
                                        $productId = $get('product_id');
                                        if (blank($productId)) {
                                            return [];
                                        }
                                        return Variant::where('product_id', $productId)
                                            ->where('status', 'activo')
                                            ->get()
                                            ->pluck('full_name', 'id');
                                    }),

                                Forms\Components\TextInput::make('cantidad')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('unit_id')
                                    ->label('Unidad')
                                    ->options(function ($get) {
                                        $product = Product::with('unit')->find($get('product_id'));
                                        return $product?->unit ? Unit::where('unit_category_id', $product->unit->unit_category_id)->pluck('name', 'id') : [];
                                    })
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->collapsible()
                            ->cloneable() // 🟢 Permite duplicar filas rápido
                            ->itemLabel(
                                fn($state) =>
                                isset($state['variant_id']) ? Variant::find($state['variant_id'])?->full_name : 'Seleccione producto'
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->copyable() // 🟢 Permite copiar el código rápido
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->status === 'anulado' ? "<s>$state</s>" : $state
                    )->html(),
                Tables\Columns\TextColumn::make('user.name') 
                    ->label('Responsable')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-m-user') // Icono para identificarlo rápido
                    ->color('gray')
                    ->description(fn($record) => $record->user->email ?? ''), // Opcional: muestra el correo debajo del nombre

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Ajuste')
                    ->sortable()
                    ->description(fn($record) => $record->created_at->diffForHumans()) // 🟢 Agrega "hace 2 horas"
                    ->dateTime('d/m/Y h:i A'),

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

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Observación')
                    ->limit(30) // 🟢 Acorta textos largos
                    ->tooltip(fn($state) => $state)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
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
