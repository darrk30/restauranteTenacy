<?php

namespace App\Filament\Restaurants\Pages;

use App\Services\OrdenService;
use App\Models\Product;
use App\Models\Variant; // Importante para la lógica del carrito
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

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

    public function mount(int $mesa, ?int $pedido = null)
    {
        $this->mesa = $mesa;
        $this->pedido = $pedido;
    }


    // =========================================================
    // 1. ABRIR MODAL Y PREPARAR PRODUCTO
    // =========================================================
    public function agregarProducto(int $productoId)
    {
        // Cargamos producto con atributos y valores de variantes
        $producto = Product::with([
            'attributes',
            'variants.values'
        ])->find($productoId);

        $this->productoSeleccionado = $producto;

        // Reseteamos variables del modal
        $this->esCortesia = false;
        $this->notaPedido = '';
        $this->selectedAttributes = [];
        $this->variantSeleccionadaId = null;
        $this->precioCalculado = $producto->price;

        // CASO 1: PRODUCTO SIMPLE
        if ($producto->attributes->isEmpty()) {
            $varianteUnica = $producto->variants->first();
            if ($varianteUnica) {
                $this->variantSeleccionadaId = $varianteUnica->id;

                // === NUEVO: Calcular stock inicial ===
                $this->stockActualVariante = $varianteUnica->stocks->sum('stock_real');
                $this->stockReservaVariante = $varianteUnica->stocks->sum('stock_reserva');
            }
        }
        // CASO B: Producto Variable (Con atributos, ej: Ceviche)
        else {
            foreach ($producto->attributes as $attr) {
                // Decodificamos los valores del pivote JSON
                $rawValues = $attr->pivot->values ?? [];

                // Aseguramos que sea un array asociativo
                $values = is_string($rawValues) ? json_decode($rawValues, true) : $rawValues;

                // Pre-seleccionamos la primera opción por defecto
                if (is_array($values) && count($values) > 0) {
                    // FIX: Accedemos como array ['id'] para evitar el error "Attempt to read property id on array"
                    $primerValorId = $values[0]['id'];
                    $this->seleccionarAtributo($attr->id, $primerValorId);
                }
            }
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
        // ... (inicio de la función igual que antes) ...
        if (!$this->productoSeleccionado || $this->productoSeleccionado->variants->isEmpty()) {
            return;
        }

        $matchVariant = null;

        // ... (tu bucle foreach para encontrar $matchVariant igual que antes) ...
        foreach ($this->productoSeleccionado->variants as $variant) {
            $variantValueIds = $variant->values->pluck('id')->toArray();
            $seleccionados = array_values($this->selectedAttributes);
            $coincidencias = array_intersect($seleccionados, $variantValueIds);

            if (count($coincidencias) === count($this->productoSeleccionado->attributes)) {
                $matchVariant = $variant;
                break;
            }
        }

        if ($matchVariant) {
            $this->variantSeleccionadaId = $matchVariant->id;
            $this->precioCalculado = $this->productoSeleccionado->price + $matchVariant->extra_price;

            // === NUEVO: CALCULAR STOCK DE LA VARIANTE ENCONTRADA ===
            // Sumamos 'stock_real' y 'stock_reserva' de todos los almacenes de esta variante
            $this->stockActualVariante = $matchVariant->stocks->sum('stock_real');
            $this->stockReservaVariante = $matchVariant->stocks->sum('stock_reserva');
        } else {
            $this->variantSeleccionadaId = null;
            $this->precioCalculado = $this->productoSeleccionado->price;

            // Si no hay variante válida, reseteamos stock a 0
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
            // CAMBIO CLAVE: Ya NO comparamos las notas aquí.
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

                // Calculamos cuánto tendríamos en total
                $cantidadNueva = $this->carrito[$indiceExistente]['quantity'] + 1;

                if ($cantidadNueva > $stockRestante) {
                    \Filament\Notifications\Notification::make()->title('No hay suficiente stock para agregar más')->warning()->send();
                    return;
                }
            }

            // b) Actualizamos Cantidad y Precio Total
            $this->carrito[$indiceExistente]['quantity']++;
            $this->carrito[$indiceExistente]['total'] = $this->carrito[$indiceExistente]['quantity'] * $this->carrito[$indiceExistente]['price'];

            // c) CONCATENAR NOTAS (CAMBIO SOLICITADO)
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

            // Generamos ID único
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
                'image'       => $this->productoSeleccionado->image_path
            ];
        }

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
        $this->calcularTotales();
    }

    public function eliminarItem($index)
    {
        unset($this->carrito[$index]);
        $this->carrito = array_values($this->carrito); // Reindexar claves del array
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
