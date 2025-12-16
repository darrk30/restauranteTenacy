<?php

namespace App\Filament\Restaurants\Resources;

use App\Enums\StatusProducto;
use App\Enums\TipoProducto;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Filament\Restaurants\Resources\PurchaseResource\Pages;
use App\Filament\Restaurants\Resources\PurchaseResource\Widgets\PurchaseStats;
use App\Models\Purchase;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Services\ProductService;
use App\Services\PurcharseService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\HtmlString;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Compras';
    protected static ?string $navigationGroup = 'Inventario';



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos generales')
                    ->schema([
                        Select::make('tipo_documento')
                            ->label('Tipo de comprobante')
                            ->options([
                                'FACTURA' => 'Factura',
                                'BOLETA'  => 'Boleta',
                                'TICKET'  => 'Ticket',
                                'OTRO'    => 'Otro',
                            ])
                            ->required(),

                        TextInput::make('serie')
                            ->label('Serie')
                            ->placeholder('B001')
                            ->maxLength(10)
                            ->required(),

                        TextInput::make('numero')
                            ->label('Número')
                            ->placeholder('123456')
                            ->numeric()
                            ->required(),

                        Select::make('supplier_id')
                            ->label('Proveedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(), // siempre ancho completo

                        Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->columnSpanFull(), // siempre ancho completo
                    ])
                    ->columns(['default' => 1, 'md' => 3, 'xl' => 3,]),

                Section::make('Estados')
                    ->schema([
                        DatePicker::make('fecha_compra')
                            ->label('Fecha de compra')
                            ->default(now())
                            ->required(),
                        Select::make('estado_despacho')
                            ->label('Estado de despacho')
                            ->options([
                                'pendiente' => 'Pendiente',
                                'recibido' => 'Recibido',
                            ])
                            ->default('recibido')
                            ->required(),

                        Select::make('estado_pago')
                            ->label('Estado de pago')
                            ->options([
                                'pendiente' => 'Pendiente',
                                'pagado' => 'Pagado',
                            ])
                            ->default('pendiente')
                            ->required(),
                    ])->columns(['default' => 1, 'md' => 3, 'xl' => 3,]),

                Section::make('Productos')
                    ->schema([
                        Repeater::make('details')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Grid::make()
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3,])
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Producto')
                                            ->relationship('product', 'name', function ($query) {
                                                $query->whereIn('type', [TipoProducto::Producto->value, TipoProducto::Insumo->value,])
                                                    ->where('control_stock', true)
                                                    ->where('status', StatusProducto::Activo->value);
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->required()
                                            ->suffixAction(
                                                Action::make('createProduct')
                                                    ->label('Nuevo')
                                                    ->icon('heroicon-o-plus')
                                                    ->modalHeading('Crear producto')
                                                    ->modalSubmitActionLabel('Guardar')
                                                    ->form(ProductResource::getSchema())
                                                    ->action(function (array $data, Set $set) {
                                                        try {
                                                            $data = (new ProductService())->validateAndGenerateSlug($data);
                                                        } catch (\Exception $e) {
                                                            Notification::make()
                                                                ->title('No se pudo crear el producto')
                                                                ->body($e->getMessage())
                                                                ->danger()
                                                                ->send();
                                                            throw new Halt();
                                                        }
                                                        $categories = $data['categories'] ?? [];
                                                        unset($data['categories']);
                                                        $unidId = $data['unid_id'] ?? null;
                                                        $product = Product::create($data);
                                                        if (!empty($categories)) {
                                                            $product->categories()->sync($categories);
                                                        }
                                                        if (!empty($unidId)) {
                                                            $product->unit()->associate($unidId);
                                                            $product->save();
                                                        }
                                                        (new ProductService())->handleAfterCreate($product, $data);
                                                        $set('product_id', $product->id);
                                                    })
                                            )
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

                                        Select::make('variant_id')
                                            ->label('Variante')
                                            ->options(function (callable $get) {
                                                $productId = $get('product_id');
                                                if (!$productId) {
                                                    return [];
                                                }
                                                return Variant::where('product_id', $productId)
                                                    ->where('status', 'activo')
                                                    ->get()
                                                    ->pluck('full_name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        Select::make('warehouse_id')
                                            ->label('Almacén')
                                            ->relationship('warehouse', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->default(function () {
                                                return Warehouse::query()->orderBy('id')->value('id'); // primer almacén
                                            })
                                            ->required(),
                                    ]),

                                // OTRO GRID PARA LOS CAMPOS NUMÉRICOS
                                Grid::make()
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4,])
                                    ->schema([
                                        TextInput::make('cantidad')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->placeholder(0.00)
                                            ->minValue(0.01)
                                            ->reactive()
                                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateLine($get, $set))
                                            ->required(),


                                        Select::make('unit_id')
                                            ->label('Unidad')
                                            ->options(function (callable $get) {
                                                $product = Product::with('unit')->find($get('product_id'));
                                                $categoryId = $product?->unit?->unit_category_id;
                                                return $categoryId
                                                    ? Unit::where('unit_category_id', $categoryId)->pluck('name', 'id')
                                                    : [];
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        TextInput::make('costo')
                                            ->label('Costo')
                                            ->numeric()
                                            ->placeholder(0.00)
                                            ->prefix('S/')
                                            ->reactive()
                                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateLine($get, $set))
                                            ->required(),

                                        TextInput::make('subtotal')
                                            ->label('Subtotal')
                                            ->numeric()
                                            ->placeholder(0.00)
                                            ->prefix('S/')
                                            ->readOnly(),
                                    ]),
                            ])
                            ->columns(['default' => 1, 'md' => 2, 'xl' => 4,])
                            ->defaultItems(1)
                            ->reactive()
                            ->addActionLabel('Agregar producto'),
                    ]),


                Section::make('')
                    ->heading(fn() => new HtmlString(
                        '<div class="flex items-center w-full">
                            <span>Métodos de pago</span>
                            <span class="text-gray-500 font-normal">(opcional)</span>
                        </div>'
                    ))->schema([
                        Repeater::make('paymentMethods')
                            ->label('')
                            ->relationship('paymentMethods')
                            ->rules([
                                fn(Get $get) =>
                                $get('estado_pago') === 'pagado'
                                    ? 'required|array|min:1'
                                    : 'nullable',
                            ])
                            ->saveRelationshipsUsing(function ($record, array $state) {
                                $filtered = collect($state)->filter(function ($item) {
                                    return !empty($item['payment_method_id']) || !empty($item['monto']);
                                })->map(function ($item) {
                                    $item['monto'] = (float) ($item['monto'] ?? 0);
                                    return $item;
                                });

                                // Guardar pagos
                                $record->paymentMethods()->delete();
                                $record->paymentMethods()->createMany($filtered->toArray());

                                // ---- CALCULAR SALDO ----
                                $totalPagado = $filtered->sum('monto');
                                $saldo = max(($record->total ?? 0) - $totalPagado, 0);

                                // Guardar el saldo actualizado
                                $record->update(['saldo' => $saldo]);
                            })

                            ->schema([
                                Select::make('payment_method_id')
                                    ->label('Método')
                                    ->relationship('paymentMethod', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->reactive()
                                    ->afterStateUpdated(fn($state, callable $set) => $set('referencia', null)),

                                TextInput::make('monto')
                                    ->label('Monto')
                                    ->numeric()
                                    ->nullable()
                                    ->prefix('S/'),

                                TextInput::make('referencia')
                                    ->label('Referencia')
                                    ->maxLength(50)
                                    ->nullable(),
                            ])
                            ->columns(['default' => 1, 'md' => 3, 'xl' => 3,])
                            ->addActionLabel('Agregar pago'),
                    ]),

                Section::make('Totales y estado')
                    ->schema([
                        TextInput::make('costo_envio')
                            ->label('Costo de envío')
                            ->numeric()
                            ->placeholder('0.00')
                            ->prefix('S/')
                            ->dehydrateStateUsing(fn($state) => $state === null || $state === '' ? 0 : $state),


                        TextInput::make('descuento')
                            ->label('Descuento')
                            ->numeric()
                            ->placeholder('0.00')
                            ->prefix('S/')
                            ->reactive()
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateTotals($get, $set, goUpTwoLevels: false))
                            ->dehydrateStateUsing(fn($state) => $state === null || $state === '' ? 0 : $state),


                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->default(0)
                            ->prefix('S/')
                            ->readOnly(),

                        TextInput::make('igv')
                            ->label('IGV (18%)')
                            ->numeric()
                            ->default(0)
                            ->prefix('S/')
                            ->readOnly(),

                        TextInput::make('total')
                            ->label('Total general')
                            ->numeric()
                            ->default(0)
                            ->prefix('S/')
                            ->readOnly()
                            ->required(),
                    ])->columns(['default' => 2, 'md' => 3, 'xl' => 5,]),
            ]);
    }

    private static function recalculateLine(Get $get, Set $set): void
    {
        $cantidad = (float) ($get('cantidad') ?? 0);
        $costo = (float) ($get('costo') ?? 0);
        $subtotal = $cantidad * $costo;
        $set('subtotal', round($subtotal, 2));
        self::recalculateTotals($get, $set, goUpTwoLevels: true);
    }

    private static function recalculateTotals(Get $get, Set $set, bool $goUpTwoLevels = false): void
    {
        // subir niveles según de dónde venga
        $path = $goUpTwoLevels ? '../../' : '';

        $details = collect($get($path . 'details') ?? []);
        $subtotalBruto = $details->sum(fn($item) => (float) ($item['subtotal'] ?? 0));

        if ($subtotalBruto <= 0) {
            $set($path . 'subtotal', 0);
            $set($path . 'igv', 0);
            $set($path . 'total', 0);
            return;
        }

        // BASE + IGV
        $base = $subtotalBruto / 1.18;
        $igv = $subtotalBruto - $base;

        // DESCUENTO
        $descuento = (float) ($get($path . 'descuento') ?? 0);

        if ($descuento > 0 && $descuento < $subtotalBruto) {
            $factor = 1 - ($descuento / $subtotalBruto);
            $base *= $factor;
            $igv *= $factor;
        }

        $total = $base + $igv;

        $set($path . 'subtotal', round($base, 2));
        $set($path . 'igv', round($igv, 2));
        $set($path . 'total', round($total, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->html()
                    ->searchable(['serie', 'numero']) // 👈 BUSCA POR CAMPOS REALES
                    ->getStateUsing(function ($record) {
                        $tipo = $record->tipo_documento;
                        $color = $tipo->getColor();
                        $label = $tipo->getLabel();

                        // Colores manuales
                        $colors = [
                            'success' => ['bg' => '#DCFCE7', 'text' => '#166534'],
                            'info'    => ['bg' => '#DBEAFE', 'text' => '#1E3A8A'],
                            'gray'    => ['bg' => '#E5E7EB', 'text' => '#374151'],
                        ];

                        $bg = $colors[$color]['bg'] ?? $colors['gray']['bg'];
                        $text = $colors[$color]['text'] ?? $colors['gray']['text'];

                        return "
                            <div style='font-weight:600; font-size:14px;'>
                                {$record->serie}-{$record->numero}
                            </div>

                            <span style='
                                display:inline-block;
                                padding:0px 8px;
                                font-size:11px;
                                font-weight:500;
                                border-radius:10px;
                                background:{$bg};
                                color:{$text};
                            '>
                                {$label}
                            </span>
                        ";
                    }),

                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('costo_envio')
                    ->label('Costo de envío')
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format($state, 2))
                    ->sortable()
                    ->color('success') // Verde siempre
                    ->badge(),

                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format($state, 2))
                    ->sortable()
                    ->color('success') // Verde siempre
                    ->badge(),

                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->formatStateUsing(fn($state) => 'S/ ' . number_format($state, 2))
                    ->sortable()
                    ->badge()
                    ->color(
                        fn($record) =>
                        $record->saldo > 0
                            ? 'warning' // amarillo si hay saldo
                            : 'success' // verde si está en 0
                    ),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->sortable()
                    ->icon(fn($state) => match ($state) {
                        'pagado' => 'heroicon-o-check-circle',
                        'pendiente' => 'heroicon-o-clock',
                        'cancelado' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn($state) => match ($state) {
                        'pagado' => 'success',
                        'pendiente' => 'warning',
                        'cancelado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('estado_despacho')
                    ->label('Despacho')
                    ->badge()
                    ->icon(fn($state) => match ($state) {
                        'pendiente' => 'heroicon-o-clock',
                        'recibido' => 'heroicon-o-truck',
                        'cancelado' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn($state) => match ($state) {
                        'pendiente' => 'warning',
                        'recibido' => 'success',
                        'cancelado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('estado_comprobante')
                    ->label('Estado')
                    ->badge()
                    ->sortable()
                    ->icon(fn($state) => match ($state) {
                        'aceptado' => 'heroicon-o-check-circle',
                        'anulado' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn($state) => match ($state) {
                        'aceptado' => 'success',
                        'anulado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),
                TextColumn::make('fecha_compra')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('pagos')
                        ->label('Pagos')
                        ->icon('heroicon-o-banknotes')
                        ->modalHeading('Pagos de la compra')
                        ->form(function ($record) {

                            $schema = [
                                Placeholder::make('pagos_realizados')
                                    ->label('')
                                    ->content(function ($record) {
                                        $pagos = $record->paymentMethods()->with('paymentMethod')->get();
                                        return new \Illuminate\Support\HtmlString(
                                            view('filament.purchase.pagos-table', [
                                                'pagos' => $pagos,
                                                'saldo' => $record->saldo,
                                            ])->render()
                                        );
                                    })
                                    ->columnSpanFull(),
                            ];

                            // Solo mostrar formulario si hay saldo pendiente
                            if ($record->saldo > 0) {
                                $schema[] = Section::make('Agregar nuevo pago')
                                    ->schema([
                                        Select::make('payment_method_id')
                                            ->label('Método de pago')
                                            ->options(PaymentMethod::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        TextInput::make('monto')
                                            ->label('Monto')
                                            ->numeric()
                                            ->required()
                                            ->prefix('S/'),

                                        TextInput::make('referencia')
                                            ->label('Referencia')
                                            ->maxLength(100),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull();
                            }

                            return $schema;
                        })
                        ->modalFooterActions(function ($record) {
                            // Si no hay saldo, ocultar botones del formulario
                            if ($record->saldo <= 0) {
                                return [];
                            }

                            // Si hay saldo → mostrar botón submit normal
                            return [
                                \Filament\Actions\Action::make('submit')
                                    ->label('Agregar pago')
                                    ->submit('submit'),
                            ];
                        })
                        ->action(function ($data, $record, $livewire) {

                            if ($record->saldo <= 0) {
                                return;
                            }

                            $record->paymentMethods()->create([
                                'payment_method_id' => $data['payment_method_id'],
                                'monto' => $data['monto'],
                                'referencia' => $data['referencia'] ?? null,
                            ]);

                            // Recalcular saldo
                            $totalPagado = $record->paymentMethods()->sum('monto');
                            $saldo = max($record->total - $totalPagado, 0);

                            $record->update(['saldo' => $saldo]);

                            $livewire->dispatch('refresh');

                            \Filament\Notifications\Notification::make()
                                ->title('Pago agregado exitosamente')
                                ->success()
                                ->send();
                        }),


                    Tables\Actions\Action::make('anular')
                        ->label('Anular')
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->estado_comprobante = 'anulado';
                            $record->estado_pago = 'cancelado';
                            $record->estado_despacho = 'cancelado';
                            $record->save();

                            $service = new PurcharseService();
                            $service->revert($record);
                        }),
                ])->visible(fn($record) => $record->estado_comprobante !== 'anulado')
            ])
            ->filters([
                Tables\Filters\Filter::make('fecha')
                    ->default(true)
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q) => $q->whereDate('fecha_compra', '>=', $data['desde']))
                            ->when($data['hasta'], fn($q) => $q->whereDate('fecha_compra', '<=', $data['hasta']));
                    })
                    ->default([
                        'desde' => now()->startOfMonth()->toDateString(),
                        'hasta' => now()->toDateString(),
                    ]),

                Tables\Filters\SelectFilter::make('estado_comprobante')
                    ->label('Estado de comprobante')
                    ->options([
                        'aceptado' => 'Aceptado',
                        'anulado' => 'Anulado',
                    ]),
            ])

            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ])->recordUrl(null);
    }

    public static function getRelations(): array
    {
        return [
            // Puedes añadir RelationManagers si deseas ver productos o pagos
        ];
    }

    public static function getWidgets(): array
    {
        return [
            PurchaseStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}
