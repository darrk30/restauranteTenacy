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
use App\Models\Warehouse;
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

    public function procesarPagoFinal(InventoryService $inventoryService)
    {
        // 1. PREPARACIÓN
        $this->calculateTotalServer();
        $totalPagado = collect($this->pagos_agregados)->sum('amount');
        $tenantId = Filament::getTenant()->id;
        $userId = Auth::id();

        // Validaciones básicas de caja y montos
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

        // --------------------------------------------------------------------------
        // A. RECOLECCIÓN DE IDs PARA CONSULTA MASIVA DE STOCK (OPTIMIZACIÓN)
        // --------------------------------------------------------------------------
        $almacenes = Warehouse::where('restaurant_id', $tenantId)->get();
        $variantIdsToCheck = collect();

        foreach ($this->items as $item) {
            if ($item->item_type === 'Promocion' && $item->promotion) {
                // Si es promoción, recolectamos los IDs de sus hijos
                foreach ($item->promotion->promotionproducts as $subItem) {
                    if ($subItem->variant_id) {
                        $variantIdsToCheck->push($subItem->variant_id);
                    }
                }
            } elseif ($item->variant_id) {
                // Si es producto normal
                $variantIdsToCheck->push($item->variant_id);
            }
        }

        $variantIdsToCheck = $variantIdsToCheck->unique();

        // Traemos todo el stock necesario de una sola vez
        $todosLosStocks = WarehouseStock::whereIn('variant_id', $variantIdsToCheck)
            ->whereIn('warehouse_id', $almacenes->pluck('id'))
            ->get()
            ->groupBy('variant_id');
        // --------------------------------------------------------------------------


        DB::beginTransaction();
        try {
            // Bloqueos y creación de Venta (Cabecera) - IGUAL QUE ANTES
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

            $detallesParaInsertar = []; // Para tabla sale_details (Recibo)
            $detallesParaKardex = collect(); // Para mover stock

            // 3. PROCESAMIENTO DE ITEMS
            foreach ($this->items as $item) {

                // --- PASO 1: REGISTRAR EN EL RECIBO (Siempre va lo que se pidió: el Combo o el Producto) ---
                $detallesParaInsertar[] = [
                    'sale_id'         => $sale->id,
                    'product_id'      => $item->product_id,
                    'variant_id'      => $item->variant_id,
                    'product_name'    => $item->product_name ?? $item->product->name, // Usar nombre guardado o del producto
                    'cantidad'        => $item->cantidad,
                    'precio_unitario' => $item->price,
                    'subtotal'        => $item->subTotal,
                ];

                // --- PASO 2: LÓGICA DE STOCK (KARDEX) ---

                // CASO A: ES UNA PROMOCIÓN
                if ($item->item_type === 'Promocion' && $item->promotion) {

                    // Iteramos los ingredientes/productos de la promoción
                    foreach ($item->promotion->promotionproducts as $subItem) {
                        $productoHijo = $subItem->product;

                        // Solo procesamos si el hijo controla stock
                        if ($productoHijo && $productoHijo->control_stock) {

                            // Cantidad total = (Cantidad de Combos pedidos) * (Cantidad del item en el combo)
                            $cantidadADescontar = $item->cantidad * $subItem->quantity;

                            // Simulamos un objeto para usar el servicio de almacén o usamos lógica directa
                            // Como es un sub-item, no tenemos un 'OrderDetail', así que validamos stock manualmente
                            // con la colección $todosLosStocks que cargamos al principio.

                            // Lógica simplificada: Usar el primer almacén que tenga stock o el default
                            $almacenHijo = $almacenes->first(); // Puedes mejorar esto buscando en $todosLosStocks cuál tiene saldo

                            // Validar Stock
                            // Asumiendo que usas variant_id para controlar stock si existe
                            $stockKey = $subItem->variant_id ?? null;

                            if ($stockKey) {
                                $stockEnAlmacen = $todosLosStocks->has($stockKey)
                                    ? $todosLosStocks->get($stockKey)->where('warehouse_id', $almacenHijo->id)->sum('stock_actual')
                                    : 0;

                                // Opcional: Lanzar excepción si no hay stock del componente
                                // if ($stockEnAlmacen < $cantidadADescontar) throw new \Exception("Falta stock componente: " . $productoHijo->name);
                            }

                            // Crear Movimiento Virtual para Kardex
                            $tempDetail = new \App\Models\SaleDetail([
                                'product_id' => $productoHijo->id,
                                'variant_id' => $subItem->variant_id,
                                'cantidad'   => $cantidadADescontar,
                                'sale_id'    => $sale->id, // Vinculado a la venta padre
                            ]);

                            $tempDetail->setRelation('warehouse', $almacenHijo);
                            $tempDetail->setRelation('product', $productoHijo);
                            $tempDetail->setRelation('variant', $subItem->variant); // Importante si es variante
                            $tempDetail->setRelation('unit', $productoHijo->unit);

                            $detallesParaKardex->push($tempDetail);
                        }
                    }
                }

                // CASO B: ES UN PRODUCTO INDIVIDUAL (Tu lógica original)
                elseif ($item->product && $item->product->control_stock) {

                    $almacenSeleccionado = $inventoryService->determinarAlmacenParaItem(
                        $item,
                        $almacenes,
                        $todosLosStocks,
                        $almacenes->first()
                    );

                    if (!$almacenSeleccionado) {
                        throw new \Exception("Sin stock disponible para: " . $item->product->name);
                    }

                    // Usamos el último elemento agregado a $detallesParaInsertar para crear el objeto
                    $tempDetail = new \App\Models\SaleDetail(end($detallesParaInsertar));
                    $tempDetail->setRelation('warehouse', $almacenSeleccionado);
                    $tempDetail->setRelation('product', $item->product);
                    $tempDetail->setRelation('variant', $item->variant);
                    $tempDetail->setRelation('unit', $item->product->unit);

                    $detallesParaKardex->push($tempDetail);
                }
            }

            // 4. INSERCIONES MASIVAS EN BD (EL RECIBO)
            DB::table('sale_details')->insert($detallesParaInsertar);

            // 5. MOVIMIENTO DE KARDEX (DESCUENTO DE STOCK)
            // Solo si hay items que controlan stock (sean directos o hijos de promo)
            if ($detallesParaKardex->isNotEmpty()) {
                // Nota: applyVentaMasiva usa los objetos SaleDetail virtuales que creamos.
                // Para productos normales, los IDs coincidirán después del insert si los recuperamos,
                // pero para hijos de promo, son objetos 'new' que no están en sale_details.
                // El trait debe saber manejar esto (generalmente usa product_id/variant_id/warehouse_id).

                $this->applyVentaMasiva($detallesParaKardex, 'salida', "{$sale->serie}-{$sale->correlativo}", 'Venta');
            }

            // 6. REGISTRO DE CAJA
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

            // 7. CIERRE DE ESTADOS
            $order->update(['status' => 'pagado']);
            if ($order->table_id) {
                Table::where('id', $order->table_id)->update([
                    'estado_mesa' => 'libre',
                    'order_id' => null,
                    'asientos' => 0
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
