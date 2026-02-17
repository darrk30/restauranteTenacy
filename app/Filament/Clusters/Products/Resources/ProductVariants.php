<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Product;
use App\Models\WarehouseStock; // Asegúrate de importar el modelo de Stock
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
        // Nota: Esto solo funciona si el formulario está montado en la página principal,
        // pero en esta vista de tabla, el formulario está dentro de un modal (Action).
        // Si necesitas escanear dentro del modal, la lógica es diferente.
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn() => $this->record
                    ->variants()
                    ->with('product', 'stock') // Cargamos la relación de stock único
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
                    ->label('Variante')
                    ->getStateUsing(
                        fn($record) =>
                        $record->values && $record->values->isNotEmpty()
                            ? $record->values->map(fn($value) => "{$value->attribute->name}: {$value->name}")->toArray()
                            : ['Sin variantes']
                    )
                    ->badge()
                    ->colors(['primary']),

                // 🟢 STOCK ACTUALIZADO (Sin almacenes)
                Tables\Columns\TextColumn::make('stock.stock_real')
                    ->label('Stock Actual')
                    ->numeric()
                    ->alignCenter()
                    ->placeholder('0'),

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
                        // Llenamos los datos existentes
                        'image_path' => $record->image_path,
                        'codigo_barras' => $record->codigo_barras,
                        'internal_code' => $record->internal_code,
                        'costo' => $record->costo,
                        'status' => $record->status,
                        // No llenamos 'stock_inicial' porque es un campo de un solo uso
                    ])
                    ->form([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Imagen')
                            ->image()
                            ->imageEditor()
                            ->directory('products/variants')
                            ->disk('public')
                            ->preserveFilenames()
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('codigo_barras')
                                    ->label('Código de barras')
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('internal_code')
                                    ->label('Código interno')
                                    ->maxLength(100),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                // 1. STOCK INICIAL (Simplificado)
                                Forms\Components\TextInput::make('stock_inicial')
                                    ->label(fn($record) => $record?->product?->unit?->name ? "Stock inicial ({$record->product->unit->name})" : "Stock inicial")
                                    ->default(0)
                                    // Solo visible si controla stock Y nunca se ha asignado stock inicial
                                    ->visible(fn($record) => $record->product->control_stock && !$record->stock_inicial)
                                    ->numeric()
                                    ->step(fn($record) => $record?->product?->unit?->code === 'NIU' ? 1 : 'any')
                                    ->rules(fn($record) => $record?->product?->unit?->code === 'NIU' ? ['required', 'integer', 'min:0'] : ['required', 'numeric', 'min:0'])
                                    ->helperText("Solo se ingresa una vez."),

                                // 2. COSTO
                                Forms\Components\TextInput::make('costo')
                                    ->label('Costo')
                                    ->numeric()
                                    ->prefix('S/.')
                                    ->default(0),

                                // 3. ESTADO
                                Toggle::make('status')
                                    ->label('Estado')
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->inline(false)
                                    ->formatStateUsing(fn($state) => $state === 'activo')
                                    ->dehydrateStateUsing(fn($state) => $state ? 'activo' : 'inactivo'),
                            ]),
                    ])
                    ->action(function (array $data, $record): void {

                        // --- LÓGICA DE STOCK INICIAL CENTRALIZADO ---
                        if (isset($data['stock_inicial']) && !$record->stock_inicial) {
                            $cantidad = (float) $data['stock_inicial'];

                            if ($cantidad > 0) {
                                // Buscamos o creamos el stock único de la variante
                                $stock = WarehouseStock::firstOrCreate(
                                    ['variant_id' => $record->id],
                                    [
                                        'stock_real' => 0,
                                        'stock_reserva' => 0,
                                        'min_stock' => 0,
                                        'restaurant_id' => filament()->getTenant()->id,
                                    ]
                                );

                                // Actualizamos los valores
                                $stock->increment('stock_real', $cantidad);
                                $stock->increment('stock_reserva', $cantidad);

                                // Registramos en Kardex (Sin almacén)
                                $record->kardexes()->create([
                                    'product_id'      => $record->product_id,
                                    'variant_id'      => $record->id,
                                    'restaurant_id'   => filament()->getTenant()->id,
                                    'tipo_movimiento' => 'Stock Inicial',
                                    'cantidad'        => $cantidad,
                                    'stock_restante'  => $stock->stock_real,
                                    'modelo_type'     => get_class($record), // Ojo: esto guardará App\Filament...ProductVariants
                                    'modelo_id'       => $record->id,
                                    'comprobante'     => 'STOCK-INICIAL',
                                ]);

                                // Marcamos que ya se asignó stock inicial
                                $record->stock_inicial = true;
                            }
                            // Limpiamos el dato para que no intente guardarse en la tabla variants
                            unset($data['stock_inicial']);
                        }

                        // Actualizamos la variante con el resto de datos
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
