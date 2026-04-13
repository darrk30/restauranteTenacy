<?php

namespace App\Filament\Restaurants\Pages\Facturacion;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Variant;
use App\Models\DocumentSerie;
use App\Models\CreditDebitNote;
use App\Enums\MotivoNotaCredito;
use App\Enums\DocumentSeriesType;
use App\Traits\ManjoStockProductos; // 🟢 Importamos el Trait
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class EmitirNota extends Page
{
    use ManjoStockProductos; // 🟢 Usamos el Trait

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.facturacion.emitir-nota';
    protected static ?string $title = 'Emitir Nota Electrónica';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'emitir-nota/{record}';

    public ?array $data = [];
    public Sale $sale;

    public static function canAccess(): bool
    {
        if (! Filament::getTenant()) {
            return false;
        }

        $user = auth()->user();

        if ($user->hasRole('Super Admin')) {
            return false;
        }

        try {
            return $user->hasPermissionTo('emitir_nota_rest');
        } catch (\Exception $e) {
            return false;
        }
    }

    public function mount($record): void
    {
        // 🟢 1. Añadimos 'details.promotion.promotionproducts.product' para ver dentro del combo
        $this->sale = Sale::with([
            'client',
            'details.product',
            'details.promotion.promotionproducts.product'
        ])->findOrFail($record);

        $hasStockProducts = false;

        $items = $this->sale->details->map(function ($item) use (&$hasStockProducts) {
            $cantidad = (float) $item->cantidad;
            $precio = (float) $item->precio_unitario;

            // Si no tiene product_id pero tiene promotion_id, es una promoción
            $isPromo = is_null($item->product_id) && !is_null($item->promotion_id);

            // 🟢 2A. Verificamos si es un producto directo y controla stock
            if ($item->product && $item->product->control_stock) {
                $hasStockProducts = true;
            }

            // 🟢 2B. NUEVO: Si es promoción, buscamos en sus productos internos
            if ($isPromo && $item->promotion && $item->promotion->promotionproducts) {
                foreach ($item->promotion->promotionproducts as $promoProduct) {
                    if ($promoProduct->product && $promoProduct->product->control_stock) {
                        $hasStockProducts = true;
                        break; // Con que uno solo controle stock, ya necesitamos mostrar el Switch
                    }
                }
            }

            return [
                'product_id'      => $item->product_id,
                'variant_id'      => $item->variant_id,
                'promotion_id'    => $item->promotion_id,
                'product_name'    => $item->product_name ?? ($isPromo ? $item->promotion->name : $item->descripcion),
                'cantidad'        => number_format($cantidad, 2, '.', ''),
                'precio_unitario' => number_format($precio, 2, '.', ''),
                'subtotal'        => number_format($cantidad * $precio, 2, '.', ''),
                'es_promocion'    => $isPromo,
            ];
        })->toArray();

        $this->form->fill([
            'fecha_emision'      => now()->format('Y-m-d'),
            'tipo_nota'          => '07',
            'cod_motivo'         => '',
            'des_motivo'         => '',
            'items'              => $items,
            'has_stock_products' => $hasStockProducts, // 🟢 Ahora sí detecta combos con stock
            'devolver_stock'     => false,
        ]);
    }

    public static function recalculateLine(Get $get, Set $set): void
    {
        $cantidad = (float) ($get('cantidad') ?: 0);
        $precio = (float) ($get('precio_unitario') ?: 0);
        $set('subtotal', number_format($cantidad * $precio, 2, '.', ''));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // 🟢 1. DATOS GENERALES DE LA NOTA
                Section::make('Datos de la Nota')
                    ->description('Referencia: ' . $this->sale->tipo_comprobante . ' ' . $this->sale->serie . '-' . $this->sale->correlativo)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([

                            Hidden::make('has_stock_products'), // 🟢 Variable oculta de control

                            Placeholder::make('cliente')
                                ->label('Cliente (Origen)')
                                ->content($this->sale->nombre_cliente . ' (' . ($this->sale->numero_documento ?? 'Sin Doc') . ')'),

                            DatePicker::make('fecha_emision')
                                ->label('Fecha de Emisión')
                                ->default(now())
                                ->required()
                                ->validationMessages(['required' => 'La fecha es obligatoria.']),

                            Select::make('tipo_nota')
                                ->label('Tipo de Nota')
                                ->options([
                                    '07' => 'Nota de Crédito',
                                    '08' => 'Nota de Débito',
                                ])
                                ->required()
                                ->validationMessages(['required' => 'Seleccione un tipo de nota.'])
                                ->live(),

                            Select::make('serie_id')
                                ->label('Serie de la Nota')
                                ->options(function (Get $get) {
                                    $tenantId = Filament::getTenant()->id;
                                    return DocumentSerie::where('restaurant_id', $tenantId)
                                        ->where('is_active', true)
                                        ->where('serie', 'LIKE', $this->sale->tipo_comprobante === 'Factura' ? 'F%' : 'B%')
                                        ->where('serie', 'LIKE', $get('tipo_nota') === '07' ? '_C%' : '_D%')
                                        ->pluck('serie', 'serie');
                                })
                                ->required()
                                ->validationMessages(['required' => 'La serie es obligatoria. Verifique sus series activas.']),

                            Select::make('cod_motivo')
                                ->label('Motivo SUNAT')
                                ->options(MotivoNotaCredito::opcionesParaSelect())
                                ->required()
                                ->validationMessages(['required' => 'Debe elegir un motivo oficial de SUNAT.'])
                                ->columnSpan(['default' => 1, 'md' => 2]),

                            Textarea::make('des_motivo')
                                ->label('Descripción')
                                ->required()
                                ->validationMessages(['required' => 'Agregue una breve descripción del motivo.'])
                                ->rows(1)
                                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),

                            Toggle::make('devolver_stock')
                                ->label('Devolver stock')
                                ->onColor('success')
                                ->visible(fn(Get $get) => $get('tipo_nota') === '07' && $get('has_stock_products'))
                                ->helperText('Al activar, los productos con control de stock regresarán al almacén.')
                                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),
                        ])
                    ]),

                // 🟢 2. PRODUCTOS Y VARIANTES (DISEÑO FLUIDO)
                Section::make('Productos')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->addActionLabel('Agregar producto')
                            ->schema([
                                Hidden::make('product_name'),
                                Hidden::make('promotion_id'),
                                Hidden::make('es_promocion'),

                                Grid::make(12)->schema([

                                    Select::make('product_id')
                                        ->label('Producto')
                                        ->options(function () {
                                            return Product::where('restaurant_id', Filament::getTenant()->id)
                                                ->where('status', 'activo')
                                                ->pluck('name', 'id');
                                        })
                                        // 🟢 Mostramos el nombre de la promo si es promo
                                        ->placeholder(fn(Get $get) => $get('es_promocion') ? $get('product_name') : 'Seleccione producto')
                                        ->disabled(fn(Get $get) => $get('es_promocion')) // 🟢 Bloqueado si es promo
                                        ->dehydrated()
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->required(fn(Get $get) => !$get('es_promocion')) // 🟢 Requerido solo si NO es promo
                                        ->validationMessages(['required' => 'Requerido.'])
                                        ->columnSpan(['default' => 12, 'md' => 6, 'lg' => 4])
                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                            if (!$state) {
                                                $set('variant_id', null);
                                                $set('product_name', null);
                                                $set('precio_unitario', '0.00');
                                                $this->recalculateLine($get, $set);
                                                return;
                                            }

                                            // Si el usuario cambia manualmente, ya no es promoción
                                            $set('es_promocion', false);
                                            $set('promotion_id', null);

                                            $product = Product::find($state);
                                            $variants = Variant::where('product_id', $state)->where('status', 'activo')->get();

                                            $set('product_name', $product->name);
                                            $precioBase = (float) ($product->price ?? 0);

                                            if ($variants->count() === 1) {
                                                $variant = $variants->first();
                                                $set('variant_id', $variant->id);

                                                $precioExtra = 0;
                                                $atributosPivot = DB::table('attribute_product')->where('product_id', $product->id)->get();

                                                foreach ($atributosPivot as $row) {
                                                    $valores = $row->values;
                                                    if (is_string($valores)) $valores = json_decode($valores, true);
                                                    if (is_string($valores)) $valores = json_decode($valores, true);

                                                    if (is_array($valores)) {
                                                        foreach ($valores as $val) {
                                                            $nombreAtr = trim($val['name'] ?? '');
                                                            if (!empty($nombreAtr) && stripos($variant->full_name, $nombreAtr) !== false) {
                                                                $precioExtra += (float) ($val['extra'] ?? 0);
                                                            }
                                                        }
                                                    }
                                                }
                                                $set('precio_unitario', number_format($precioBase + $precioExtra, 2, '.', ''));
                                            } else {
                                                $set('variant_id', null);
                                                $set('precio_unitario', number_format($precioBase, 2, '.', ''));
                                            }

                                            $this->recalculateLine($get, $set);
                                        }),

                                    Select::make('variant_id')
                                        ->label('Variante')
                                        ->disabled(fn(Get $get) => $get('es_promocion') || !$get('product_id')) // 🟢 Bloqueado si es promo o no hay prod
                                        ->dehydrated()
                                        ->options(function (Get $get) {
                                            $productId = $get('product_id');
                                            if (!$productId) return [];
                                            return Variant::where('product_id', $productId)
                                                ->where('status', 'activo')
                                                ->get()
                                                ->pluck('full_name', 'id');
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->columnSpan(['default' => 12, 'md' => 6, 'lg' => 3])
                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                            if ($state) {
                                                $variant = Variant::find($state);
                                                $product = Product::find($get('product_id'));

                                                $set('product_name', ($product->name ?? '') . ' - ' . $variant->full_name);

                                                $precioBase = (float) ($product->price ?? 0);
                                                $precioExtra = 0;

                                                $atributosPivot = DB::table('attribute_product')
                                                    ->where('product_id', $product->id)->get();

                                                foreach ($atributosPivot as $row) {
                                                    $valores = $row->values;
                                                    if (is_string($valores)) $valores = json_decode($valores, true);
                                                    if (is_string($valores)) $valores = json_decode($valores, true);

                                                    if (is_array($valores)) {
                                                        foreach ($valores as $val) {
                                                            $nombreAtr = trim($val['name'] ?? '');
                                                            $extra = (float) ($val['extra'] ?? 0);

                                                            if (!empty($nombreAtr) && stripos($variant->full_name, $nombreAtr) !== false) {
                                                                $precioExtra += $extra;
                                                            }
                                                        }
                                                    }
                                                }

                                                $set('precio_unitario', number_format($precioBase + $precioExtra, 2, '.', ''));
                                                $this->recalculateLine($get, $set);
                                            }
                                        }),

                                    TextInput::make('cantidad')
                                        ->label('Cant.')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(0.01)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(Get $get, Set $set) => $this->recalculateLine($get, $set))
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Falta cant.',
                                            'min' => 'Inválido'
                                        ])
                                        ->columnSpan(['default' => 4, 'md' => 4, 'lg' => 1]),

                                    TextInput::make('precio_unitario')
                                        ->label('P. Unit.')
                                        ->numeric()
                                        ->prefix('S/')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(Get $get, Set $set) => $this->recalculateLine($get, $set))
                                        ->required()
                                        ->validationMessages(['required' => 'Requerido.'])
                                        ->columnSpan(['default' => 4, 'md' => 4, 'lg' => 2]),

                                    TextInput::make('subtotal')
                                        ->label('Subtotal')
                                        ->numeric()
                                        ->prefix('S/')
                                        ->readOnly()
                                        ->columnSpan(['default' => 4, 'md' => 4, 'lg' => 2]),
                                ]),
                            ])
                            ->defaultItems(1)
                            ->live(),
                    ]),

                // 🟢 3. TOTALES GLOBALES
                Section::make()
                    ->schema([
                        Placeholder::make('totales')
                            ->hiddenLabel()
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $totalComprobante = 0;

                                foreach ($items as $item) {
                                    $totalComprobante += (float) ($item['subtotal'] ?? 0);
                                }

                                $divisor = get_tax_divisor(Filament::getTenant()->id);
                                $totalOpGravada = round($totalComprobante / $divisor, 2);
                                $totalIgv = round($totalComprobante - $totalOpGravada, 2);
                                $totalPagar = number_format($totalComprobante, 2);

                                return new HtmlString("
                                    <div style='text-align: right; font-size: 1.1rem;'>
                                        <p><b>Op. Gravada:</b> S/ " . number_format($totalOpGravada, 2) . "</p>
                                        <p><b>IGV:</b> S/ " . number_format($totalIgv, 2) . "</p>
                                        <p style='font-size: 1.5rem; font-weight: bold; margin-top: 10px; color: #10b981;'>
                                            TOTAL: S/ {$totalPagar}
                                        </p>
                                    </div>
                                ");
                            })
                    ])
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Emitir y Enviar a SUNAT')
                ->color('primary')
                ->requiresConfirmation()
                ->submit('emitirNota'),

            Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url(Comprobantes::getUrl()),
        ];
    }

    public function emitirNota()
    {
        $data = $this->form->getState();
        $tenant = Filament::getTenant();

        if (empty($data['items'])) {
            Notification::make()->title('Error')->body('Agrega al menos un producto para emitir la nota.')->danger()->send();
            return;
        }

        // --- A. CÁLCULOS MATEMÁTICOS GLOBALES ---
        $divisor = get_tax_divisor($tenant->id);
        $totalOpGravada = 0;
        $totalIgv = 0;

        foreach ($data['items'] as &$item) {
            $cantidad = (float) $item['cantidad'];
            $precio = (float) $item['precio_unitario'];
            $subtotalLlamado = round($precio * $cantidad, 2);
            $valorVentaLinea = round($subtotalLlamado / $divisor, 2);
            $igvLinea = round($subtotalLlamado - $valorVentaLinea, 2);

            $totalOpGravada += $valorVentaLinea;
            $totalIgv += $igvLinea;

            $item['precio_unitario'] = $precio;
            $item['cantidad'] = $cantidad;
        }
        $totalComprobante = round($totalOpGravada + $totalIgv, 2);

        // --- B. GENERACIÓN DE CORRELATIVO BASADO EN LA SERIE ELEGIDA ---
        $serieElegida = $data['serie_id'];
        $ultimoCorrelativo = CreditDebitNote::where('restaurant_id', $tenant->id)
            ->where('serie', $serieElegida)
            ->max('correlativo');
        $nuevoCorrelativo = $ultimoCorrelativo ? $ultimoCorrelativo + 1 : 1;

        // 🟢 PREPARACIÓN DE ÍTEMS (Mapeo de promociones)
        $itemsParaGuardar = collect($data['items'])->map(function ($item) {
            return [
                'product_id'      => $item['product_id'] ?? null,
                'variant_id'      => $item['variant_id'] ?? null,
                'promotion_id'    => $item['promotion_id'] ?? null,
                'product_name'    => $item['product_name'],
                'cantidad'        => (float) $item['cantidad'],
                'precio_unitario' => (float) $item['precio_unitario'],
                'subtotal'        => round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2),
            ];
        })->toArray();

        // --- C. GUARDADO EN BASE DE DATOS ---
        $nota = CreditDebitNote::create([
            'restaurant_id' => $tenant->id,
            'user_id'       => Auth::id(),
            'sale_id'       => $this->sale->id,
            'tipo_nota'     => $data['tipo_nota'],
            'serie'         => $serieElegida,
            'correlativo'   => $nuevoCorrelativo,
            'fecha_emision' => $data['fecha_emision'] ?? now(),
            'cod_motivo'    => $data['cod_motivo'],
            'des_motivo'    => $data['des_motivo'],
            'op_gravada'    => $totalOpGravada,
            'monto_igv'     => $totalIgv,
            'total'         => $totalComprobante,
            'details'       => $itemsParaGuardar, // 🟢 Guardamos los items mapeados
        ]);

        // 🟢 --- PROCESAMIENTO DE KARDEX Y STOCK (Vía Trait) --- 🟢
        $devolverStock = $data['devolver_stock'] ?? false;
        $tipoNota = $data['tipo_nota'];
        $comprobanteRef = "{$nota->serie}-{$nota->correlativo}";

        // 1. Si es Nota de Crédito y marcaron la casilla de Devolver
        if ($tipoNota === '07' && $devolverStock) {
            foreach ($data['items'] as $itemData) {
                $virtualItem = new \App\Models\SaleDetail($itemData); // Objeto virtual

                // Desglosar si es promoción para devolver los insumos correctos
                if (!empty($itemData['promotion_id'])) {
                    $promo = \App\Models\Promotion::with('promotionproducts')->find($itemData['promotion_id']);
                    if ($promo) {
                        foreach ($promo->promotionproducts as $subItem) {
                            $subVirtual = new \App\Models\SaleDetail([
                                'product_id' => $subItem->product_id,
                                'variant_id' => $subItem->variant_id,
                                'cantidad'   => $itemData['cantidad'] * $subItem->quantity,
                            ]);
                            $this->reverseItem($subVirtual, 'salida', $comprobanteRef, 'Nota Crédito (Reingreso)');
                        }
                    }
                } else {
                    // Si es producto normal
                    $this->reverseItem($virtualItem, 'salida', $comprobanteRef, 'Nota Crédito (Reingreso)');
                }
            }
        }
        // 2. Si es Nota de Débito (Nuevos ítems agregados)
        elseif ($tipoNota === '08') {
            foreach ($data['items'] as $itemData) {
                $virtualItem = new \App\Models\SaleDetail($itemData);
                $this->processItem($virtualItem, 'salida', $comprobanteRef, 'Nota Débito (Cargo Extra)');
            }
        }
        // 🟢 --- FIN DE PROCESAMIENTO DE STOCK --- 🟢

        // 🟢 --- PROCESAMIENTO DE CAJA (100% AUTOMÁTICO) --- 🟢
        $sesionCaja = \App\Models\SessionCashRegister::where('user_id', Auth::id())
            ->whereNull('closed_at')
            ->first();

        if ($sesionCaja) {
            // Buscar el movimiento original de la venta
            $movimientoOriginal = \App\Models\CashRegisterMovement::where('referencia_type', Sale::class)
                ->where('referencia_id', $this->sale->id)
                ->first();

            if ($movimientoOriginal) {
                $tipoMovimiento = $tipoNota === '07' ? 'egreso' : 'ingreso';
                $motivoTexto = $tipoNota === '07' ? 'Devolución NC: ' : 'Cobro Extra ND: ';

                $sesionCaja->cashRegisterMovements()->create([
                    'payment_method_id' => $movimientoOriginal->payment_method_id,
                    'usuario_id'        => Auth::id(),
                    'tipo'              => $tipoMovimiento,
                    'motivo'            => $motivoTexto . "{$nota->serie}-{$nota->correlativo} (Ref: {$this->sale->serie}-{$this->sale->correlativo})",
                    'monto'             => $totalComprobante, // Total de la nota
                    'referencia_type'   => CreditDebitNote::class,
                    'referencia_id'     => $nota->id,
                ]);
            } else {
                Notification::make()
                    ->title('Aviso de Caja')
                    ->body('La nota se emitió, pero no se afectó la caja porque la venta original no tiene un método de pago registrado.')
                    ->warning()
                    ->send();
            }
        } else {
            Notification::make()
                ->title('Aviso de Caja')
                ->body('La nota se emitió, pero el movimiento de dinero no se registró porque no tienes un turno de caja abierto.')
                ->warning()
                ->send();
        }
        // 🟢 --- FIN PROCESAMIENTO DE CAJA --- 🟢

        // --- D. ENVÍO A SUNAT ---
        $payloadBuilder = app(\App\Services\NotePayloadService::class);
        $sunatService = app(\App\Services\SunatGreenterApiService::class);

        $payload = $payloadBuilder->buildFromNote($nota, $tenant);
        $respuesta = $sunatService->sendNote($payload, $tenant->api_token);

        // --- E. PROCESAR RESPUESTA ---
        if (!$respuesta['success']) {
            $nota->update([
                'status_sunat' => 'error_api',
                'message'      => $respuesta['error_data']['error'] ?? $respuesta['message'] ?? 'Error de conexión.'
            ]);
            Notification::make()->title('Fallo en la API')->body('Se guardó localmente, pero falló el envío a SUNAT.')->danger()->send();
            return redirect()->to(Comprobantes::getUrl());
        }

        $apiData = $respuesta['data'];
        $apiSuccess = $apiData['sunatResponse']['success'] ?? false;

        $nombreBase = "{$tenant->ruc}-{$nota->tipo_nota}-{$nota->serie}-{$nota->correlativo}";
        $slug = $tenant->slug ?? 'default';
        $fecha = \Carbon\Carbon::parse($nota->fecha_emision)->format('Y-m-d');

        $pathXml = null;
        if (!empty($apiData['xml'])) {
            $pathXml = "tenants/{$slug}/notas/xml/{$fecha}/{$nombreBase}.xml";
            Storage::disk('public')->put($pathXml, base64_decode($apiData['xml']));
        }

        $updateData = [
            'hash'         => $apiData['hash'] ?? null,
            'path_xml'     => $pathXml,
            'qr_data'      => $apiData['qr_data'] ?? null,
            'total_letras' => $apiData['total_letras'] ?? null,
        ];

        if ($apiSuccess) {
            $pathCdrZip = null;
            if (!empty($apiData['sunatResponse']['cdrZip'])) {
                $pathCdrZip = "tenants/{$slug}/notas/cdr/{$fecha}/R-{$nombreBase}.zip";
                Storage::disk('public')->put($pathCdrZip, base64_decode($apiData['sunatResponse']['cdrZip']));
            }

            $updateData['status_sunat'] = 'aceptado';
            $updateData['success']      = true;
            $updateData['path_cdrZip']  = $pathCdrZip;
            $updateData['code']         = $apiData['sunatResponse']['cdrResponse']['code'] ?? null;
            $updateData['description']  = $apiData['sunatResponse']['cdrResponse']['description'] ?? null;

            Notification::make()->title('¡Nota Electrónica Aceptada!')->success()->send();
        } else {
            $updateData['status_sunat'] = 'rechazado';
            $updateData['success']      = false;
            $updateData['code']         = $apiData['sunatResponse']['error']['code'] ?? null;
            $updateData['message']      = $apiData['sunatResponse']['error']['message'] ?? null;
            $updateData['description']  = "Error SUNAT: " . ($apiData['sunatResponse']['error']['message'] ?? '');

            Notification::make()->title('Nota Rechazada por SUNAT')->body($updateData['message'])->danger()->send();
        }

        $nota->update($updateData);

        return redirect()->to(Comprobantes::getUrl());
    }
}
