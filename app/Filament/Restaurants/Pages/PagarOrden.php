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
use App\Traits\ManjoStockProductos;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

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
    public $canal_orden;

    public function mount($record)
    {
        $this->tenantSlug = Filament::getTenant()->slug;
        $this->order = Order::with([
            'details.product.unit',
            'details.variant',
            'details.promotion.promotionproducts.product.unit', // Producto del hijo
            'details.promotion.promotionproducts.variant'       // Variante del hijo
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

    private function obtenerCorrelativoFinal($serieId)
    {
        $tenantId = filament()->getTenant()->id;
        $serieConfig = DocumentSerie::where('restaurant_id', $tenantId)
            ->findOrFail($serieId);

        // 1. Verificar si hay una última venta registrada para esta serie y restaurante
        $ultimaVenta = Sale::where('restaurant_id', $tenantId)
            ->where('serie', $serieConfig->serie)
            ->latest('id')
            ->first();

        if ($ultimaVenta) {
            // Si hay ventas, el nuevo correlativo es el último + 1
            $nuevoNumero = intval($ultimaVenta->correlativo) + 1;
        } else {
            // Si NO hay ventas, tomamos el current_number de la configuración + 1
            $nuevoNumero = $serieConfig->current_number + 1;
        }

        // 2. Actualizamos el DocumentSerie para que esté sincronizado
        $serieConfig->update([
            'current_number' => $nuevoNumero
        ]);

        // 3. Retornamos formateado a 8 dígitos
        return str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);
    }

    // public function procesarPagoFinal()
    // {
    //     // 1. PREPARACIÓN Y VALIDACIONES DE CAJA
    //     $this->calculateTotalServer();
    //     $totalPagado = collect($this->pagos_agregados)->sum('amount');
    //     $tenantId = Filament::getTenant()->id;
    //     $userId = Auth::id();

    //     // Validar que la caja esté abierta
    //     $sesionCaja = SessionCashRegister::where('restaurant_id', $tenantId)
    //         ->where('user_id', $userId)
    //         ->where('status', 'open')
    //         ->first();

    //     if (!$sesionCaja) {
    //         Notification::make()->title('Caja Cerrada')->danger()->send();
    //         return;
    //     }

    //     // Validar montos completos
    //     if ($totalPagado < ($this->total_final - 0.01)) {
    //         Notification::make()->title('Pago incompleto')->danger()->send();
    //         return;
    //     }

    //     DB::beginTransaction();
    //     try {
    //         // 2. BLOQUEOS Y CREACIÓN DE LA VENTA (CABECERA)
    //         $order = Order::where('id', $this->order->id)->lockForUpdate()->first();
    //         if ($order->status === 'pagado') throw new \Exception("Orden ya procesada.");

    //         $serieConfig = DocumentSerie::where('id', $this->serie_id)->lockForUpdate()->first();
    //         if (!$serieConfig) throw new \Exception("Serie no válida.");

    //         $correlativo = $this->obtenerCorrelativoFinal($this->serie_id);
    //         $nombreFinalCliente = $this->cliente_seleccionado
    //             ? ($this->cliente_seleccionado->razon_social ?? ($this->cliente_seleccionado->nombres . ' ' . $this->cliente_seleccionado->apellidos))
    //             : 'CLIENTES VARIOS';

    //         // Crear la Venta
    //         $sale = Sale::create([
    //             'restaurant_id'    => $tenantId,
    //             'order_id'         => $order->id,
    //             'client_id'        => $this->cliente_seleccionado?->id,
    //             'user_id'          => $userId,
    //             'nombre_cliente'   => $nombreFinalCliente,
    //             'tipo_documento'   => $this->cliente_seleccionado?->tipo_documento ?? 'DNI',
    //             'numero_documento' => $this->cliente_seleccionado?->numero ?? '99999999',
    //             'tipo_comprobante' => $this->tipo_comprobante,
    //             'serie'            => $serieConfig->serie,
    //             'correlativo'      => $correlativo,
    //             'monto_descuento'  => $this->monto_descuento,
    //             'op_gravada'       => $this->op_gravada,
    //             'monto_igv'        => $this->monto_igv,
    //             'total'            => $this->total_final,
    //             'status'           => 'completado',
    //             'canal'            => $order->canal,
    //             'delivery_id'      => $order->delivery_id,
    //             'nombre_delivery'  => $order->nombre_delivery,
    //             'fecha_emision'    => now(),
    //         ]);

    //         $detallesParaInsertar = [];
    //         $detallesParaKardex = collect();

    //         // 3. PROCESAMIENTO DE ITEMS (RECIBO Y PREPARACIÓN KARDEX)
    //         foreach ($this->items as $item) {
    //             $esPromocion = ($item->item_type === 'Promocion');

    //             // A. Preparar datos para 'sale_details' (El recibo visual)
    //             $detallesParaInsertar[] = [
    //                 'sale_id'         => $sale->id,
    //                 'product_id'      => $esPromocion ? null : $item->product_id,
    //                 'variant_id'      => $esPromocion ? null : $item->variant_id,
    //                 'promotion_id'    => $esPromocion ? $item->promotion_id : null,
    //                 'product_name'    => $item->product_name ?? ($esPromocion ? ($item->promotion->name ?? 'Promoción') : ($item->product->name ?? 'Producto')),
    //                 'cantidad'        => $item->cantidad,
    //                 'precio_unitario' => $item->price,
    //                 'subtotal'        => $item->subTotal,
    //                 'created_at'      => now(),
    //                 'updated_at'      => now(),
    //             ];

    //             // B. Preparar objetos virtuales para el Trait de Stock (Kardex)
    //             if ($esPromocion && $item->promotion) {
    //                 // Desglosar la promoción para descontar ingredientes/productos
    //                 foreach ($item->promotion->promotionproducts as $subItem) {
    //                     $productoHijo = $subItem->product;

    //                     if ($productoHijo && $productoHijo->control_stock) {
    //                         $cantidadADescontar = $item->cantidad * $subItem->quantity;

    //                         $tempDetail = new \App\Models\SaleDetail([
    //                             'product_id' => $productoHijo->id,
    //                             'variant_id' => $subItem->variant_id,
    //                             'cantidad'   => $cantidadADescontar,
    //                             'sale_id'    => $sale->id,
    //                         ]);
    //                         $tempDetail->setRelation('product', $productoHijo);
    //                         $tempDetail->setRelation('variant', $subItem->variant);
    //                         $tempDetail->setRelation('unit', $productoHijo->unit);

    //                         $detallesParaKardex->push($tempDetail);
    //                     }
    //                 }
    //             } elseif ($item->product && $item->product->control_stock) {
    //                 // Producto normal con control de stock
    //                 $tempDetail = new \App\Models\SaleDetail([
    //                     'product_id' => $item->product_id,
    //                     'variant_id' => $item->variant_id,
    //                     'cantidad'   => $item->cantidad,
    //                     'sale_id'    => $sale->id,
    //                 ]);

    //                 $tempDetail->setRelation('product', $item->product);
    //                 $tempDetail->setRelation('variant', $item->variant);
    //                 $tempDetail->setRelation('unit', $item->product->unit);

    //                 $detallesParaKardex->push($tempDetail);
    //             }
    //         }

    //         // 4. INSERCIÓN MASIVA DEL DETALLE DE VENTA
    //         DB::table('sale_details')->insert($detallesParaInsertar);

    //         // 5. MOVIMIENTO DE STOCK (KARDEX) - Centralizado
    //         if ($detallesParaKardex->isNotEmpty()) {
    //             // El Trait buscará el stock por variant_id directamente
    //             $this->applyVentaMasiva(
    //                 $detallesParaKardex,
    //                 'salida',
    //                 "{$sale->serie}-{$sale->correlativo}",
    //                 'Venta'
    //             );
    //         }

    //         // 6. REGISTRO DE MOVIMIENTOS EN CAJA
    //         $movimientosCaja = collect($this->pagos_agregados)->map(function ($pago) use ($sale, $userId) {
    //             $referenciaStr = !empty($this->referencia_pago) ? " | Ref: " . $this->referencia_pago : "";
    //             return [
    //                 'payment_method_id' => $pago['id'],
    //                 'usuario_id'        => $userId,
    //                 'tipo'              => 'Ingreso',
    //                 'motivo'            => "Venta: {$sale->serie}-{$sale->correlativo}" . $referenciaStr,
    //                 'monto'             => $pago['amount'],
    //                 'referencia_type'   => Sale::class,
    //                 'referencia_id'     => $sale->id,
    //                 'created_at'        => now(),
    //                 'updated_at'        => now(),
    //             ];
    //         })->toArray();

    //         $sesionCaja->cashRegisterMovements()->createMany($movimientosCaja);
    //         $this->referencia_pago = '';

    //         // 7. ACTUALIZACIÓN DE ESTADOS (Orden y Mesa)
    //         $order->update(['status' => 'pagado']);

    //         if ($order->table_id) {
    //             Table::where('id', $order->table_id)->update([
    //                 'estado_mesa' => 'libre',
    //                 'order_id'    => null,
    //                 'asientos'    => 0
    //             ]);
    //         }

    //         DB::commit();

    //         // Finalización exitosa
    //         Notification::make()->title('Venta exitosa')->success()->send();
    //         $this->ventaExitosaId = $sale->id;
    //         $this->mostrarPantallaExito = true;
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
    //     }
    // }

    public function procesarPagoFinal()
    {
        // 1. VALIDACIONES PREVIAS (Caja, Montos, etc.)
        $this->calculateTotalServer();
        $totalPagado = collect($this->pagos_agregados)->sum('amount');
        $tenantId = Filament::getTenant()->id;
        $userId = Auth::id();

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
            // 2. BLOQUEOS Y CABECERA DE VENTA
            $order = Order::where('id', $this->order->id)->lockForUpdate()->first();
            if ($order->status === 'pagado') throw new \Exception("Orden ya procesada.");

            $serieConfig = DocumentSerie::where('id', $this->serie_id)->lockForUpdate()->first();
            if (!$serieConfig) throw new \Exception("Serie no válida.");

            $correlativo = $this->obtenerCorrelativoFinal($this->serie_id);
            $nombreFinalCliente = $this->cliente_seleccionado
                ? ($this->cliente_seleccionado->razon_social ?? ($this->cliente_seleccionado->nombres . ' ' . $this->cliente_seleccionado->apellidos))
                : 'CLIENTES VARIOS';

            $sale = Sale::create([
                'restaurant_id'    => $tenantId,
                'order_id'         => $order->id,
                'client_id'        => $this->cliente_seleccionado?->id,
                'user_id'          => $userId,
                'nombre_cliente'   => $nombreFinalCliente,
                'tipo_documento'   => $this->cliente_seleccionado?->tipo_documento ?? 'DNI',
                'numero_documento' => $this->cliente_seleccionado?->numero ?? '99999999',
                'tipo_comprobante' => $this->tipo_comprobante,
                'serie'            => $serieConfig->serie,
                'correlativo'      => $correlativo,
                'monto_descuento'  => $this->monto_descuento,
                'op_gravada'       => $this->op_gravada,
                'monto_igv'        => $this->monto_igv,
                'total'            => $this->total_final,
                'status'           => 'completado',
                'canal'            => $order->canal,
                'delivery_id'      => $order->delivery_id,
                'nombre_delivery'  => $order->nombre_delivery,
                'fecha_emision'    => now(),
            ]);

            $detallesParaKardex = collect();

            // 3. PROCESAMIENTO DE ITEMS
            foreach ($this->items as $item) {
                $esPromocion = ($item->item_type === 'Promocion');

                // A. Guardamos el detalle visual (SaleDetail)
                // Se guarda lo que se vendió: "1 Ceviche" o "1 Gaseosa"
                $saleDetail = new \App\Models\SaleDetail([
                    'sale_id'         => $sale->id,
                    'product_id'      => $esPromocion ? null : $item->product_id,
                    'variant_id'      => $esPromocion ? null : $item->variant_id,
                    'promotion_id'    => $esPromocion ? $item->promotion_id : null,
                    'product_name'    => $item->product_name ?? ($esPromocion ? ($item->promotion->name ?? 'Promoción') : ($item->product->name ?? 'Producto')),
                    'cantidad'        => $item->cantidad,
                    'precio_unitario' => $item->price,
                    'subtotal'        => $item->subTotal,
                ]);

                // Guardamos inmediatamente para tener ID y relaciones listas para el Kardex
                $saleDetail->save();

                // Cargamos relaciones para que el Trait no tenga que consultar de nuevo
                if (!$esPromocion) {
                    $saleDetail->load(['product', 'variant', 'product.unit']);
                } else {
                    $saleDetail->load(['promotion.promotionproducts.product', 'promotion.promotionproducts.variant']);
                }

                // B. Lo agregamos a la colección para procesar STOCK
                // Pasamos el objeto COMPLETO. El Trait decidirá si descuenta directo o busca receta.
                $detallesParaKardex->push($saleDetail);
            }

            // 4. MOVIMIENTO DE STOCK (KARDEX) - LLAMADA AL TRAIT INTELIGENTE
            if ($detallesParaKardex->isNotEmpty()) {
                $this->applyVentaMasiva(
                    $detallesParaKardex,
                    'salida',
                    "{$sale->serie}-{$sale->correlativo}",
                    'Venta'
                );
            }

            // 5. REGISTRO CAJA
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

            // 6. ACTUALIZACIÓN ESTADOS
            $order->update(['status' => 'pagado']);

            if ($order->table_id) {
                Table::where('id', $order->table_id)->update([
                    'estado_mesa' => 'libre',
                    'order_id'    => null,
                    'asientos'    => 0
                ]);
            }

            DB::commit();

            Notification::make()->title('Venta exitosa')->success()->send();
            $this->ventaExitosaId = $sale->id;
            $this->mostrarPantallaExito = true;
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function terminarProcesoVenta()
    {
        return redirect()->to("/app/point-of-sale");
    }

    public function getHeading(): string
    {
        return '';
    }
}
