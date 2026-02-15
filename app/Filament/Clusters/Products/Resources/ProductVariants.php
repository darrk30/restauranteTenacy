<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Product;
use App\Services\BarcodeLookupService;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Toggle;

class ProductVariants extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.products.pages.product-variants';

    public Product $record;

    public function mount(Product $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return "Variantes de {$this->record->name}";
    }

    protected function getListeners(): array
    {
        return [
            'barcode-scanned' => 'setBarcode',
        ];
    }

    public function setBarcode($data)
    {
        $this->form->fill([
            'barcode' => $data['code'],
        ]);
    }


    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn() => $this->record
                    ->variants()
                    ->with('product')
                    ->whereIn('status', ['activo', 'inactivo'])
            )
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Imagen')
                    ->circular()
                    ->disk('public')
                    ->visibility('public')
                    ->default(asset('img/productdefault.jpg')),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto'),
                    
                    Tables\Columns\TextColumn::make('values')
                    ->label('Variante de producto')
                    ->getStateUsing(
                        fn($record) =>
                        $record->values && $record->values->isNotEmpty()
                        ? $record->values
                        ->map(fn($value) => "{$value->attribute->name}: {$value->name}")
                        ->toArray()
                        : ['Sin variantes']
                        )
                        ->badge()
                        ->colors(['primary']),
                        
                    Tables\Columns\TextColumn::make('costo')
                        ->formatStateUsing(fn($state) => 'S/ ' . number_format($state, 2))
                        ->label('Costo'),
                    Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'activo',
                        'danger' => 'inactivo',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->color('primary')
                    ->modalHeading('Editar Variante')
                    ->fillForm(fn($record) => [
                        'image' => $record->image,
                        'codigo_barras' => $record->codigo_barras,
                        'internal_code' => $record->internal_code,
                        'extra_price' => $record->extra_price,
                        'sale_without_stock' => $record->sale_without_stock,
                        'status' => $record->status,
                        'unit' => $record->unit,
                    ])
                    ->form([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Imagen')
                            ->image()
                            ->imageEditor()
                            ->directory('products/variants')
                            ->disk('public')
                            ->preserveFilenames()
                            ->previewable(true)
                            ->columnSpanFull(), // Para que ocupe todo el ancho arriba

                        // --- FILA 1: Códigos (2 Columnas) ---
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('codigo_barras')
                                    ->label('Código de barras')
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('internal_code')
                                    ->label('Código interno')
                                    ->maxLength(100),
                            ]),

                        // --- FILA 2: Stock, Costo y Estado (3 Columnas) ---
                        Forms\Components\Grid::make(3)
                            ->schema([
                                // 1. Stock Inicial (Tu lógica original intacta)
                                Forms\Components\TextInput::make('stock_inicial')
                                    ->label(fn($record) => $record?->product?->unit?->name ? "Stock inicial ({$record->product->unit->name})" : "Stock inicial")
                                    ->default(0)
                                    ->visible(function ($record) {
                                        if (!$record?->product?->control_stock) return false;
                                        if ($record?->product?->unit?->code === 'ZZ') return false;
                                        if ($record->stock_inicial != false) return false;
                                        return true;
                                    })
                                    ->numeric()
                                    ->step(fn($record) => $record?->product?->unit?->code === 'NIU' ? 1 : 'any')
                                    ->rules(fn($record) => $record?->product?->unit?->code === 'NIU' ? ['required', 'integer', 'min:0'] : ['required', 'numeric', 'min:0'])
                                    ->helperText("Solo se ingresa una vez."),

                                // 2. Costo (Agregado según tu petición)
                                Forms\Components\TextInput::make('costo')
                                    ->label('Costo')
                                    ->numeric()
                                    ->prefix('S/.') // Opcional: símbolo de moneda
                                    ->default(0),

                                // 3. Estado (Switch)
                                Toggle::make('status')
                                    ->label('Estado')
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->inline(false) // Pone la etiqueta ARRIBA del switch, alineándolo visualmente con los inputs de texto
                                    // 1. Al LEER: Convertimos 'activo' a true, cualquier otra cosa a false
                                    ->formatStateUsing(fn($state) => $state === 'activo')
                                    // 2. Al GUARDAR: Si es true guardamos 'activo', si es false guardamos 'inactivo'
                                    ->dehydrateStateUsing(fn($state) => $state ? 'activo' : 'inactivo'),
                            ]),
                    ])
                    ->fillForm(fn($record) => $record->toArray())
                    ->action(function (array $data, $record): void {

                        // Guardar stock inicial solo si viene en el formulario
                        if (isset($data['stock_inicial']) && $record->stock_inicial_asignado == false) {

                            $cantidad = (int) $data['stock_inicial'];

                            if ($cantidad > 0) {
                                $warehouse = \App\Models\Warehouse::where('restaurant_id', filament()->getTenant()->id)
                                    ->orderBy('order')
                                    ->first();

                                if ($warehouse) {
                                    $stock = \App\Models\WarehouseStock::firstOrCreate(
                                        [
                                            'warehouse_id' => $warehouse->id,
                                            'variant_id' => $record->id,
                                        ],
                                        [
                                            'stock_real' => 0,
                                            'stock_reserva' => 0,
                                            'min_stock' => 0,
                                            'restaurant_id' => filament()->getTenant()->id,
                                        ]
                                    );
                                    $stock->update([
                                        'stock_real' => $stock->stock_real + $cantidad,
                                        'stock_reserva' => $stock->stock_real + $cantidad
                                    ]);

                                    $record->kardexes()->create([
                                        'product_id'      => $record->product_id,
                                        'variant_id'      => $record->id,
                                        'restaurant_id'   => filament()->getTenant()->id,
                                        'tipo_movimiento' => 'Stock Inicial',
                                        'cantidad'        => $cantidad,
                                        'stock_restante'  => $stock->stock_real,
                                        'modelo_type'     => ProductVariants::class,
                                        'modelo_id'       => $record->id,
                                        'comprobante'     => 'STOCK-INICIAL',
                                    ]);
                                }
                                $record->update(['stock_inicial' => true,]);
                            }

                            // No permitir guardar este dato nuevamente
                            unset($data['stock_inicial']);
                        }
                        $record->update($data);

                        Notification::make()
                            ->title('Variante actualizada correctamente')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false);
    }

    public function getBreadcrumbs(): array
    {
        return [
            ProductResource::getUrl('index') => 'Productos',
            ProductResource::getUrl('edit', ['record' => $this->record]) => $this->record->name,
            url()->current() => 'Listado de Variantes',
        ];
    }
}
