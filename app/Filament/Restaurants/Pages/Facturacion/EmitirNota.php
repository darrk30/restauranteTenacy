<?php

namespace App\Filament\Restaurants\Pages\Facturacion;

use App\Enums\MotivosSUNAT;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Variant;
use App\Models\DocumentSerie;
use App\Models\CreditDebitNote;
use App\Models\SaleDetail;
use App\Models\Promotion;
use App\Models\SessionCashRegister;
use App\Models\CashRegisterMovement;
use App\Traits\ManjoStockProductos;
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
use Carbon\Carbon;

class EmitirNota extends Page
{
    use ManjoStockProductos;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.facturacion.emitir-nota';
    protected static ?string $title = 'Emitir Nota Electrónica';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'emitir-nota/{record}';

    public ?array $data = [];
    public Sale $sale;

    // Nueva variable para guardar el mensaje si hay notas previas
    public ?string $notasPrevias = null;

    public static function canAccess(): bool
    {
        if (!Filament::getTenant()) return false;
        $user = auth()->user();
        if ($user->hasRole('Super Admin')) return false;
        return $user->can('emitir_nota_rest');
    }

    public function mount($record): void
    {
        // Cargamos también los productos de las promociones para el Kardex
        $this->sale = Sale::with(['client', 'details.product', 'details.variant', 'details.promotion.promotionproducts.product'])
            ->findOrFail($record);

        $notasExistentes = CreditDebitNote::where('sale_id', $this->sale->id)
            ->whereIn('status_sunat', ['registrado', 'aceptado'])
            ->get();

        if ($notasExistentes->isNotEmpty()) {
            $this->notasPrevias = $notasExistentes->map(fn($nota) => "{$nota->serie}-{$nota->correlativo}")->implode(', ');
        }

        $hasStockProducts = false;

        // --- LÓGICA DE AJUSTE PROPORCIONAL ---
        $montoDescuentoGlobal = (float) ($this->sale->monto_descuento ?? 0);
        $totalBrutoOriginal = $this->sale->details->sum(fn($item) => (float)$item->cantidad * (float)$item->precio_unitario);
        $factorAjuste = ($montoDescuentoGlobal > 0 && $totalBrutoOriginal > 0)
            ? ($totalBrutoOriginal - $montoDescuentoGlobal) / $totalBrutoOriginal
            : 1;

        $items = $this->sale->details->map(function ($item) use (&$hasStockProducts, $factorAjuste) {
            $cantidad = (float) $item->cantidad;
            $precioAjustado = (float) $item->precio_unitario * $factorAjuste;
            $isPromo = !is_null($item->promotion_id);

            // Verificamos si hay productos con stock (normales o dentro de promo)
            if ($item->product?->control_stock) $hasStockProducts = true;
            if ($isPromo && $item->promotion) {
                foreach ($item->promotion->promotionproducts as $pp) {
                    if ($pp->product?->control_stock) {
                        $hasStockProducts = true;
                        break;
                    }
                }
            }

            return [
                'id'              => $item->id,
                'product_id'      => $item->product_id,
                'variant_id'      => $item->variant_id,
                'promotion_id'    => $item->promotion_id,
                'product_name'    => $isPromo ? ($item->promotion->name ?? 'Promoción') : ($item->product->name ?? $item->descripcion),
                'cantidad'        => number_format($cantidad, 2, '.', ''),
                'precio_unitario' => number_format($precioAjustado, 2, '.', ''),
                'costo_unitario'  => number_format($item->costo_unitario ?? 0, 2, '.', ''),
                'subtotal'        => number_format($cantidad * $precioAjustado, 2, '.', ''),
                'es_promocion'    => $isPromo,
            ];
        })->toArray();

        $this->form->fill([
            'fecha_emision'    => now()->format('Y-m-d'),
            'tipo_nota'        => '07',
            'items'            => $items,
            'has_stock_products' => $hasStockProducts,
            'devolver_stock'   => false,
        ]);
    }

    public static function updateSubtotal(Get $get, Set $set): void
    {
        $cantidad = (float) ($get('cantidad') ?? 0);
        $precio = (float) ($get('precio_unitario') ?? 0);
        $set('subtotal', number_format($cantidad * $precio, 2, '.', ''));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Placeholder::make('alerta_notas')
                ->hiddenLabel()
                ->visible(fn() => !empty($this->notasPrevias))
                ->content(fn() => new HtmlString('
                    <div style="display: flex; gap: 0.75rem; padding: 1rem; border-radius: 0.75rem; background-color: #fef08a; color: #854d0e; border: 1px solid #facc15;">
                        <svg style="width: 1.5rem; height: 1.5rem; flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 style="font-weight: 700; margin-bottom: 0.25rem;">Atención: Notas previas detectadas</h3>
                            <p style="font-size: 0.875rem; margin: 0;">Ya hay notas generadas para este comprobante: <strong>' . $this->notasPrevias . '</strong>. Verifique bien los montos y el stock a devolver antes de emitir una nueva.</p>
                        </div>
                    </div>
                '))
                ->columnSpanFull(),
            Section::make('Datos de la Nota')
                ->description('Referencia: ' . $this->sale->tipo_comprobante . ' ' . $this->sale->serie . '-' . $this->sale->correlativo)
                ->schema([
                    // Grid responsivo: 1 col en móvil, 2 en tablet, 4 en escritorio
                    Grid::make([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])->schema([
                        Hidden::make('has_stock_products'),

                        Placeholder::make('cliente')
                            ->label('Cliente')
                            ->content($this->sale->nombre_cliente)
                            ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 1]), // Ocupa más espacio en tablets

                        DatePicker::make('fecha_emision')
                            ->label('Fecha Emisión')
                            ->required()
                            ->native(false), // Mejora la UI en móviles
                        Select::make('tipo_nota')
                            ->label('Tipo Nota')
                            ->options(['07' => 'N. Crédito', '08' => 'N. Débito'])
                            ->required()
                            ->live(), // 🟢 Esto le avisa a Filament que debe recargar los campos que dependan de este

                        Select::make('serie_id')
                            ->label('Serie')
                            ->options(fn(Get $get) => DocumentSerie::where('restaurant_id', Filament::getTenant()->id)
                                ->where('is_active', true)
                                ->where('serie', 'LIKE', ($this->sale->tipo_comprobante === 'Factura' ? 'F' : 'B') . ($get('tipo_nota') === '07' ? 'C%' : 'D%'))
                                ->pluck('serie', 'serie'))
                            ->required(),

                        // 👇 AQUÍ ESTÁ EL CAMBIO PRINCIPAL 👇
                        Select::make('cod_motivo')
                            ->label('Motivo SUNAT')
                            ->options(function (Get $get) {
                                // Si el usuario eligió "N. Débito" (08), cargamos el catálogo 10
                                if ($get('tipo_nota') === '08') {
                                    return MotivosSUNAT::opcionesDebito();
                                }

                                // Por defecto, o si eligió "N. Crédito" (07), cargamos el catálogo 09
                                return MotivosSUNAT::opcionesCredito();
                            })
                            ->required()
                            ->live() // Opcional, pero recomendado si otro campo dependiera del motivo
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        Textarea::make('des_motivo')
                            ->label('Descripción')
                            ->required()
                            ->rows(1)
                            ->columnSpan(['default' => 1, 'sm' => 2]),

                        Toggle::make('devolver_stock')
                            ->label('Reingresar Stock')
                            ->inline(false) // Mejor alineación en móviles
                            ->visible(fn(Get $get) => $get('tipo_nota') === '07' && $get('has_stock_products'))
                            ->columnSpan(['default' => 1, 'sm' => 2]),
                    ])
                ]),

            Section::make('Productos')
                ->schema([
                    Repeater::make('items')
                        ->schema([
                            // Grid interno de 12 columnas para control fino
                            Grid::make(12)->schema([
                                TextInput::make('product_name')
                                    ->label('Promoción / Producto')
                                    ->readOnly()
                                    ->visible(fn(Get $get) => $get('es_promocion'))
                                    ->columnSpan(['default' => 12, 'lg' => 4]),

                                Select::make('product_id')
                                    ->label('Producto')
                                    ->options(Product::where('restaurant_id', Filament::getTenant()->id)->pluck('name', 'id'))
                                    ->hidden(fn(Get $get) => $get('es_promocion')) // Ocultar si es promo
                                    ->live()
                                    ->searchable()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (!$state) return;
                                        $p = Product::find($state);
                                        $set('product_name', $p->name);
                                        $set('precio_unitario', $p->price);
                                        static::updateSubtotal($get, $set);
                                    })
                                    ->columnSpan(['default' => 12, 'lg' => 4]),

                                Select::make('variant_id')
                                    ->label('Variante')
                                    ->searchable()
                                    ->options(fn(Get $get) => Variant::where('product_id', $get('product_id'))->get()->pluck('full_name', 'id'))
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (!$state) return;
                                        $v = Variant::find($state);
                                        $set('precio_unitario', $v->price);
                                        static::updateSubtotal($get, $set);
                                    })
                                    ->hidden(fn(Get $get) => $get('es_promocion'))
                                    ->disabled(fn(Get $get) => !$get('product_id'))
                                    ->columnSpan(['default' => 12, 'lg' => 3]),

                                TextInput::make('cantidad')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::updateSubtotal($get, $set))
                                    ->columnSpan(['default' => 6, 'lg' => 1]), // Media fila en móvil

                                TextInput::make('precio_unitario')
                                    ->label('P. Unit')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::updateSubtotal($get, $set))
                                    ->columnSpan(['default' => 6, 'lg' => 2]),

                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('S/')
                                    ->readOnly()
                                    ->columnSpan(['default' => 12, 'lg' => 2]),

                                Hidden::make('product_name'),
                                Hidden::make('promotion_id'),
                                Hidden::make('es_promocion'),
                            ])
                        ])
                        ->addActionLabel('Agregar otro producto')
                        ->live()
                ]),

            Section::make()->schema([
                Placeholder::make('resumen_final')
                    ->content(function (Get $get) {
                        $total = collect($get('items'))->sum(fn($i) => (float)($i['subtotal'] ?? 0));
                        $divisor = get_tax_divisor(Filament::getTenant()->id);
                        $gravada = round($total / $divisor, 2);
                        $igv = round($total - $gravada, 2);

                        return new HtmlString("
                        <div class='flex flex-col items-end gap-1 border-t pt-4'>
                            <div class='text-sm text-gray-500'>Gravada: S/ " . number_format($gravada, 2) . "</div>
                            <div class='text-sm text-gray-500'>IGV: S/ " . number_format($igv, 2) . "</div>
                            <div class='text-xl md:text-2xl font-bold text-primary-600'>TOTAL NOTA: S/ " . number_format($total, 2) . "</div>
                        </div>
                    ");
                    })
            ])
        ])->statePath('data');
    }

    public function emitirNota()
    {
        $data = $this->form->getState();
        $tenant = Filament::getTenant();

        if (empty($data['items'])) {
            Notification::make()->title('Error')->body('Agregue al menos un ítem.')->danger()->send();
            return;
        }

        // ==========================================
        // 1. GUARDADO LOCAL DEL DOCUMENTO
        // ==========================================
        DB::beginTransaction();
        try {
            $total = collect($data['items'])->sum(fn($i) => (float)$i['subtotal']);
            $divisor = get_tax_divisor($tenant->id);
            $gravada = round($total / $divisor, 2);
            $igv = round($total - $gravada, 2);

            $ultimo = CreditDebitNote::where('restaurant_id', $tenant->id)->where('serie', $data['serie_id'])->max('correlativo');
            $nuevoCorrelativo = ($ultimo ?? 0) + 1;

            $nota = CreditDebitNote::create([
                'restaurant_id' => $tenant->id,
                'user_id'       => Auth::id(),
                'sale_id'       => $this->sale->id,
                'tipo_nota'     => $data['tipo_nota'],
                'serie'         => $data['serie_id'],
                'correlativo'   => $nuevoCorrelativo,
                'fecha_emision' => $data['fecha_emision'],
                'cod_motivo'    => $data['cod_motivo'],
                'des_motivo'    => $data['des_motivo'],
                'op_gravada'    => $gravada,
                'monto_igv'     => $igv,
                'total'         => $total,
                'details'       => $data['items'],
                'status_sunat'  => 'registrado',
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error Crítico')->body($e->getMessage())->danger()->send();
            return;
        }

        // ==========================================
        // 2. ENVÍO A SUNAT
        // ==========================================
        try {
            $payload = app(\App\Services\NotePayloadService::class)->buildFromNote($nota, $tenant);
            $respuesta = app(\App\Services\SunatGreenterApiService::class)->sendNote($payload, $tenant->api_token);

            if (!$respuesta['success']) {
                $nota->update([
                    'status_sunat' => 'error_api',
                    'message'      => substr($respuesta['message'] ?? 'Error de conexión', 0, 250)
                ]);
                Notification::make()->title('Fallo envío a API')->danger()->send();
                return redirect()->to('/pdv/comprobantes');
            }

            // 3. PROCESAR RESPUESTA Y ARCHIVOS
            $apiData = $respuesta['data'];
            $sunatResponse = $apiData['sunatResponse'] ?? [];
            $apiSuccess = $sunatResponse['success'] ?? false;
            $nombreBase = "{$tenant->ruc}-{$nota->tipo_nota}-{$nota->serie}-{$nota->correlativo}";
            $slug = $tenant->slug ?? 'default';
            $fechaFolder = \Carbon\Carbon::parse($nota->fecha_emision)->format('Y-m-d');

            $pathXml = $nota->path_xml;
            if (!empty($apiData['xml'])) {
                $pathXml = "tenants/{$slug}/notas/xml/{$fechaFolder}/{$nombreBase}.xml";
                Storage::disk('public')->put($pathXml, base64_decode($apiData['xml']));
            }

            $updateData = [
                'hash'         => $apiData['hash'] ?? null,
                'path_xml'     => $pathXml,
                'qr_data'      => $apiData['qr_data'] ?? null,
                'total_letras' => $apiData['total_letras'] ?? null,
            ];

            if ($apiSuccess) {
                DB::beginTransaction();
                try {
                    if ($data['tipo_nota'] === '07') {
                        $this->sale->update(['status' => 'anulada_por_nota']);
                        if (($data['devolver_stock'] ?? false)) {
                            foreach ($data['items'] as $item) {
                                $saleDetail = SaleDetail::find($item['id']);
                                if (!$saleDetail) continue;
                                $saleDetail->cantidad = $item['cantidad'];
                                $this->reverseItem($saleDetail, 'salida', "{$nota->serie}-{$nuevoCorrelativo}", 'NC Reingreso');
                            }
                        }
                    } elseif ($data['tipo_nota'] === '08') {
                        foreach ($data['items'] as $item) {
                            if (empty($item['id'])) {
                                $newItem = new SaleDetail([
                                    'product_id' => $item['product_id'],
                                    'variant_id' => $item['variant_id'],
                                    'cantidad'   => $item['cantidad'],
                                ]);
                                $this->processItem($newItem, 'salida', "{$nota->serie}-{$nuevoCorrelativo}", 'ND Salida Stock');
                            }
                        }
                    }

                    $sesion = SessionCashRegister::where('user_id', Auth::id())->whereNull('closed_at')->first();
                    if ($sesion) {
                        $origMov = CashRegisterMovement::where('referencia_type', \App\Models\Sale::class)
                            ->where('referencia_id', $this->sale->id)->first();

                        $sesion->cashRegisterMovements()->create([
                            'payment_method_id' => $origMov->payment_method_id ?? 1,
                            'usuario_id'        => Auth::id(),
                            'tipo'              => $data['tipo_nota'] === '07' ? 'egreso' : 'ingreso',
                            'motivo'            => "Nota {$nota->serie}-{$nuevoCorrelativo} (Ref: {$this->sale->serie}-{$this->sale->correlativo})",
                            'monto'             => $total,
                            'referencia_type'   => CreditDebitNote::class,
                            'referencia_id'     => $nota->id,
                        ]);
                    }

                    DB::commit();
                } catch (\Exception $eKardex) {
                    DB::rollBack();
                    Notification::make()->title('Error en Kardex/Caja')->body($eKardex->getMessage())->danger()->send();
                }

                $pathCdrZip = null;
                if (!empty($sunatResponse['cdrZip'])) {
                    $pathCdrZip = "tenants/{$slug}/notas/cdr/{$fechaFolder}/R-{$nombreBase}.zip";
                    Storage::disk('public')->put($pathCdrZip, base64_decode($sunatResponse['cdrZip']));
                }

                $cdrResponse = $sunatResponse['cdrResponse'] ?? [];
                $updateData['status_sunat'] = 'aceptado';
                $updateData['success']      = true;
                $updateData['path_cdrZip']  = $pathCdrZip;
                $updateData['code']         = $cdrResponse['code'] ?? null;
                $updateData['description']  = $cdrResponse['description'] ?? 'Aceptado por SUNAT';
                $updateData['message']      = $updateData['description'];

                Notification::make()->title('¡Nota Aceptada!')->success()->send();
            } else {
                $errorSunat = $sunatResponse['error'] ?? [];
                $updateData['status_sunat'] = 'rechazado';
                $updateData['success']      = false;
                $updateData['message']      = $errorSunat['message'] ?? 'Error desconocido';
                $updateData['description']  = "Error SUNAT: " . ($updateData['message']);

                Notification::make()->title('Nota Rechazada')->body($updateData['message'])->danger()->send();
            }

            $nota->update($updateData);
        } catch (\Exception $e) {
            Notification::make()->title('Error Inesperado')->body($e->getMessage())->danger()->send();
        }

        return redirect()->to('/pdv/comprobantes');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Emitir Nota')->color('primary')->submit('emitirNota'),
        ];
    }
}
