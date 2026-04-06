<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Order;
use App\Models\Client;
use App\Models\Payment;
use App\Models\DocumentSerie;
use App\Models\PaymentMethod;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Form;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\DB;
use App\Filament\Restaurants\Resources\ClientResource;
use App\Models\CashRegisterMovement;
use App\Models\Sale;
use App\Models\SessionCashRegister;
use App\Models\Table;
use App\Models\WarehouseStock;
use App\Services\InventoryService;
use App\Services\SunatGreenterApiService;
use App\Traits\ManjoStockProductos;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class PagarOrden extends Page implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;
    use ManjoStockProductos;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static string $view = 'filament.pdv.pagar-orden';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'pedidos/{record}/pagar';

    public Order $order;
    /** @var \Illuminate\Database\Eloquent\Collection */
    public $items = [];

    // UI & Pagos
    public $metodos_pago = [];
    public $pagos_agregados = [];

    // Variables Sincronizadas
    public $subtotal_base = 0;
    public $monto_descuento = 0;
    public $total_final = 0;
    public $op_gravada = 0;
    public $monto_igv = 0;
    public $tipo_comprobante = 'Nota de venta'; // ✅ CAMBIO: Defecto es Nota de Venta
    public $serie_id;
    public $notas_pago = '';
    public bool $cliente_tiene_ruc = false;
    public $referencia_pago = '';

    /** @var mixed */
    public $series;

    // Clientes
    public $search_cliente = '';
    public $cliente_seleccionado;
    public $resultados_clientes = [];
    public $tenantSlug;

    public $ventaExitosaId = null; // ID de la venta recién creada
    public $mostrarPantallaExito = false;
    public $puedeImprimirComprobante = false;
    public $canal_orden;

    public function mount($record)
    {
        $this->tenantSlug = Filament::getTenant()->slug;
        $this->order = Order::with([
            'details' => function ($query) {
                $query->where('status', '!=', \App\Enums\StatusPedido::Cancelado);
            },
            'details.product.unit',
            'details.variant',
            'details.promotion.promotionproducts.product.unit',
            'details.promotion.promotionproducts.variant'
        ])->findOrFail($record);
        $this->canal_orden = $this->order->canal;
        $this->items = $this->order->details;

        $this->cargarSeries();
        $this->serie_id = $this->series->firstWhere('type_documento', $this->tipo_comprobante)?->id;
        $this->metodos_pago = PaymentMethod::where('status', true)
            ->get(['id', 'name', 'image_path', 'requiere_referencia']);

        $this->order->client_id ? $this->seleccionarCliente($this->order->client_id) : $this->setClienteVarios();

        $this->subtotal_base = $this->items->sum('subTotal');
        $this->calculateTotalServer();
    }

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
            return $user->hasPermissionTo('cobrar_pedido_rest');
        } catch (\Exception $e) {
            return false;
        }
    }

    public function cargarSeries()
    {
        $this->series = DocumentSerie::where('is_active', true)
            ->whereIn('type_documento', ['Boleta', 'Factura', 'Nota de venta'])
            ->get();
    }

    public function obtenerSerieActual($tipo)
    {
        return $this->series->firstWhere('type_documento', $tipo)->serie ?? '---';
    }

    public function setClienteVarios()
    {
        $cliente = Client::firstOrCreate(
            [
                'numero' => '99999999',
                'restaurant_id' => filament()->getTenant()->id
            ],
            [
                'nombres' => 'CLIENTES',
                'apellidos' => 'VARIOS',
                'status' => 'Activo',
            ]
        );

        $this->cliente_seleccionado = $cliente;
        $this->cliente_tiene_ruc = false;
    }


    public function calculateTotalServer()
    {
        $this->total_final = max(0, $this->subtotal_base - $this->monto_descuento);
        $this->op_gravada = round($this->total_final / 1.18, 2);
        $this->monto_igv = round($this->total_final - $this->op_gravada, 2);
    }

    public function updatedTipoComprobante($value)
    {
        $serie = $this->series->firstWhere('type_documento', $value);
        $this->serie_id = $serie?->id;
        // dd($value);
        // 1. Si es factura, validar RUC
        if ($value === 'Factura') {
            if (!$this->cliente_seleccionado || strlen($this->cliente_seleccionado->numero) !== 11) {
                $this->removerCliente(); // Esto pone cliente_seleccionado en null
                $this->cliente_tiene_ruc = false;
            }
        }
        // 2. Si es Boleta o Nota de Venta y no hay nadie, poner Varios
        else {
            if (!$this->cliente_seleccionado) {
                $this->setClienteVarios();
            }
        }
    }


    public function updatedSearchCliente()
    {
        if (strlen($this->search_cliente) < 2) {
            $this->resultados_clientes = [];
            return;
        }

        $query = Client::query()
            ->where('restaurant_id', filament()->getTenant()->id);

        if (ctype_digit($this->search_cliente)) {
            // 🔥 DNI o RUC → índice directo
            $query->where('numero', 'like', $this->search_cliente . '%');
        } else {
            // Texto → solo nombres/razón social
            $query->where(function ($q) {
                $q->where('nombres', 'like', '%' . $this->search_cliente . '%')
                    ->orWhere('razon_social', 'like', '%' . $this->search_cliente . '%');
            });
        }

        $this->resultados_clientes = $query
            ->limit(5)
            ->get();
    }


    public function seleccionarCliente($clienteId)
    {
        $cliente = Client::find($clienteId);
        if (!$cliente) return;

        $esRuc = strlen($cliente->numero) === 11;

        // Si el usuario ya marcó "Factura" pero elige un cliente con DNI
        if ($this->tipo_comprobante === 'Factura' && !$esRuc) {
            Notification::make()
                ->title('Cliente no válido para Factura')
                ->body('El cliente seleccionado debe tener RUC.')
                ->danger()
                ->send();
            return; // No lo selecciona
        }

        $this->cliente_seleccionado = $cliente;
        $this->cliente_tiene_ruc = $esRuc;

        // Auto-cambio a factura si seleccionas un RUC (opcional pero recomendado)
        if ($esRuc) {
            $this->tipo_comprobante = 'Factura';
        }

        $this->search_cliente = '';
        $this->resultados_clientes = [];
    }


    public function removerCliente()
    {
        $this->cliente_seleccionado = null;
        $this->cliente_tiene_ruc = false;
        $this->search_cliente = '';
        $this->resultados_clientes = [];

        // Si no estamos en factura, siempre debe haber un cliente (Varios por defecto)
        // if ($this->tipo_comprobante !== 'Factura') {
        //     $this->setClienteVarios();
        // }
    }

    public function crearClienteAction(): Action
    {
        return Action::make('crearCliente')
            ->label('Nuevo Cliente')
            ->modalWidth('2xl')
            ->model(Client::class)
            ->form(fn(Form $form) => ClientResource::form($form))
            ->action(function (array $data) {
                $cliente = Client::create($data);
                $this->seleccionarCliente($cliente->id);
                Notification::make()->title('Cliente registrado')->success()->send();
            });
    }

    // Modificamos para recibir el modelo ya bloqueado
    private function obtenerCorrelativoFinal(DocumentSerie $serieConfig)
    {
        // 1. Tomamos el número actual de la configuración y le sumamos 1
        $nuevoNumero = $serieConfig->current_number + 1;

        // 2. Actualizamos la serie inmediatamente (Esto está protegido por tu lockForUpdate)
        $serieConfig->update([
            'current_number' => $nuevoNumero
        ]);

        // 3. Retornamos formateado a 8 dígitos (ej: 00000015)
        return str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);
    }

    public function procesarPagoFinal()
    {
        // 1. VALIDACIONES PREVIAS
        $this->calculateTotalServer();
        $totalPagado = collect($this->pagos_agregados)->sum('amount');
        $tenant = Filament::getTenant();
        $tenantId = $tenant->id;
        $userId = Auth::id();

        // Obtenemos la configuración
        $config = $tenant->cached_config ?? $tenant;

        $sesionCaja = SessionCashRegister::where('restaurant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$sesionCaja) {
            Notification::make()->title('Caja Cerrada')->danger()->send();
            return;
        }

        if ($totalPagado < ($this->total_final - 0.01)) {
            Notification::make()->title('Pago incompleto')->danger()->send();
            return;
        }

        DB::beginTransaction();
        try {
            $order = Order::where('id', $this->order->id)->lockForUpdate()->first();

            if ($order->status === 'pagado') {
                throw new \Exception("Orden ya procesada.");
            }

            $serieConfig = DocumentSerie::where('id', $this->serie_id)->lockForUpdate()->first();

            if (!$serieConfig) {
                throw new \Exception("Serie no válida.");
            }

            $correlativo = $this->obtenerCorrelativoFinal($serieConfig);
            $serie = $serieConfig->serie;

            $nombreFinalCliente = $this->cliente_seleccionado
                ? ($this->cliente_seleccionado->razon_social ?? ($this->cliente_seleccionado->nombres . ' ' . $this->cliente_seleccionado->apellidos))
                : 'CLIENTES VARIOS';

            // =========================
            // 💾 1. GUARDAR VENTA PRIMERO EN BD
            // =========================
            $sale = Sale::create([
                'restaurant_id'    => $tenantId,
                'order_id'         => $order->id,
                'client_id'        => $this->cliente_seleccionado?->id,
                'user_id'          => $userId,
                'user_actualiza_id' => null,
                'nombre_cliente'   => $nombreFinalCliente,
                'tipo_documento'   => $this->cliente_seleccionado?->tipo_documento ?? 'DNI',
                'numero_documento' => $this->cliente_seleccionado?->numero ?? '99999999',
                'tipo_comprobante' => $this->tipo_comprobante,
                'serie'            => $serie,
                'correlativo'      => $correlativo,
                'monto_descuento'  => $this->monto_descuento,
                'op_gravada'       => $this->op_gravada,
                'monto_igv'        => $this->monto_igv,
                'total'            => $this->total_final,
                'costo_total'      => 0, // Se actualiza más abajo
                'status'           => 'completado',
                'canal'            => $order->canal,
                'delivery_id'      => $order->delivery_id,
                'nombre_delivery'  => $order->nombre_delivery,
                'fecha_emision'    => now(),
                // 🔥 Nace siempre como registrado si es comprobante electrónico
                'status_sunat'     => in_array($this->tipo_comprobante, ['Boleta', 'Factura']) ? 'registrado' : 'no_aplica',
            ]);

            $detallesParaKardex = collect();
            $costoTotalVenta = 0;

            // =========================
            // 📦 2. PROCESAMIENTO DE ITEMS
            // =========================
            foreach ($this->items as $item) {
                if ($item->status === \App\Enums\StatusPedido::Cancelado) {
                    continue;
                }
                $esPromocion = ($item->item_type === 'Promocion');

                $cantidad = $item->cantidad;
                $precioUnitario = (float) $item->price;
                $subtotal = round($precioUnitario * $cantidad, 2);
                $valorTotal = round($subtotal / 1.18, 2);
                $valorUnitario = ($cantidad > 0) ? ($valorTotal / $cantidad) : 0;

                $costoUnitario = 0;
                if (!$esPromocion && $item->variant) {
                    $costoUnitario = (float) ($item->variant->costo ?? 0);
                }
                $costoTotal = round($costoUnitario * $cantidad, 2);
                $costoTotalVenta += $costoTotal;

                $saleDetail = new \App\Models\SaleDetail([
                    'sale_id'         => $sale->id,
                    'product_id'      => $esPromocion ? null : $item->product_id,
                    'variant_id'      => $esPromocion ? null : $item->variant_id,
                    'promotion_id'    => $esPromocion ? $item->promotion_id : null,
                    'product_name'    => $item->product_name ?? 'Producto',
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'valor_unitario'  => $valorUnitario,
                    'subtotal'        => $subtotal,
                    'valor_total'     => $valorTotal,
                    'costo_unitario'  => $costoUnitario,
                    'costo_total'     => $costoTotal,
                ]);

                $saleDetail->save();

                if (!$esPromocion) {
                    $saleDetail->load(['product', 'variant', 'product.unit']);
                } else {
                    $saleDetail->load(['promotion.promotionproducts.product', 'promotion.promotionproducts.variant']);
                }

                $detallesParaKardex->push($saleDetail);
            }

            $sale->update(['costo_total' => $costoTotalVenta]);

            // =========================
            // 📊 3. MOVIMIENTO DE STOCK (Kardex)
            // =========================
            if ($detallesParaKardex->isNotEmpty()) {
                $this->applyVentaMasiva(
                    $detallesParaKardex,
                    'salida',
                    "{$sale->serie}-{$sale->correlativo}",
                    'Venta'
                );
            }

            // =========================
            // 💵 4. REGISTRO CAJA
            // =========================
            $movimientosCaja = collect($this->pagos_agregados)->map(function ($pago) use ($sale, $userId) {
                $referenciaStr = !empty($this->referencia_pago) ? " | Ref: " . $this->referencia_pago : "";
                return [
                    'payment_method_id' => $pago['id'],
                    'usuario_id'        => $userId,
                    'tipo'              => 'Ingreso',
                    'motivo'            => "Venta: {$sale->serie}-{$sale->correlativo}" . $referenciaStr,
                    'monto'             => $pago['amount'],
                    'referencia_type'   => Sale::class,
                    'referencia_id'     => $sale->id,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            })->toArray();

            $sesionCaja->cashRegisterMovements()->createMany($movimientosCaja);
            $this->referencia_pago = '';

            // =========================
            // 🍽️ 5. ACTUALIZACIÓN ESTADOS MESA/ORDEN
            // =========================
            $order->update(['status' => 'pagado']);

            if ($order->table_id) {
                Table::where('id', $order->table_id)->update([
                    'estado_mesa' => 'libre',
                    'order_id'    => null,
                    'asientos'    => 0
                ]);
            }

            DB::commit(); // 🔥 VENTA ASEGURADA AL 100% AQUÍ. Ya no hay marcha atrás con la base de datos.

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error interno')->body($e->getMessage())->danger()->send();
            return;
        }

        // ==========================================
        // 🚀 6. COMUNICACIÓN CON SUNAT (POST-GUARDADO)
        // ==========================================
        $statusSunatMensaje = $sale->status_sunat;

        if (in_array($this->tipo_comprobante, ['Boleta', 'Factura'])) {
            $apiKey = $tenant->api_token;

            if (empty($apiKey)) {
                $sale->update(['status_sunat' => 'error_api', 'message' => "El restaurante no tiene API Key configurada."]);
                $statusSunatMensaje = 'error_api';
            } else {
                try {
                    // 🟢 USAMOS EL SERVICIO (El mismo que usas en Filament)
                    $payloadBuilder = app(\App\Services\InvoicePayloadService::class);
                    $payload = $payloadBuilder->buildFromSale($sale, $tenant);

                    $sunatService = app(\App\Services\SunatGreenterApiService::class);
                    $respuesta = $sunatService->sendInvoice($payload, $apiKey);

                    if (!$respuesta['success']) {
                        // Falló la conexión con la API
                        $sale->update([
                            'status_sunat' => 'error_api',
                            'message'      => $respuesta['error_data']['error'] ?? $respuesta['message'] ?? 'Error de conexión con la API interna.'
                        ]);
                        $statusSunatMensaje = 'error_api';
                    } else {
                        $data = $respuesta['data'];
                        $apiSuccess = $data['sunatResponse']['success'] ?? false;

                        // Guardar XML si existe
                        $xmlBase64 = $data['xml'] ?? null;
                        $pathXml = null;
                        if ($xmlBase64) {
                            $slug  = $tenant->slug ?? 'default';
                            $fecha = now()->format('Y-m-d');
                            $folder = "tenants/{$slug}/comprobantes/xml/{$fecha}";
                            $nombreBase = "{$sale->serie}-{$sale->correlativo}";
                            $pathXml = "{$folder}/{$nombreBase}.xml";
                            Storage::disk('public')->put($pathXml, base64_decode($xmlBase64));
                        }

                        if ($apiSuccess) {
                            // Guardar CDR ZIP
                            $cdrBase64 = $data['sunatResponse']['cdrZip'] ?? null;
                            $pathCdrZip = null;
                            if ($cdrBase64) {
                                $slug  = $tenant->slug ?? 'default';
                                $fecha = now()->format('Y-m-d');
                                $folder = "tenants/{$slug}/comprobantes/cdr/{$fecha}";
                                $nombreBase = "{$sale->serie}-{$sale->correlativo}";
                                $pathCdrZip = "{$folder}/R-{$nombreBase}.zip";
                                Storage::disk('public')->put($pathCdrZip, base64_decode($cdrBase64));
                            }

                            // Actualizar la venta como ACEPTADA
                            $sale->update([
                                'status_sunat' => 'aceptado',
                                'hash'         => $data['hash'] ?? null,
                                'path_xml'     => $pathXml,
                                'path_cdrZip'  => $pathCdrZip,
                                'success'      => true,
                                'code'         => $data['sunatResponse']['cdrResponse']['code'] ?? null,
                                'description'  => $data['sunatResponse']['cdrResponse']['description'] ?? null,
                                'notes'        => json_encode($data['sunatResponse']['cdrResponse']['notes'] ?? []),
                                'qr_data'      => $data['qr_data'] ?? null,
                                'total_letras' => $data['total_letras'] ?? null,
                            ]);
                            $statusSunatMensaje = 'aceptado';
                        } else {
                            // SUNAT procesó el XML pero encontró errores (ej. RUC inválido)
                            $sale->update([
                                'status_sunat' => 'rechazado',
                                'hash'         => $data['hash'] ?? null,
                                'path_xml'     => $pathXml, // Si hubo error validando en SUNAT, Greenter sí genera un XML
                                'success'      => false,
                                'code'         => $data['sunatResponse']['error']['code'] ?? null,
                                'message'      => $data['sunatResponse']['error']['message'] ?? null,
                                'description'  => "Error SUNAT: " . ($data['sunatResponse']['error']['message'] ?? ''),
                                'qr_data'      => $data['qr_data'] ?? null,
                                'total_letras' => $data['total_letras'] ?? null,
                            ]);
                            $statusSunatMensaje = 'rechazado';
                        }
                    }
                } catch (\Exception $apiEx) {
                    // Si ocurre un error fatal en tu API central (ej 500 server error), 
                    // atrapamos el error para no asustar al cajero, la venta ya está en BD.
                    $sale->update([
                        'status_sunat' => 'error_api',
                        'message'      => $apiEx->getMessage()
                    ]);
                    $statusSunatMensaje = 'error_api';
                }
            }
        }

        // ==========================================
        // 🖨️ 7. GENERACIÓN DE PDF Y NOTIFICACIONES
        // ==========================================
        try {
            // El modelo sale ya está actualizado (sale->fresh() asegura tener los paths actualizados)
            $pdfService = app(\App\Services\TicketPdfService::class);
            $pdfService->generateAndSave($sale->fresh(), $tenant);
        } catch (\Exception $e) {
            \Log::error("Error generando PDF de la venta {$sale->id}: " . $e->getMessage());
        }

        $this->puedeImprimirComprobante = $config->mostrar_modal_impresion_comprobante ?? true;

        if ($statusSunatMensaje === 'aceptado') {
            Notification::make()->title('Venta y SUNAT exitosos')->success()->send();
        } elseif (in_array($statusSunatMensaje, ['rechazado', 'error_api'])) {
            Notification::make()->title('Venta guardada (Error SUNAT)')->body('Revisa el estado de la factura')->warning()->send();
        } else {
            Notification::make()->title('Venta exitosa')->success()->send();
        }

        $this->ventaExitosaId = $sale->id;
        $this->mostrarPantallaExito = true;
    }

    public function terminarProcesoVenta()
    {
        return redirect()->to("/app/point-of-sale");
    }

    public function getHeading(): string
    {
        return '';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
