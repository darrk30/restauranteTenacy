<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Order;
use App\Models\Client;
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
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SessionCashRegister;
use App\Models\Table;
use App\Traits\ManjoStockProductos;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
    public $metodos_pago;
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

    // Propiedades para el formulario de pago
    public $metodo_pago_seleccionado_id;
    public $monto_a_pagar = 0;
    public $requiere_referencia = false;

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
        $this->metodos_pago = PaymentMethod::where('status', true)->get();

        // 1. Seleccionar Efectivo por defecto
        $efectivo = $this->metodos_pago->firstWhere('name', 'Efectivo') ?? $this->metodos_pago->first();

        if ($efectivo) {
            $this->metodo_pago_seleccionado_id = $efectivo->id;
            $this->requiere_referencia = $efectivo->requiere_referencia;
        }
        $this->subtotal_base = $this->items->sum('subTotal');
        $this->order->client_id ? $this->seleccionarCliente($this->order->client_id) : $this->setClienteVarios();

        $this->subtotal_base = $this->items->sum('subTotal');
        $this->calculateTotalServer();
        $this->sugerirMontoFaltante();
    }

    // Inicializar el primer método por defecto en el mount o cuando cargues los métodos
    public function updatedMetodosPago($value)
    {
        if (!empty($this->metodos_pago) && !$this->metodo_pago_seleccionado_id) {
            $this->seleccionarMetodo($this->metodos_pago[0]->id);
        }
    }

    public function seleccionarMetodo($id)
    {
        // Usamos collect() para asegurar que no falle si por alguna razón se convierte en array
        $metodo = collect($this->metodos_pago)->firstWhere('id', $id);

        if ($metodo) {
            $this->metodo_pago_seleccionado_id = $id;
            $this->requiere_referencia = $metodo->requiere_referencia;
            $this->referencia_pago = ''; // Resetear nro operación
            $this->sugerirMontoFaltante();
        }
    }

    public function sugerirMontoFaltante()
    {
        $totalPagado = collect($this->pagos_agregados)->sum('amount');
        $faltante = $this->total_final - $totalPagado;
        // Forzamos 2 decimales para el input
        $this->monto_a_pagar = $faltante > 0 ? number_format($faltante, 2, '.', '') : '0.00';
    }

    public function agregarPago()
    {
        if ($this->monto_a_pagar <= 0) return;

        $metodo = collect($this->metodos_pago)->firstWhere('id', $this->metodo_pago_seleccionado_id);
        $ref = trim($this->referencia_pago);

        // Buscar si ya existe el mismo método y referencia para sumar montos
        $index = collect($this->pagos_agregados)->search(function ($item) use ($ref) {
            return $item['id'] == $this->metodo_pago_seleccionado_id && $item['referencia'] == $ref;
        });

        if ($index !== false) {
            $this->pagos_agregados[$index]['amount'] = round($this->pagos_agregados[$index]['amount'] + (float)$this->monto_a_pagar, 2);
        } else {
            $this->pagos_agregados[] = [
                'id' => $metodo->id,
                'name' => $metodo->name,
                'amount' => round((float)$this->monto_a_pagar, 2),
                'referencia' => $ref,
            ];
        }

        $this->referencia_pago = '';
        $this->sugerirMontoFaltante();
    }

    public function quitarPago($index)
    {
        unset($this->pagos_agregados[$index]);
        $this->pagos_agregados = array_values($this->pagos_agregados); // Reindexar
        $this->sugerirMontoFaltante();
    }

    // Actualizar montos cuando cambie el descuento
    public function updatedMontoDescuento()
    {
        $this->calculateTotalServer();
        $this->sugerirMontoFaltante();
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
        $divisor = get_tax_divisor();
        $this->op_gravada = round($this->total_final / $divisor, 2);
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

        return $nuevoNumero;
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
                $divisor = get_tax_divisor($tenantId);
                $valorTotal = round($subtotal / $divisor, 2);
                $valorUnitario = ($cantidad > 0) ? ($valorTotal / $cantidad) : 0;

                $costoUnitario = 0;
                if (!$esPromocion && $item->variant) {
                    $costoUnitario = (float) ($item->variant->costo ?? 0);
                }
                $costoTotal = round($costoUnitario * $cantidad, 2);
                $costoTotalVenta += $costoTotal;

                $saleDetail = new SaleDetail([
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
            $config = $tenant->cached_config;
            $apiKey = $config->api_token;
            if (empty($apiKey)) {
                $sale->update([
                    'status_sunat' => 'error_api',
                    'message' => "El restaurante no tiene API Token configurada en la sección de Configuración General."
                ]);
                $statusSunatMensaje = 'error_api';
            } else {
                try {
                    $payloadBuilder = app(\App\Services\InvoicePayloadService::class);
                    $payload = $payloadBuilder->buildFromSale($sale, $tenant);

                    $sunatService = app(\App\Services\SunatGreenterApiService::class);
                    $respuesta = $sunatService->sendInvoice($payload, $apiKey);
                    if (!$respuesta['success']) {
                        // Falló la conexión o hubo un error fatal en la API interna (Greenter no respondió)
                        $sale->update([
                            'status_sunat' => 'error_api',
                            'message'      => $respuesta['error_data']['error'] ?? $respuesta['message'] ?? 'Error de conexión.'
                        ]);
                        $statusSunatMensaje = 'error_api';
                    } else {
                        // Greenter respondió correctamente (con éxito o error de SUNAT)
                        $data = $respuesta['data'];
                        $apiSuccess = $data['sunatResponse']['success'] ?? false;

                        // 1. Guardar Archivos (XML y CDR) si existen
                        $slug  = $tenant->slug ?? 'default';
                        $fecha = now()->format('Y-m-d');
                        $folderXml = "tenants/{$slug}/comprobantes/xml/{$fecha}";
                        $folderCdr = "tenants/{$slug}/comprobantes/cdr/{$fecha}";
                        $nombreBase = "{$tenant->ruc}-{$sale->serie}-{$sale->correlativo}";

                        $pathXml = null;
                        if (!empty($data['xml'])) {
                            $pathXml = "{$folderXml}/{$nombreBase}.xml";
                            Storage::disk('public')->put($pathXml, base64_decode($data['xml']));
                        }

                        $pathCdrZip = null;
                        if (!empty($data['sunatResponse']['cdrZip'])) {
                            $pathCdrZip = "{$folderCdr}/R-{$nombreBase}.zip";
                            Storage::disk('public')->put($pathCdrZip, base64_decode($data['sunatResponse']['cdrZip']));
                        }

                        // 🟢 2. CREAMOS EL ARRAY BASE (Datos que siempre se actualizan)
                        $updateData = [
                            'hash'         => $data['hash'] ?? null,
                            'path_xml'     => $pathXml,
                            'qr_data'      => $data['qr_data'] ?? null,
                            'total_letras' => $data['total_letras'] ?? null,
                        ];

                        // 🟢 3. AÑADIMOS DATOS ESPECÍFICOS SEGÚN EL ESTADO
                        if ($apiSuccess) {
                            $updateData['success'] = true;

                            if ($payload['enviar_sunat'] ?? false) {
                                // A. Enviado a SUNAT y Aceptado
                                $updateData['status_sunat'] = 'aceptado';
                                $updateData['path_cdrZip']  = $pathCdrZip;
                                $updateData['code']         = $data['sunatResponse']['cdrResponse']['code'] ?? null;
                                $updateData['description']  = $data['sunatResponse']['cdrResponse']['description'] ?? null;
                                $updateData['notes']        = json_encode($data['sunatResponse']['cdrResponse']['notes'] ?? []);
                                $statusSunatMensaje         = 'aceptado';
                            } else {
                                // B. Solo Firmado Localmente (No enviado a SUNAT)
                                $updateData['status_sunat'] = 'registrado';
                                $updateData['description']  = 'XML generado y firmado localmente. Pendiente de envío a SUNAT.';
                                $statusSunatMensaje         = 'registrado';
                            }
                        } else {
                            // C. Enviado a SUNAT pero Rechazado (Ej. RUC inválido)
                            $updateData['success']      = false;
                            $updateData['status_sunat'] = 'rechazado';
                            $updateData['code']         = $data['sunatResponse']['error']['code'] ?? null;
                            $updateData['message']      = $data['sunatResponse']['error']['message'] ?? null;
                            $updateData['description']  = "Error SUNAT: " . ($data['sunatResponse']['error']['message'] ?? '');
                            $statusSunatMensaje         = 'rechazado';
                        }

                        // 🟢 4. EJECUTAMOS EL UPDATE UNA SOLA VEZ
                        $sale->update($updateData);
                    }
                } catch (\Exception $apiEx) {
                    // 🟢 Esto evita que el error "Data too long" detenga el sistema
                    $mensajeRecortado = substr($apiEx->getMessage(), 0, 250);

                    $sale->update([
                        'status_sunat' => 'error_api',
                        'message'      => $mensajeRecortado
                    ]);
                    $statusSunatMensaje = 'error_api';
                }
            }
        }

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
        return redirect()->to("/pdv/point-of-sale");
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
