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

                Forms\Components\Section::make('Información del ajuste')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Grid::make(1)
                                    ->schema([
                                        Forms\Components\Select::make('tipo')
                                            ->label('Tipo de ajuste')
                                            ->options([
                                                'entrada' => 'Entrada',
                                                'salida' => 'Salida',
                                            ])
                                            ->required(),
                                    ])
                                    ->columnSpan(1),
                                Forms\Components\Textarea::make('motivo')
                                    ->label('Motivo del ajuste')
                                    ->rows(5)
                                    ->columnSpan(1),

                            ]),
                    ]),

                Forms\Components\Section::make('Items del ajuste')
                    ->schema([
                        Forms\Components\Repeater::make('items')
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

                                Forms\Components\TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required(),

                                Forms\Components\Select::make('unit_id')
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
                Tables\Columns\TextColumn::make('codigo')
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return '
                    <div style="display:flex; flex-direction:column; line-height:1.2;">
                        <span>' . \Carbon\Carbon::parse($state)->timezone('America/Lima')->format('d/m/Y') . '</span>
                        <span>' . \Carbon\Carbon::parse($state)->timezone('America/Lima')->format('h:i A') . '</span>
                    </div>';
                    })
                    ->html(),

                Tables\Columns\TextColumn::make('tipo')
                    ->badge()
                    ->label('Tipo')
                    ->colors([
                        'success' => 'incremento',
                        'danger'  => 'decremento',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->placeholder('Sin motivo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable()
                    ->tooltip('Ver items')
                    ->alignment('center')
                    ->html()
                    ->action(
                        Tables\Actions\Action::make('verItems')
                            ->modalHeading('Productos')
                            ->modalWidth('lg')
                            ->modalContent(function ($record) {
                                return view('filament.ajustes.items-list', [
                                    'items' => $record->items()->with('product')->get(),
                                ]);
                            })
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'activo',
                        'danger' => 'anulado',
                    ]),
            ])

            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DateTimePicker::make('from')
                            ->label('Desde')
                            ->displayFormat('d/m/Y h:i A') // Vista 12h
                            ->format('Y-m-d h:i A'),       // Formato guardado

                        Forms\Components\DateTimePicker::make('until')
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
            ])

            ->bulkActions([
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
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            // 'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
