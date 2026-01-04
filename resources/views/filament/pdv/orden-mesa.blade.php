@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ordenmesa.css') }}">
@endpush
<x-filament-panels::page>

    <div class="pos-layout">

        {{-- ========================================== --}}
        {{-- COLUMNA IZQUIERDA: CATÁLOGO --}}
        {{-- ========================================== --}}
        <div class="pos-main-content">

            {{-- 1. BUSCADOR --}}
            <div class="search-container">
                {{-- Icono Lupa --}}
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>

                {{-- Input (Nota: placeholder es obligatorio para el truco CSS) --}}
                <input type="text" placeholder="Buscar producto..." class="search-input"
                    wire:model.live.debounce.300ms="search">

                {{-- Botón X (Limpiar) --}}
                <button class="clear-btn" {{-- Al hacer clic, borra la variable 'search' de Livewire --}} wire:click="$set('search', '')"
                    onclick="this.previousElementSibling.value = ''; this.previousElementSibling.focus();"
                    {{-- Limpieza visual inmediata --}}>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- 2. CATEGORÍAS --}}
            <div class="pos-categories">
                <button wire:click="$set('categoriaSeleccionada', null)"
                    class="btn-category {{ is_null($categoriaSeleccionada) ? 'active' : '' }}">
                    Todas
                </button>

                @foreach ($categorias as $categoria)
                    <button wire:key="cat-{{ $categoria->id }}"
                        wire:click="$set('categoriaSeleccionada', {{ $categoria->id }})"
                        class="btn-category {{ $categoriaSeleccionada === $categoria->id ? 'active' : '' }}">
                        {{ $categoria->name }}
                    </button>
                @endforeach
            </div>

            {{-- 3. PRODUCTOS --}}
            <div class="pos-products-area" wire:loading.class="opacity-50 pointer-events-none"
                wire:target="search, categoriaSeleccionada">
                <div class="products-grid">
                    @forelse ($productos as $product)
                        <div wire:key="prod-{{ $product->id }}" class="product-card">

                            @php
                                // 1. CALCULAR STOCK TOTAL DE RESERVA (Desde la BD)
                                $stockReservaTotal = 0;

                                if ($product->control_stock == 1) {
                                    // Sumamos solo la columna 'stock_reserva'
                                    foreach ($product->variants as $v) {
                                        $stockReservaTotal += $v->stocks->sum('stock_reserva');
                                    }
                                }

                                // 2. CALCULAR CUÁNTOS YA TENEMOS EN EL CARRITO DE ESTE PRODUCTO
                                // Buscamos en el array $carrito cuántas veces aparece este product_id
                                $cantidadEnCarrito = collect($carrito)
                                    ->where('product_id', $product->id)
                                    ->sum('quantity');

                                // 3. STOCK VISIBLE (REAL TIME)
                                // Lo que el usuario ve es: Lo que hay en almacén MENOS lo que ya puso en la bandeja
                                $stockVisible = $stockReservaTotal - $cantidadEnCarrito;

                                // 4. ESTADO DE AGOTADO
                                // Está agotado si controla stock, el visible es <= 0 y no permite venta sin stock
                                $estaAgotado =
                                    $product->control_stock == 1 &&
                                    $stockVisible <= 0 &&
                                    $product->venta_sin_stock == 0;
                            @endphp
                            {{-- IMAGEN + STOCK --}}
                            <div class="product-image-container">

                                {{-- Mostrar Badge SOLO si control_stock es 1 --}}
                                @if ($product->control_stock == 1)
                                    <div class="stock-badge flex flex-col items-start leading-tight"
                                        style="padding: 4px 8px; font-size: 0.7rem;">
                                        <div class="flex items-center gap-1">
                                            {{-- El color cambia dinámicamente según lo que queda --}}
                                            <span
                                                class="stock-dot {{ $stockVisible > 0 ? 'bg-green-500' : 'bg-red-500' }}"></span>

                                            {{-- AQUI MOSTRAMOS EL STOCK RESTANTE --}}
                                            <span>{{ $stockVisible }} Unidades</span>
                                        </div>
                                    </div>
                                @endif

                                @if ($product->image_path)
                                    <img src="{{ asset('storage/' . $product->image_path) }}"
                                        alt="{{ $product->name }}" class="product-img">
                                @else
                                    <div class="flex items-center justify-center h-full text-gray-300 bg-gray-50">
                                        <svg style="width:32px; height:32px" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                    </div>
                                @endif

                                {{-- OVERLAY AGOTADO --}}
                                @if ($estaAgotado)
                                    <div class="agotado-overlay">
                                        <span class="agotado-badge">
                                            AGOTADO
                                        </span>
                                    </div>
                                @endif
                            </div>

                            {{-- INFO + BOTÓN --}}
                            <div class="product-info">
                                <h3 class="product-title">{{ $product->name }}</h3>

                                <div class="attr-container">
                                    @foreach ($product->attributes as $attribute)
                                        @php
                                            // 1. Obtenemos el valor crudo
                                            $rawValues = $attribute->pivot->values;

                                            // 2. Si es texto (JSON), lo convertimos a Array. Si ya es array, lo usamos tal cual.
                                            // El 'true' en json_decode fuerza a que sea un array asociativo ['name' => 'X']
                                            $listaValores = is_string($rawValues)
                                                ? json_decode($rawValues, true)
                                                : $rawValues;
                                        @endphp

                                        {{-- 3. Validamos que no esté vacío y sea iterable --}}
                                        @if (!empty($listaValores) && (is_array($listaValores) || is_object($listaValores)))
                                            <div class="attr-section">
                                                <span class="attr-label">{{ $attribute->name }}:</span>
                                                <div class="attr-values">
                                                    @foreach ($listaValores as $valor)
                                                        {{-- 4. Accedemos como array asociativo --}}
                                                        <span class="attr-tag">
                                                            {{ is_array($valor) ? $valor['name'] : $valor->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                <div class="product-footer">
                                    {{-- Botón Carrito --}}
                                    <button
                                        class="btn-footer-cart {{ $estaAgotado ? 'opacity-50 cursor-not-allowed bg-gray-300 text-gray-500' : '' }}"
                                        {{-- Solo permitir click si hay stock --}}
                                        @if (!$estaAgotado) wire:click="agregarProducto({{ $product->id }})" @endif
                                        @if ($estaAgotado) disabled @endif {{-- 1. Bloquear este botón especifico mientras carga --}}
                                        wire:loading.attr="disabled" wire:target="agregarProducto({{ $product->id }})"
                                        title="{{ $estaAgotado ? 'Sin stock suficiente' : 'Agregar' }}">

                                        {{-- 2. ICONO DEL CARRITO --}}
                                        {{-- Se OCULTA automáticamente cuando se está ejecutando agregarProducto(ID) --}}
                                        <svg wire:loading.remove wire:target="agregarProducto({{ $product->id }})"
                                            xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="9" cy="21" r="1"></circle>
                                            <circle cx="20" cy="21" r="1"></circle>
                                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6">
                                            </path>
                                        </svg>

                                        {{-- 3. SPINNER DE CARGA --}}
                                        {{-- Por defecto está OCULTO (hidden). Livewire le quita 'hidden' SOLO cuando carga este ID --}}
                                        <svg wire:loading.class.remove="hidden"
                                            wire:target="agregarProducto({{ $product->id }})"
                                            class="animate-spin hidden" style="width:18px; height:18px;"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                                stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </button>

                                    <div class="product-price">
                                        <span class="currency-symbol">S/</span>{{ number_format($product->price, 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-500">
                            <p class="font-medium">No se encontraron productos.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- COLUMNA DERECHA: SIDEBAR (TICKET) --}}
        {{-- ========================================== --}}
        <div class="pos-sidebar">
            <div class="cart-header">
                <span>Orden Actual</span>
                <span class="text-sm font-normal text-gray-500">Mesa: {{ $mesa }}</span>
            </div>

            {{-- LISTA DE ITEMS (DINÁMICA) --}}
            <div class="cart-items-container">

                @forelse($carrito as $index => $item)
                    <div class="cart-item" wire:key="cart-item-{{ $item['item_id'] }}">

                        <div class="cart-item-info">
                            <div class="cart-item-title">
                                {{ $item['name'] }}
                                @if ($item['is_cortesia'])
                                    <span class="text-xs text-orange-500 font-bold">(Cortesía)</span>
                                @endif
                            </div>

                            <div class="cart-item-price">
                                {{-- Muestra precio unitario --}}
                                S/ {{ number_format($item['price'], 2) }} u.

                                @if ($item['notes'])
                                    <div class="text-xs text-gray-400 italic mt-1">
                                        Nota: {{ Str::limit($item['notes'], 20) }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="cart-item-actions">
                            {{-- Total de la línea --}}
                            <div class="font-bold">S/ {{ number_format($item['total'], 2) }}</div>

                            {{-- Controles --}}
                            <div class="qty-control">
                                {{-- Botón Menos (Si es 1, se convierte en botón de eliminar) --}}
                                @if ($item['quantity'] == 1)
                                    <button class="btn-qty text-red-500"
                                        wire:click="eliminarItem({{ $index }})">
                                        <svg style="width:14px;height:14px" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                @else
                                    <button class="btn-qty"
                                        wire:click="decrementarCantidad({{ $index }})">-</button>
                                @endif

                                <span class="text-xs font-bold px-2">{{ $item['quantity'] }}</span>

                                <button class="btn-qty"
                                    wire:click="incrementarCantidad({{ $index }})">+</button>
                            </div>
                        </div>

                    </div>
                @empty
                    {{-- ESTADO VACÍO --}}
                    <div class="flex flex-col items-center justify-center h-64 text-gray-400">
                        <svg style="width:48px;height:48px;margin-bottom:10px;opacity:0.5" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        <span class="text-sm">La orden está vacía</span>
                    </div>
                @endforelse

            </div>

            {{-- TOTALES (DINÁMICOS) --}}
            <div class="cart-footer">
                <div class="cart-total-row">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="font-bold">S/ {{ number_format($subtotal, 2) }}</span>
                </div>
                <div class="cart-total-row">
                    <span class="text-gray-500">IGV (18%)</span>
                    <span class="font-bold">S/ {{ number_format($igv, 2) }}</span>
                </div>
                <div class="cart-total-row cart-total-final">
                    <span>Total</span>
                    <span>S/ {{ number_format($total, 2) }}</span>
                </div>

                {{-- Botón Cobrar (Deshabilitado si está vacío) --}}
                <button class="btn-checkout" @if (empty($carrito)) disabled style="opacity:0.5" @endif>
                    COBRAR S/ {{ number_format($total, 2) }}
                </button>
            </div>
        </div>

    </div>
    @if ($productoSeleccionado)
        {{-- Fondo oscuro (Overlay) --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">

            {{-- Click fuera para cerrar --}}
            <div class="absolute inset-0" wire:click="cerrarModal"></div>

            {{-- TU COMPONENTE DE CARTA (Con z-index superior) --}}
            <div class="relative z-10 w-full max-w-md">

                {{-- Llamamos al componente que creamos antes --}}
                <x-cardproduct :product="$productoSeleccionado" :variantId="$variantSeleccionadaId" />

            </div>
        </div>
    @endif
</x-filament-panels::page>

{{-- @push('styles')
    <link rel="stylesheet" href="{{ asset('css/ordenmesa.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ordenmesadarrk.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/ordenmesa.js') }}" defer></script>
@endpush --}}

{{-- <livewire:pedido-mesa :tenant="$tenant" :mesa="$mesa" :pedido="$pedido" /> --}}
