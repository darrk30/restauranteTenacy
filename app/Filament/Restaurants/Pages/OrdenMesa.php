<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Services\OrdenService;
use App\Models\Variant;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pest\Support\Str;

class OrdenMesa extends Page
{
    protected static string $view = 'filament.pdv.orden-mesa';
    protected static string $panel = 'restaurants';

    // === PROPIEDADES DE URL ===
    public int $mesa;
    public ?int $pedido = null;

    // === FILTROS ===
    public $categoriaSeleccionada = null;
    public $search = '';

    public $cantidadesOriginales = [];

    public $stockActualVariante = 0; // Stock Real de la variante seleccionada
    public $stockReservaVariante = 0; // Stock en Reserva

    // === VARIABLES DEL MODAL (PRODUCTO SELECCIONADO) ===
    public ?Product $productoSeleccionado = null;
    public $variantSeleccionadaId = null;
    public $esCortesia = false;
    public $notaPedido = '';

    // Lógica de Selección
    public $selectedAttributes = []; // [ attribute_id => value_id ]
    public $precioCalculado = 0.00;

    // === VARIABLES DEL CARRITO (SIDEBAR) ===
    public $carrito = [];
    public $subtotal = 0.00;
    public $igv = 0.00;
    public $total = 0.00;

    // === NUEVA PROPIEDAD PARA LA COMANDA ===
    public $mostrarModalComanda = false;
    public ?Order $ordenGenerada = null;

    // === NUEVAS PROPIEDADES PARA EL CONTROL DE CAMBIOS ===
    public $hayCambios = false; // Bandera para saber si el botón debe decir "Actualizar"
    public $itemsEliminados = []; // Para guardar IDs de la BD que debemos borrar
    public $stocksProductos = [];
    public $cantidadesOriginalesPorVariante = [];


    public function mount(int $mesa, ?int $pedido = null)
    {
        $this->mesa = $mesa;
        $this->pedido = $pedido;
        // === DETECTAR SI VENIMOS DE CREAR UNA ORDEN ===
        if (session()->has('orden_creada_id')) {
            $idOrden = session('orden_creada_id');
            $this->ordenGenerada = Order::with(['details', 'table', 'user'])->find($idOrden);

            if ($this->ordenGenerada) {
                $this->mostrarModalComanda = true; // ¡Esto abre el modal automáticamente!
            }
        }
        // 2. SI HAY UN PEDIDO EN LA URL -> RECONSTRUIR EL CARRITO
        if ($this->pedido) {
            $ordenExistente = Order::with(['details'])->find($this->pedido);

            if ($ordenExistente) {
                // Recuperamos totales
                $this->subtotal = $ordenExistente->subtotal;
                $this->igv = $ordenExistente->igv;
                $this->total = $ordenExistente->total;

                $this->carrito = $ordenExistente->details->map(function ($detalle) {

                    // AQUI GUARDAMOS LA CANTIDAD ORIGINAL EN MEMORIA
                    $this->cantidadesOriginales[$detalle->id] = $detalle->cantidad;

                    return [
                        'item_id'     => $detalle->id,
                        'product_id'  => $detalle->product_id,
                        'variant_id'  => $detalle->variant_id,
                        'name'        => $detalle->product_name,
                        'price'       => $detalle->price,
                        'quantity'    => $detalle->cantidad,
                        'total'       => $detalle->subTotal,
                        'is_cortesia' => (bool) $detalle->cortesia,
                        'notes'       => $detalle->notes,
                        'image'       => $detalle->product ? $detalle->product->image_path : null,
                        'guardado'    => true,
                    ];
                })->toArray();
            }
        }

        $this->hayCambios = false;
        $this->itemsEliminados = [];
    }

    // =========================================================
    // NUEVO: PROCESAR LA ORDEN (ORDENAR)
    // =========================================================
    public function procesarOrden()
    {
        // 1. Validaciones
        if (empty($this->carrito)) {
            \Filament\Notifications\Notification::make()->title('El carrito está vacío')->warning()->send();
            return;
        }

        // 2. Iniciar Transacción (Todo o nada)
        try {
            DB::beginTransaction();

            // A) CREAR CABECERA (ORDER)
            $order = Order::create([
                // restaurant_id se llena solo por el boot del modelo
                'table_id'      => $this->mesa,
                'code'          => 'ORD-' . strtoupper(Str::random(8)), // Genera un código único
                'status'        => 'pendiente', // O el estado inicial de tu Enum
                'subtotal'      => $this->subtotal,
                'igv'           => $this->igv,
                'total'         => $this->total,
                'fecha_pedido'  => now(),
                'user_id'       => Auth::id(),
            ]);

            // B) CREAR DETALLES (ORDER DETAILS)
            foreach ($this->carrito as $item) {
                OrderDetail::create([
                    'order_id'      => $order->id,
                    // restaurant_id se llena solo
                    'product_id'    => $item['product_id'],
                    'variant_id'    => $item['variant_id'],
                    'product_name'  => $item['name'],
                    'price'         => $item['price'],
                    'cantidad'      => $item['quantity'],
                    'subTotal'      => $item['total'], // Ojo: Tu modelo usa 'subTotal' (camelCase)
                    'cortesia'      => $item['is_cortesia'] ? 1 : 0,
                    'status'        => 'pendiente', // Estado inicial del item cocina
                    'notes'         => $item['notes'],
                    'fecha_envio_cocina' => now(),
                ]);

                $this->gestionarStock($item['variant_id'], $item['quantity'], 'restar');
                // OPCIONAL: AQUÍ DEBERÍAS DESCONTAR EL STOCK (SI APLICA)
                // $this->descontarStock($item); 
            }

            DB::commit();

            // 3. Limpiar Carrito Local
            $this->carrito = [];
            $this->calcularTotales();

            // 4. REDIRECCIÓN CON FLASH DATA
            // Redirigimos a la ruta que incluye el ID del pedido
            // Y mandamos una "flag" para que el mount() abra el modal
            return redirect()
                ->to("/restaurants/restaurant-central/orden-mesa/{$this->mesa}/{$order->id}")
                ->with('orden_creada_id', $order->id);
        } catch (\Exception $e) {
            DB::rollBack();
            \Filament\Notifications\Notification::make()
                ->title('Error al procesar orden')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cerrarModalComanda()
    {
        $this->mostrarModalComanda = false;
    }

    /**
     * Gestiona el stock (Resta o Suma) recorriendo almacenes en orden.
     * * @param int $variantId
     * @param float $cantidad
     * @param string $operacion 'restar' (venta) | 'sumar' (devolución/cancelación)
     */
    private function gestionarStock($variantId, $cantidad, $operacion = 'restar')
    {
        // 1. Buscamos la variante con sus stocks ordenados (ej: por ID o prioridad)
        $variant = Variant::with(['stocks' => function ($q) {
            $q->orderBy('id', 'asc'); // Prioridad al primer almacén creado
        }])->find($variantId);

        if (!$variant) return;

        // Si el producto no controla stock, no hacemos nada
        $product = $variant->product; // Asumiendo relación
        if ($product && $product->control_stock == 0) return;

        $pendiente = $cantidad;

        foreach ($variant->stocks as $stock) {
            if ($pendiente <= 0) break;

            if ($operacion === 'restar') {
                // LÓGICA DE DESCUENTO (VENTA)

                // Cuánto hay en este almacén?
                $disponible = $stock->stock_reserva;

                if ($disponible >= $pendiente) {
                    // Caso A: Este almacén tiene suficiente para cubrir todo
                    $stock->decrement('stock_reserva', $pendiente);
                    $pendiente = 0;
                } else {
                    // Caso B: Este almacén tiene algo, pero no todo. Tomamos lo que hay.
                    // (Solo si hay algo positivo, para no dejar negativos innecesarios si ya estaba en 0)
                    if ($disponible > 0) {
                        $stock->decrement('stock_reserva', $disponible);
                        $pendiente -= $disponible;
                    }
                    // Si disponible es 0 o menos, pasamos al siguiente almacén sin restar nada
                }
            } else {
                // LÓGICA DE DEVOLUCIÓN (CANCELACIÓN)
                // Simplemente devolvemos al primer almacén (o distribuyes si prefieres)
                // Aquí lo devuelvo todo al primero que encuentro y rompo el ciclo
                $stock->increment('stock_reserva', $pendiente);
                $pendiente = 0;
            }
        }

        // EDGE CASE: Si después de recorrer todos los almacenes aún falta restar (venta sin stock permitido)
        // Descontamos el remanente del último almacén (quedará negativo)
        if ($operacion === 'restar' && $pendiente > 0 && $variant->stocks->isNotEmpty()) {
            $variant->stocks->last()->decrement('stock_reserva', $pendiente);
        }
    }

    // =========================================================
    // 1. ABRIR MODAL Y PREPARAR PRODUCTO
    // =========================================================
    public function agregarProducto(int $productoId)
    {
        // 1. Cargamos el producto con sus relaciones necesarias
        // Agregamos 'variants.stocks' para tener el inventario listo sin consultas extra
        $producto = Product::with([
            'attributes',
            'variants.values',
            'variants.stocks'
        ])->find($productoId);

        $this->productoSeleccionado = $producto;

        // 2. Reseteamos variables del modal (Estado Limpio)
        $this->esCortesia = false;
        $this->notaPedido = '';
        $this->selectedAttributes = [];
        $this->variantSeleccionadaId = null;

        // Precio base inicial (luego se le sumarán los extras si hay atributos)
        $this->precioCalculado = $producto->price;

        // Stock inicial en 0 visualmente hasta confirmar variante
        $this->stockActualVariante = 0;
        $this->stockReservaVariante = 0;

        // =========================================================
        // CASO A: PRODUCTO SIMPLE (Sin atributos)
        // =========================================================
        if ($producto->attributes->isEmpty()) {
            $varianteUnica = $producto->variants->first();

            if ($varianteUnica) {
                $this->variantSeleccionadaId = $varianteUnica->id;

                // Calcular stock directamente
                $this->stockActualVariante = $varianteUnica->stocks->sum('stock_real');
                $this->stockReservaVariante = $varianteUnica->stocks->sum('stock_reserva');
            }
        }
        // =========================================================
        // CASO B: PRODUCTO CON ATRIBUTOS (Calculamos Precios JSON)
        // =========================================================
        else {
            // 1. Pre-seleccionar la primera opción de cada atributo por defecto
            foreach ($producto->attributes as $attr) {
                // Decodificamos el JSON de la tabla pivote de forma segura
                $rawValues = $attr->pivot->values ?? [];
                $values = is_string($rawValues) ? json_decode($rawValues, true) : $rawValues;

                // Si hay valores configurados, tomamos el primero
                if (is_array($values) && count($values) > 0) {
                    $primerValorId = $values[0]['id'];

                    // Solo llenamos el array, NO calculamos todavía para ahorrar recursos
                    $this->selectedAttributes[$attr->id] = $primerValorId;
                }
            }

            // 2. Una vez seleccionados todos los defaults, calculamos PRECIO y STOCK una sola vez
            // Esta función (que refactorizamos antes) leerá los 'extra' del JSON y buscará la variante de stock
            $this->buscarVarianteCoincidente();
        }

        // Opcional: Si usas un modal de Filament que se abre con dispatch
        // $this->dispatch('open-modal', id: 'modal-producto');
    }

    // =========================================================
    // NUEVA FUNCIÓN: ACTUALIZAR ORDEN
    // =========================================================
    public function actualizarOrden()
    {
        if (!$this->pedido) return;

        try {
            // =========================================================
            // PASO 1: CALCULAR DIFERENCIAS EXACTAS (ESTILO ODOO)
            // =========================================================

            $diffParaCocina = [
                'nuevos' => [],
                'cancelados' => []
            ];

            // A) REVISAR EL CARRITO ACTUAL (Para nuevos, aumentos y reducciones)
            foreach ($this->carrito as $item) {

                // CASO 1: ES NUEVO (No está guardado en BD)
                if (!isset($item['guardado']) || !$item['guardado']) {
                    $diffParaCocina['nuevos'][] = [
                        'cant' => $item['quantity'],
                        'nombre' => $item['name'],
                        'nota' => $item['notes']
                    ];
                }
                // CASO 2: YA EXISTÍA (Comparamos con el original)
                else {
                    $idDetalle = $item['item_id'];
                    $cantidadOriginal = $this->cantidadesOriginales[$idDetalle] ?? 0;
                    $cantidadActual = $item['quantity'];

                    if ($cantidadActual > $cantidadOriginal) {
                        // AUMENTO: Imprimir solo la diferencia positiva
                        $diferencia = $cantidadActual - $cantidadOriginal;
                        $diffParaCocina['nuevos'][] = [
                            'cant' => $diferencia,
                            'nombre' => $item['name'], // No hace falta poner "(ADICIONAL)", cocina solo ve +1
                            'nota' => $item['notes']
                        ];
                    } elseif ($cantidadActual < $cantidadOriginal) {
                        // REDUCCIÓN: Imprimir la diferencia negativa como cancelado
                        $diferencia = $cantidadOriginal - $cantidadActual;
                        $diffParaCocina['cancelados'][] = [
                            'cant' => $diferencia,
                            'nombre' => $item['name'],
                            'nota' => $item['notes']
                        ];
                    }
                }
            }

            // B) REVISAR LOS ELIMINADOS TOTALES (Botón de borrar)
            if (!empty($this->itemsEliminados)) {
                // Buscamos info en BD antes de borrar para saber qué nombre imprimir
                $itemsABorrar = OrderDetail::whereIn('id', $this->itemsEliminados)->get();
                foreach ($itemsABorrar as $item) {
                    $diffParaCocina['cancelados'][] = [
                        'cant' => $item->cantidad, // Se cancela todo lo que había
                        'nombre' => $item->product_name,
                        'nota' => $item->notes
                    ];
                }
            }

            // =========================================================
            // PASO 2: GUARDAR EN CACHE
            // =========================================================
            // Solo imprimimos si realmente hubo cambios
            if (!empty($diffParaCocina['nuevos']) || !empty($diffParaCocina['cancelados'])) {
                $jobId = 'print_' . $this->pedido . '_' . time();
                Cache::put($jobId, $diffParaCocina, now()->addMinutes(5));
                session()->flash('print_job_id', $jobId); // Flash para el modal
            }

            // =========================================================
            // PASO 3: TRANSACCIÓN DE BASE DE DATOS (UPDATE REAL)
            // =========================================================
            DB::beginTransaction();

            // 1. Cabecera
            $order = Order::find($this->pedido);
            $order->update([
                'subtotal' => $this->subtotal,
                'igv'      => $this->igv,
                'total'    => $this->total,
            ]);

            // 2. Ítems (Create / Update)
            foreach ($this->carrito as $item) {
                // Nuevo
                if (!isset($item['guardado']) || !$item['guardado']) {
                    OrderDetail::create([
                        'order_id'      => $order->id,
                        'product_id'    => $item['product_id'],
                        'variant_id'    => $item['variant_id'],
                        'product_name'  => $item['name'],
                        'price'         => $item['price'],
                        'cantidad'      => $item['quantity'],
                        'subTotal'      => $item['total'],
                        'cortesia'      => $item['is_cortesia'] ? 1 : 0,
                        'status'        => 'pendiente',
                        'notes'         => $item['notes'],
                        'fecha_envio_cocina' => now(),
                        // 'restaurant_id' se llena automático por tu scope
                    ]);
                    $this->gestionarStock($item['variant_id'], $item['quantity'], 'restar');
                }
                // Actualizar
                else {
                    $detalle = OrderDetail::find($item['item_id']);

                    if ($detalle) {
                        $cantidadAnterior = $detalle->cantidad;
                        $cantidadNueva = $item['quantity'];

                        // Solo tocamos stock si la cantidad cambió
                        if ($cantidadNueva != $cantidadAnterior) {

                            $detalle->update([
                                'cantidad' => $cantidadNueva,
                                'subTotal' => $item['total'],
                            ]);

                            // === STOCK: CALCULAR DIFERENCIA ===
                            if ($cantidadNueva > $cantidadAnterior) {
                                // Aumentó el pedido -> Restar Stock (Diferencia)
                                $diff = $cantidadNueva - $cantidadAnterior;
                                $this->gestionarStock($item['variant_id'], $diff, 'restar');
                            } else {
                                // Disminuyó el pedido -> Devolver Stock (Diferencia)
                                $diff = $cantidadAnterior - $cantidadNueva;
                                $this->gestionarStock($item['variant_id'], $diff, 'sumar');
                            }
                        }
                    }
                }
            }

            // 3. Procesar Eliminados
            if (!empty($this->itemsEliminados)) {
                // Recuperamos los datos ANTES de borrar para saber qué variant_id y cantidad devolver
                $itemsABorrar = OrderDetail::whereIn('id', $this->itemsEliminados)->get();

                foreach ($itemsABorrar as $borrado) {
                    // === STOCK: DEVOLVER TODO ===
                    $this->gestionarStock($borrado->variant_id, $borrado->cantidad, 'sumar');
                }

                // Ahora sí borramos
                OrderDetail::whereIn('id', $this->itemsEliminados)->delete();
            }

            DB::commit();

            // =========================================================
            // PASO 4: RESET Y RECARGA
            // =========================================================
            $this->hayCambios = false;
            $this->itemsEliminados = [];

            // Recargamos orden para el ID
            $this->ordenGenerada = $order->refresh();

            // Abrimos modal solo si hubo algo que imprimir
            if (session()->has('print_job_id')) {
                $this->mostrarModalComanda = true;
            }

            \Filament\Notifications\Notification::make()->title('Orden actualizada')->success()->send();

            // IMPORTANTE: Volver a ejecutar mount para resetear $cantidadesOriginales a los nuevos valores
            $this->mount($this->mesa, $this->pedido);
        } catch (\Exception $e) {
            DB::rollBack();
            \Filament\Notifications\Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // =========================================================
    // 2. LÓGICA INTELIGENTE DE VARIANTES
    // =========================================================
    public function seleccionarAtributo($attributeId, $valueId)
    {
        // Guardamos la selección del usuario
        $this->selectedAttributes[$attributeId] = $valueId;

        // Buscamos si existe una variante que coincida con TODAS las selecciones
        $this->buscarVarianteCoincidente();
    }

    public function buscarVarianteCoincidente()
    {
        // -----------------------------------------------------
        // PASO 1: CALCULO DE PRECIO (Independiente de la variante)
        // -----------------------------------------------------
        $precioBase = $this->productoSeleccionado->price;
        $extrasAcumulados = 0;

        // Recorremos los atributos que el usuario ya seleccionó
        foreach ($this->selectedAttributes as $attrId => $valIdSeleccionado) {

            // Buscamos el atributo en la relación cargada
            $atributo = $this->productoSeleccionado->attributes->find($attrId);

            if ($atributo) {
                // Decodificamos el JSON de la tabla pivote
                // Estructura esperada: [{"id":1, "name":"Grande", "extra": 5.00}, ...]
                $opciones = is_string($atributo->pivot->values)
                    ? json_decode($atributo->pivot->values, true)
                    : $atributo->pivot->values;

                // Buscamos la opción seleccionada dentro del JSON
                $opcion = collect($opciones)->firstWhere('id', $valIdSeleccionado);

                // Si tiene precio extra, lo sumamos
                if ($opcion) {
                    $extrasAcumulados += ($opcion['extra'] ?? 0);
                }
            }
        }

        // Asignamos el precio calculado INMEDIATAMENTE
        $this->precioCalculado = $precioBase + $extrasAcumulados;


        // -----------------------------------------------------
        // PASO 2: BUSQUEDA DE VARIANTE (Solo para Stock)
        // -----------------------------------------------------
        if (!$this->productoSeleccionado || $this->productoSeleccionado->variants->isEmpty()) {
            return;
        }

        $matchVariant = null;

        foreach ($this->productoSeleccionado->variants as $variant) {
            $variantValueIds = $variant->values->pluck('id')->toArray();
            $seleccionados = array_values($this->selectedAttributes);

            // Verificamos coincidencia exacta de IDs
            $coincidencias = array_intersect($seleccionados, $variantValueIds);

            if (count($coincidencias) === count($this->productoSeleccionado->attributes)) {
                $matchVariant = $variant;
                break;
            }
        }

        if ($matchVariant) {
            $this->variantSeleccionadaId = $matchVariant->id;

            // YA NO tocamos el precio aquí. El precio viene de los atributos.

            // Stock
            $this->stockActualVariante = $matchVariant->stocks->sum('stock_real');
            $this->stockReservaVariante = $matchVariant->stocks->sum('stock_reserva');
        } else {
            $this->variantSeleccionadaId = null;
            // Si no hay variante (combinación inválida), reseteamos stock
            $this->stockActualVariante = 0;
            $this->stockReservaVariante = 0;
        }
    }

    // =========================================================
    // 3. CONFIRMAR Y AGREGAR AL CARRITO
    // =========================================================
    public function confirmarAgregado()
    {
        // 1. Validaciones básicas
        if ($this->productoSeleccionado->variants->count() > 0 && !$this->variantSeleccionadaId) {
            \Filament\Notifications\Notification::make()->title('Debes seleccionar una opción válida')->warning()->send();
            return;
        }

        // 2. Preparar datos para comparar
        $prodId = $this->productoSeleccionado->id;
        $varId = $this->variantSeleccionadaId;
        $esCortesia = $this->esCortesia;
        $nuevaNota = trim($this->notaPedido); // La nota que acaba de escribir el usuario

        // 3. BUSCAR SI YA EXISTE EN EL CARRITO
        $indiceExistente = null;

        foreach ($this->carrito as $index => $item) {
            // Solo nos importa si es el mismo producto, misma variante y mismo estado de cortesía.
            if (
                $item['product_id'] == $prodId &&
                $item['variant_id'] == $varId &&
                $item['is_cortesia'] == $esCortesia
            ) {
                $indiceExistente = $index;
                break;
            }
        }

        // 4. LÓGICA DE AGREGADO O ACTUALIZACIÓN
        if ($indiceExistente !== null) {
            // === CASO A: YA EXISTE -> INCREMENTAMOS CANTIDAD Y UNIMOS NOTAS ===

            // a) Validación de Stock
            if ($this->productoSeleccionado->control_stock == 1 && $this->productoSeleccionado->venta_sin_stock == 0) {
                $stockRestante = $this->stockReservaVariante;

                // Calculamos cuánto tendríamos en total considerando lo que ya está en el carrito
                // Nota: Si quieres ser más preciso, deberías sumar todos los items del carrito con el mismo variant_id, no solo este índice.
                // Pero para este caso simple:
                $cantidadNueva = $this->carrito[$indiceExistente]['quantity'] + 1;

                if ($cantidadNueva > $stockRestante) {
                    \Filament\Notifications\Notification::make()->title('No hay suficiente stock para agregar más')->warning()->send();
                    return;
                }
            }

            // b) Actualizamos Cantidad y Precio Total
            $this->carrito[$indiceExistente]['quantity']++;
            $this->carrito[$indiceExistente]['total'] = $this->carrito[$indiceExistente]['quantity'] * $this->carrito[$indiceExistente]['price'];

            // c) CONCATENAR NOTAS
            if (!empty($nuevaNota)) {
                $notaActual = $this->carrito[$indiceExistente]['notes'];

                if (!empty($notaActual)) {
                    // Si ya tenía nota, le agregamos la nueva separada por coma
                    $this->carrito[$indiceExistente]['notes'] = $notaActual . ', ' . $nuevaNota;
                } else {
                    // Si estaba vacía, simplemente ponemos la nueva
                    $this->carrito[$indiceExistente]['notes'] = $nuevaNota;
                }
            }
        } else {
            // === CASO B: ES NUEVO -> CREAMOS LA LÍNEA ===

            $variante = Variant::with('values')->find($varId);
            $nombreItem = $this->productoSeleccionado->name;
            if ($variante && $this->productoSeleccionado->attributes->count() > 0) {
                $nombreVariante = $variante->values->pluck('name')->join(' / ');
                $nombreItem .= " ($nombreVariante)";
            }

            $precioUnitario = $esCortesia ? 0 : $this->precioCalculado;

            // Generamos ID único temporal para el frontend
            $itemId = md5($prodId . $varId . $esCortesia . time());

            $this->carrito[] = [
                'item_id'     => $itemId,
                'product_id'  => $prodId,
                'variant_id'  => $varId,
                'name'        => $nombreItem,
                'price'       => $precioUnitario,
                'quantity'    => 1,
                'total'       => $precioUnitario,
                'is_cortesia' => $esCortesia,
                'notes'       => $nuevaNota, // Usamos la nota inicial
                'image'       => $this->productoSeleccionado->image_path,
                'guardado'    => false // Marcamos explícitamente que es nuevo
            ];
        }

        // =========================================================
        // ¡CAMBIO CRÍTICO AQUÍ!
        // =========================================================
        $this->hayCambios = true; // <--- Activamos la bandera para mostrar el botón "ACTUALIZAR"

        // 5. Finalizar
        $this->calcularTotales();
        $this->cerrarModal();

        \Filament\Notifications\Notification::make()->title('Producto agregado')->success()->send();
    }

    public function cerrarModal()
    {
        $this->productoSeleccionado = null;
    }

    // =========================================================
    // 4. GESTIÓN DEL CARRITO (Sumar, Restar, Eliminar)
    // =========================================================
    // =========================================================
    // 4. GESTIÓN DEL CARRITO (Sumar, Restar, Eliminar)
    // =========================================================
    public function incrementarCantidad($index)
    {
        $item = $this->carrito[$index];
        $productoId = $item['product_id'];
        $variantId = $item['variant_id'];

        // 1. Buscamos el producto para ver sus reglas
        // Usamos find() ligero, no necesitamos cargar todo
        $producto = Product::find($productoId);

        // 2. VALIDACIÓN DE STOCK (El "Policía")
        if ($producto->control_stock == 1 && $producto->venta_sin_stock == 0) {

            // a) Buscamos el stock real (reserva) de la variante en BD
            // Nota: Es importante buscarlo fresco de la BD por si alguien más vendió
            $variante = Variant::with('stocks')->find($variantId);
            $stockMaximo = $variante ? $variante->stocks->sum('stock_reserva') : 0;

            // b) Contamos cuántos tenemos YA en el carrito (sumando todas las líneas por si se repite)
            $cantidadEnCarrito = collect($this->carrito)
                ->where('variant_id', $variantId)
                ->sum('quantity');

            // c) Verificamos si al sumar 1 nos pasamos
            if (($cantidadEnCarrito + 1) > $stockMaximo) {
                \Filament\Notifications\Notification::make()
                    ->title('Stock insuficiente')
                    ->body("Solo quedan {$stockMaximo} unidades disponibles.")
                    ->warning()
                    ->send();
                return; // ¡ALTO! No sumamos nada.
            }
        }

        // 3. Si pasó la validación (o no controla stock), procedemos
        $this->carrito[$index]['quantity']++;
        $this->carrito[$index]['total'] = $this->carrito[$index]['quantity'] * $this->carrito[$index]['price'];
        $this->hayCambios = true;
        $this->calcularTotales();
    }

    public function decrementarCantidad($index)
    {
        if ($this->carrito[$index]['quantity'] > 1) {
            $this->carrito[$index]['quantity']--;
            $this->carrito[$index]['total'] = $this->carrito[$index]['quantity'] * $this->carrito[$index]['price'];
        } else {
            // Si baja de 1, lo eliminamos
            $this->eliminarItem($index);
            return;
        }
        $this->hayCambios = true;
        $this->calcularTotales();
    }

    public function eliminarItem($index)
    {
        $item = $this->carrito[$index];

        // SI EL ITEM YA ESTABA GUARDADO EN BD (tiene flag 'guardado'), 
        // LO AGREGAMOS A LA LISTA DE ELIMINACIÓN
        if (isset($item['guardado']) && $item['guardado']) {
            $this->itemsEliminados[] = $item['item_id']; // item_id tiene el ID de la BD en este caso
        }

        unset($this->carrito[$index]);
        $this->carrito = array_values($this->carrito);

        $this->hayCambios = true; // <--- SE ACTIVA LA BANDERA
        $this->calcularTotales();
    }

    public function calcularTotales()
    {
        $acumulado = 0;
        foreach ($this->carrito as $item) {
            $acumulado += $item['total'];
        }

        // Lógica de Impuestos (Perú: IGV incluido en el precio)
        $this->total = $acumulado;
        $this->subtotal = $this->total / 1.18;
        $this->igv = $this->total - $this->subtotal;
    }

    // =========================================================
    // 5. CONFIGURACIÓN DE FILAMENT
    // =========================================================
    public function getViewData(): array
    {
        $categorias = OrdenService::obtenerCategorias();
        $productos = OrdenService::obtenerProductos(
            $this->categoriaSeleccionada,
            $this->search
        );

        return [
            'tenant'     => Filament::getTenant(),
            'mesa'       => $this->mesa,
            'pedido'     => $this->pedido,
            'categorias' => $categorias,
            'productos'  => $productos,
        ];
    }

    public static function getSlug(): string
    {
        return 'orden-mesa/{mesa}/{pedido?}';
    }

    public function getHeading(): string
    {
        return '';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }
}
