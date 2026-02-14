<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Warehouse;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DateTimePicker;
use App\Filament\Restaurants\Resources\StockAdjustmentResource\Pages\ListStockAdjustments;
use App\Filament\Restaurants\Resources\StockAdjustmentResource\Pages\CreateStockAdjustment;
use App\Enums\StatusProducto;
use App\Enums\TipoProducto;
use App\Filament\Restaurants\Resources\StockAdjustmentResource\Pages;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Variant;
use App\Models\Unit;
use App\Services\StockAdjustmentService;
use App\Traits\ManjoStockProductos;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Tables;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Resources\Resource;

class StockAdjustmentResource extends Resource
{
    use ManjoStockProductos;
    protected static ?string $model = StockAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    // protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $navigationLabel = 'Ajustes de Stock';
    protected static ?string $pluralLabel = 'Ajustes de Stock';
    protected static ?string $label = 'Ajuste de Stock';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Información del ajuste')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Select::make('tipo')
                                            ->label('Tipo de ajuste')
                                            ->options([
                                                'entrada' => 'Entrada',
                                                'salida' => 'Salida',
                                            ])
                                            ->required(),
                                    ])
                                    ->columnSpan(1),
                                Textarea::make('motivo')
                                    ->label('Motivo del ajuste')
                                    ->rows(5)
                                    ->columnSpan(1),

                            ]),
                    ]),

                Section::make('Items del ajuste')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto')
                                    ->relationship('product', 'name', function ($query) {
                                        $query->whereIn('type', [
                                            TipoProducto::Producto->value,
                                            TipoProducto::Insumo->value,
                                        ])
                                            ->where('control_stock', true)
                                            ->where('status', StatusProducto::Activo->value); // solo activos
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->columnSpan(2)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (!$state) {
                                            $set('variant_id', null);
                                            $set('unit_id', null);
                                            return;
                                        }
                                        $variants = Variant::where('product_id', $state)->where('status', 'activo')->get();
                                        $product = Product::with('unit')->find($state);
                                        if ($variants->count() === 1) {
                                            $set('variant_id', $variants->first()->id);
                                        } else {
                                            $set('variant_id', null);
                                        }
                                        if ($product?->unit?->id) {
                                            $set('unit_id', $product->unit->id);
                                        }
                                    }),

                                // VARIANTE
                                Select::make('variant_id')
                                    ->label('Variante')
                                    ->options(function (callable $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
                                            return [];
                                        }
                                        return Variant::where('product_id', $productId)->where('status', 'activo')->get()->pluck('full_name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),
                                Select::make('warehouse_id')
                                    ->label('Almacén')
                                    ->relationship('warehouse', 'name')
                                    ->default(fn() => Warehouse::query()->first()?->id)
                                    ->required(),

                                TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required(),

                                Select::make('unit_id')
                                    ->label('Unidad')
                                    ->options(function (callable $get) {
                                        $product = Product::with('unit')->find($get('product_id'));
                                        $categoryId = $product?->unit?->unit_category_id;
                                        return $categoryId ? Unit::where('unit_category_id', $categoryId)->pluck('name', 'id') : [];
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->required(),

                            ])
                            ->defaultItems(1)
                            ->columns(6)
                            ->collapsible()
                            ->itemLabel(
                                fn($state) => $state['variant_id'] ? Variant::find($state['variant_id'])?->full_name : 'Nuevo ítem'
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchPlaceholder('Busca por codigo y almacén')
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->status === 'anulado') {
                            return "<span style='text-decoration: line-through; color: #dc2626;'>$state</span>";
                        }
                        return $state;
                    })
                    ->html(),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return '
                    <div style="display:flex; flex-direction:column; line-height:1.2;">
                        <span>' . Carbon::parse($state)->timezone('America/Lima')->format('d/m/Y') . '</span>
                        <span>' . Carbon::parse($state)->timezone('America/Lima')->format('h:i A') . '</span>
                    </div>';
                    })
                    ->html(),

                TextColumn::make('tipo')
                    ->badge()
                    ->label('Tipo')
                    ->colors([
                        'success' => 'incremento',
                        'danger'  => 'decremento',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->placeholder('Sin motivo')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable()
                    ->tooltip('Ver items')
                    ->alignment('center')
                    ->html()
                    ->action(
                        Action::make('verItems')
                            ->modalHeading('Productos')
                            ->modalWidth('lg')
                            ->modalContent(function ($record) {
                                return view('filament.ajustes.items-list', [
                                    'items' => $record->items()->with('product')->get(),
                                ]);
                            })
                    ),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'activo',
                        'danger' => 'anulado',
                    ]),
            ])

            ->filters([
                Filter::make('created_at')
                    ->schema([
                        DateTimePicker::make('from')
                            ->label('Desde')
                            ->displayFormat('d/m/Y h:i A') // Vista 12h
                            ->format('Y-m-d h:i A'),       // Formato guardado

                        DateTimePicker::make('until')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y h:i A') // Vista 12h
                            ->format('Y-m-d h:i A'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q, $date) => $q->where('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn($q, $date) => $q->where('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('anular')
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
            ])

            ->toolbarActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
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
            'index' => ListStockAdjustments::route('/'),
            'create' => CreateStockAdjustment::route('/create'),
            // 'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
