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

    public function mount($record)
    {
        $this->tenantSlug = Filament::getTenant()->slug;
        $this->order = Order::with(['details.product.unit', 'details.variant'])->findOrFail($record);
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
        // 1. PREPARACIÓN (Fuera de transacción para no bloquear)
        $this->calculateTotalServer();
        $totalPagado = collect($this->pagos_agregados)->sum('amount');
        $tenantId = Filament::getTenant()->id;
        $userId = Auth::id();

        // Validaciones rápidas
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

        // Carga previa de stocks para evitar el problema N+1
        $almacenes = Warehouse::where('restaurant_id', $tenantId)->get();
        $variantIds = $this->items->pluck('variant_id')->unique();
        $todosLosStocks = WarehouseStock::whereIn('variant_id', $variantIds)
            ->whereIn('warehouse_id', $almacenes->pluck('id'))
            ->get()
            ->groupBy('variant_id');

        // 2. INICIO DE TRANSACCIÓN ATÓMICA
        DB::beginTransaction();
        try {
            // Bloqueos pesimistas para concurrencia
            $order = Order::where('id', $this->order->id)->lockForUpdate()->first();
            if ($order->status === 'pagado') throw new \Exception("Orden ya procesada.");

            $serieConfig = DocumentSerie::where('id', $this->serie_id)->lockForUpdate()->first();
            if (!$serieConfig) throw new \Exception("Serie no válida.");

            $correlativo = $this->obtenerCorrelativoFinal($this->serie_id);

            // Crear Venta (Cabecera)
            $sale = Sale::create([
                'restaurant_id'    => $tenantId,
                'order_id'         => $order->id,
                'client_id'        => $this->cliente_seleccionado?->id,
                'user_id'          => $userId,
                'nombre_cliente'   => $this->cliente_seleccionado?->full_name ?? 'Cliente Varios',
                'tipo_documento'   => $this->cliente_seleccionado?->tipo_documento ?? 'DNI',
                'numero_documento' => $this->cliente_seleccionado?->numero ?? '00000000',
                'tipo_comprobante' => $this->tipo_comprobante,
                'serie'            => $serieConfig->serie,
                'correlativo'      => $correlativo,
                'monto_descuento'  => $this->monto_descuento,
                'op_gravada'       => $this->op_gravada,
                'monto_igv'        => $this->monto_igv,
                'total'            => $this->total_final,
                'status'           => 'completado',
                'fecha_emision'    => now(),
            ]);

            $detallesParaInsertar = [];
            $detallesParaKardex = collect();

            // 3. PROCESAMIENTO DE ITEMS EN MEMORIA
            foreach ($this->items as $item) {
                // Determinar almacén mediante el Service
                $almacenSeleccionado = $inventoryService->determinarAlmacenParaItem(
                    $item,
                    $almacenes,
                    $todosLosStocks,
                    $almacenes->first()
                );

                if ($item->product->control_stock && !$almacenSeleccionado) {
                    throw new \Exception("Sin stock disponible para: " . $item->product->name);
                }

                // Preparar array para Batch Insert
                $detallesParaInsertar[] = [
                    'sale_id'         => $sale->id,
                    'product_id'      => $item->product_id,
                    'variant_id'      => $item->variant_id,
                    'product_name'    => $item->product->name,
                    'cantidad'        => $item->cantidad,
                    'precio_unitario' => $item->price,
                    'subtotal'        => $item->subTotal,
                ];

                // Preparar para actualización de stock (Trait)
                if ($item->product->control_stock) {
                    // Creamos un objeto temporal para que el Trait pueda leer sus relaciones
                    $tempDetail = new \App\Models\SaleDetail($detallesParaInsertar[count($detallesParaInsertar) - 1]);
                    $tempDetail->setRelation('warehouse', $almacenSeleccionado);
                    $tempDetail->setRelation('product', $item->product);
                    $tempDetail->setRelation('variant', $item->variant);
                    $tempDetail->setRelation('unit', $item->product->unit);
                    $detallesParaKardex->push($tempDetail);
                }
            }

            // 4. INSERCIONES MASIVAS (BATCH)
            DB::table('sale_details')->insert($detallesParaInsertar);

            $detallesReales = \App\Models\SaleDetail::where('sale_id', $sale->id)
                ->orderBy('id', 'asc') // El orden del insert masivo se respeta en los IDs
                ->get();

            // Sincronizamos los IDs con tus objetos de Kardex
            foreach ($detallesParaKardex as $index => $tempDetail) {
                if (isset($detallesReales[$index])) {
                    // Le "tatuamos" el ID real al objeto temporal antes de ir al Trait
                    $tempDetail->id = $detallesReales[$index]->id;
                    $tempDetail->exists = true; // Le decimos a Eloquent que ya existe en la DB
                }
            }

            // Ahora, cuando applyVentaMasiva lea $item->id, ya no será null
            if ($detallesParaKardex->isNotEmpty()) {
                $this->applyVentaMasiva($detallesParaKardex, 'salida', "{$sale->serie}-{$sale->correlativo}", 'Venta');
            }

            // 5. REGISTRAR PAGOS EN CAJA (Batch)
            // 5. REGISTRAR PAGOS EN CAJA (Batch)
            $movimientosCaja = collect($this->pagos_agregados)->map(function ($pago) use ($sale, $userId) {

                // Si hay una referencia guardada en la propiedad del componente
                $referenciaStr = !empty($this->referencia_pago)
                    ? " | Ref: " . $this->referencia_pago
                    : "";

                return [
                    'payment_method_id' => $pago['id'],
                    'usuario_id'        => $userId,
                    'tipo'              => 'Ingreso',
                    // Concatenamos la referencia al motivo original
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

            // 6. CIERRE DE ESTADOS
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
            // return redirect()->to("/app/point-of-sale");
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
