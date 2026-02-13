@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ordenmesa.css') }}">
@endpush

{{-- AGREGAMOS x-data AQUÍ PARA CONTROLAR EL CARRITO MÓVIL EN TODA LA PÁGINA --}}
<x-filament-panels::page x-data="{ mobileCartOpen: false }">
    <div class="pos-layout">
        <div class="pos-main-content">
            {{-- 1. CATEGORÍAS --}}
            <div class="category-scroll-wrapper">
                <button type="button" class="scroll-btn" onclick="scrollCategories('left')"> &#10094; </button>
                <div class="pos-categories" id="categoryList">
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
                <button type="button" class="scroll-btn" onclick="scrollCategories('right')"> &#10095; </button>
            </div>

            {{-- 2. BUSCADOR --}}
            <div class="search-container">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" placeholder="Buscar producto..." class="search-input"
                    wire:model.live.debounce.300ms="search">
                <button class="clear-btn" wire:click="$set('search', '')"
                    onclick="this.previousElementSibling.value = ''; this.previousElementSibling.focus();">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- 3. PRODUCTOS --}}
            <div class="pos-products-area" wire:loading.class="opacity-50 pointer-events-none"
                wire:target="search, categoriaSeleccionada">


                <div class="products-grid">
                    @forelse ($productos as $product)
                        @php
                            // TRUCO: Normalizamos el tipo aquí mismo.
                            // Si es un Enum, sacamos su valor ('Producto'). Si ya es texto, lo usamos directo.
                            $tipoProducto =
                                $product->type instanceof \App\Enums\TipoProducto
                                    ? $product->type->value
                                    : $product->type;
                        @endphp

                        {{-- USAMOS LA VARIABLE NORMALIZADA $tipoProducto PARA EL KEY --}}
                        <div wire:key="item-{{ $tipoProducto }}-{{ $product->id }}" class="product-card group"
                            x-data="{ tooltipOpen: false }" :style="tooltipOpen ? 'z-index: 50;' : ''"
                            @click.outside="tooltipOpen = false">

                            <div class="product-image-container relative">
                                {{-- BADGE: COMBO (Promocion) --}}
                                @if ($tipoProducto === 'Promocion')
                                    <div class="pos-badge">
                                        COMBO
                                    </div>
                                @endif

                                {{-- BADGE: SERVICIO --}}
                                @if ($tipoProducto === 'Servicio')
                                    <div class="pos-badge">
                                        SERVICIO
                                    </div>
                                @endif


                                {{-- IMAGEN --}}
                                @if ($product->image_path)
                                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}"
                                        class="product-img">
                                @else
                                    <div class="flex items-center justify-center h-full text-gray-300">
                                        <svg style="width:32px; height:32px" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                    </div>
                                @endif

                                {{-- OVERLAY AGOTADO --}}
                                @if ($product->esta_agotado)
                                    <div class="agotado-overlay">
                                        <span class="agotado-badge">AGOTADO</span>
                                    </div>
                                @endif

                                {{-- PRECIO --}}
                                <div class="price-tag-overlay">
                                    <span class="text-xs font-medium">S/</span>{{ number_format($product->price, 2) }}
                                </div>
                            </div>

                            {{-- En tu archivo orden-mesa.blade.php --}}

                            <div class="product-info">
                                <h3 class="product-title">{{ $product->name }}</h3>

                                {{-- 1. STOCK DE PRODUCTO FÍSICO --}}
                                @if ($tipoProducto === 'Producto' && $product->control_stock == 1)
                                    <p
                                        class="stock-text {{ $product->stock_visible > 0 ? 'text-gray-500' : 'text-red-500' }}">
                                        Stock: {{ $product->stock_visible }}
                                    </p>
                                @endif

                                {{-- 2. STOCK DE PROMOCIÓN (SOLO SI TIENE LÍMITE) --}}
                                @if ($tipoProducto === 'Promocion' && $product->tiene_limite)
                                    <p
                                        class="stock-text {{ $product->stock_visible > 0 ? 'text-purple-600' : 'text-red-500' }}">
                                        Restantes hoy: {{ $product->stock_visible }}
                                    </p>
                                @endif

                                {{-- 3. TEXTO PARA PROMOCIONES ILIMITADAS (SIN STOCK) --}}
                                @if ($tipoProducto === 'Promocion' && !$product->tiene_limite)
                                    {{-- Aquí puedes poner un texto genérico o dejarlo vacío si prefieres no mostrar nada --}}
                                    <p class="text-[10px] text-purple-500 font-medium italic">
                                        Disponible
                                    </p>
                                @endif

                                {{-- 4. SERVICIOS --}}
                                @if ($tipoProducto === 'Servicio')
                                    <p class="text-[10px] text-blue-500 italic">Servicio</p>
                                @endif
                            </div>

                            <div class="product-footer">
                                <div class="flex items-center gap-2 w-full">

                                    {{-- BOTÓN: AGREGAR PROMOCIÓN --}}
                                    @if ($tipoProducto === 'Promocion')
                                        <button
                                            class="btn-footer-cart flex-1 bg-purple-600 hover:bg-purple-700 text-white"
                                            wire:click="agregarPromocion({{ $product->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="agregarPromocion({{ $product->id }})">

                                            {{-- Icono Loading --}}
                                            <svg wire:loading.class.remove="hidden"
                                                wire:target="agregarPromocion({{ $product->id }})"
                                                class="animate-spin hidden" style="width:18px; height:18px;"
                                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>

                                            {{-- Icono Combo --}}
                                            <svg wire:loading.remove
                                                wire:target="agregarPromocion({{ $product->id }})"
                                                xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 5v14M5 12h14" />
                                            </svg>
                                        </button>
                                    @else
                                        {{-- BOTÓN: AGREGAR PRODUCTO / SERVICIO --}}
                                        <button
                                            class="btn-footer-cart flex-1 {{ $product->esta_agotado ? 'opacity-50 cursor-not-allowed bg-gray-300 text-gray-500' : '' }}"
                                            @if (!$product->esta_agotado) wire:click="agregarProducto({{ $product->id }})" @endif
                                            @if ($product->esta_agotado) disabled @endif
                                            wire:loading.attr="disabled"
                                            wire:target="agregarProducto({{ $product->id }})">

                                            <svg wire:loading.remove
                                                wire:target="agregarProducto({{ $product->id }})"
                                                xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="9" cy="21" r="1"></circle>
                                                <circle cx="20" cy="21" r="1"></circle>
                                                <path
                                                    d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6">
                                                </path>
                                            </svg>
                                            <svg wire:loading.class.remove="hidden"
                                                wire:target="agregarProducto({{ $product->id }})"
                                                class="animate-spin hidden" style="width:18px; height:18px;"
                                                xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                        </button>
                                    @endif

                                    {{-- BOTÓN INFO (TOOLTIP) --}}
                                    @if ($tipoProducto === 'Producto' || $tipoProducto === 'Promocion')
                                        <div class="relative">
                                            <button class="btn-footer-info" type="button"
                                                @click="tooltipOpen = !tooltipOpen">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                    class="w-5 h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.287.696.345 1.084m-.345-1.084c-.18-.324-.287-.696-.345-1.084m0 2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                                                </svg>
                                            </button>

                                            <div class="attr-tooltip" :class="{ 'show-tooltip': tooltipOpen }">
                                                <div class="attr-arrow"></div>
                                                <h4
                                                    class="text-[10px] font-bold text-gray-400 uppercase mb-2 border-b border-gray-700 pb-1">
                                                    {{ $tipoProducto === 'Promocion' ? 'Descripción' : 'Detalles' }}
                                                </h4>

                                                {{-- CASO 1: Producto --}}
                                                @if ($tipoProducto === 'Producto')
                                                    @if ($product->attributes->count() > 0)
                                                        @foreach ($product->attributes as $attribute)
                                                            @php
                                                                $rawValues = $attribute->pivot->values;
                                                                $listaValores = is_string($rawValues)
                                                                    ? json_decode($rawValues, true)
                                                                    : $rawValues;
                                                            @endphp
                                                            @if (!empty($listaValores))
                                                                <div class="mb-2 last:mb-0">
                                                                    <span
                                                                        class="text-[0.65rem] font-bold text-gray-300 block uppercase mb-1">
                                                                        {{ $attribute->name }}:
                                                                    </span>
                                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                                        @foreach ($listaValores as $valor)
                                                                            <span class="attr-tag-tooltip">
                                                                                {{ is_array($valor) ? $valor['name'] : $valor->name }}
                                                                            </span>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        <span class="text-xs text-gray-500 italic">Sin detalles
                                                            adicionales</span>
                                                    @endif

                                                    {{-- CASO 2: Promoción --}}
                                                @elseif($tipoProducto === 'Promocion')
                                                    <div class="text-xs text-gray-300">
                                                        {{ $product->description ?? 'Sin descripción disponible.' }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-12 text-center text-gray-500">
                            No se encontraron productos ni promociones.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- COLUMNA DERECHA: SIDEBAR (VISIBLE SOLO EN ESCRITORIO) --}}
        <div class="pos-sidebar">
            <div class="cart-header">
                <div class="flex items-center gap-3">
                    <span>Orden Actual</span>

                    {{-- Solo mostramos el botón si hay pedido (tu validación) --}}
                    @if ($pedido && !(count($carrito) === 0))
                        {{-- ESTO RENDERIZA EL BOTÓN ROJO CON EL MODAL AUTOMÁTICO --}}
                        {{ $this->anularPedidoAction }}
                    @endif
                </div>

                {{-- Agrupamos en columna alineada a la derecha --}}
                <div class="flex flex-col items-end leading-tight">
                    @if ($canal === 'salon')
                        <span class="text-sm font-normal text-gray-500">
                            Mesa: <span class="font-bold text-gray-800">{{ $mesa }}</span>
                        </span>
                    @else
                        <div class="flex flex-col items-end">
                            <span class="text-[10px] text-blue-500 uppercase font-bold tracking-tighter">
                                {{ $canal === 'delivery' ? '🛵 Delivery' : ($canal === 'llevar' ? '🛍️ Llevar' : '🏢 Salón') }}
                                {{-- Nombre del repartidor en línea pequeña --}}
                                {!! $canal === 'delivery' && $nombre_repartidor
                                    ? " | <span class='text-gray-400'>$nombre_repartidor</span>"
                                    : '' !!}
                            </span>
                            <span class="text-sm font-bold text-gray-800 dark:text-white truncate max-w-[150px]"
                                title="{{ $nombre_cliente }}">
                                {{ $nombre_cliente ?? 'Publico en general' }}
                            </span>
                        </div>
                    @endif

                    @if ($codigoOrden)
                        <span class="text-xs font-bold text-blue-600 mt-1">
                            N° {{ $codigoOrden }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="cart-items-container">
                @forelse($carrito as $index => $item)
                    @php
                        // LÓGICA DE COLORES
                        $claseFondo = '';
                        $esGuardado = isset($item['guardado']) && $item['guardado'];

                        if (!$esGuardado) {
                            // CASO 1: NUEVO -> VERDE
                            $claseFondo = 'item-nuevo';
                        } else {
                            // CASO 2: EXISTENTE -> VERIFICAR CAMBIOS
                            $id = $item['item_id'];
                            $cantOrig = $this->cantidadesOriginales[$id] ?? 0;
                            $precioOrig = $this->preciosOriginales[$id] ?? 0;

                            $cambioCantidad = $item['quantity'] != $cantOrig;
                            $cambioPrecio = abs(floatval($item['price']) - floatval($precioOrig)) > 0.001;

                            if ($cambioCantidad || $cambioPrecio) {
                                $claseFondo = 'item-actualizado'; // AMARILLO
                            }
                        }
                    @endphp

                    {{-- TU ESTRUCTURA ORIGINAL + CLASE DE COLOR --}}
                    <div class="cart-item {{ $claseFondo }}" wire:key="cart-item-{{ $item['item_id'] }}">
                        <div class="cart-item-info">
                            <div class="cart-item-title">
                                {{ $item['name'] }}
                                @if ($item['is_cortesia'])
                                    <span class="text-xs text-orange-500 font-bold">(Cortesía)</span>
                                @endif
                            </div>

                            <div class="cart-item-price">
                                {{-- AQUÍ ESTÁ EL CAMBIO: INPUT SI ES NUEVO, TEXTO SI ES VIEJO --}}
                                S/ {{ number_format($item['price'], 2) }} u.

                                @if ($item['notes'])
                                    <div class="text-xs text-gray-400 italic mt-1">
                                        Nota: {{ Str::limit($item['notes'], 20) }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="cart-item-actions">
                            <div class="font-bold">S/ {{ number_format($item['total'], 2) }}</div>
                            <div class="qty-control">
                                @if ($item['quantity'] == 1)
                                    <button class="btn-qty" wire:click="eliminarItem({{ $index }})">
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

                                <input type="number" min="1" class="qty-input"
                                    value="{{ $item['quantity'] }}"
                                    wire:change="actualizarCantidadManual({{ $index }}, $event.target.value)">

                                <button class="btn-qty"
                                    wire:click="incrementarCantidad({{ $index }})">+</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-gray-400 text-center py-10">
                        <svg style="width:48px;height:48px;margin:0 auto 10px;opacity:0.5" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        <span class="text-sm">La orden está vacía</span>
                    </div>
                @endforelse
            </div>


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

                <div class="mt-4">
                    @if (!$pedido)
                        {{-- CASO 1: ORDENAR NUEVO --}}
                        <button wire:key="btn-ordenar-nuevo" class="btn-checkout bg-blue" wire:click="procesarOrden"
                            wire:loading.attr="disabled" @if (count($carrito) == 0) disabled @endif>

                            <span wire:loading.remove wire:target="procesarOrden">
                                ORDENAR (S/ {{ number_format($total, 2) }})
                            </span>

                            <div wire:loading wire:target="procesarOrden">
                                <x-spiner-text>PROCESANDO...</x-spiner-text>
                            </div>
                        </button>
                    @else
                        @if ($hayCambios)
                            {{-- SUB-CASO A: ACTUALIZAR --}}
                            <button wire:key="btn-actualizar-pedido" class="btn-checkout bg-yellow"
                                wire:click="actualizarOrden" wire:loading.attr="disabled">

                                <span wire:loading.remove wire:target="actualizarOrden">ACTUALIZAR</span>

                                <div wire:loading wire:target="actualizarOrden">
                                    <x-spiner-text>GUARDANDO...</x-spiner-text>
                                </div>
                            </button>
                        @elseif (count($carrito) === 0)
                            {{-- SUB-CASO B: ANULAR --}}
                            <button wire:key="btn-anular-pedido" class="btn-checkout bg-red"
                                wire:click="mountAction('anularPedido')" wire:loading.attr="disabled">

                                <span wire:loading.remove wire:target="mountAction('anularPedido')">ANULAR
                                    PEDIDO</span>

                                <div wire:loading wire:target="mountAction('anularPedido')">
                                    <x-spiner-text>ANULANDO...</x-spiner-text>
                                </div>
                            </button>
                        @else
                            {{-- SUB-CASO C: COBRAR --}}
                            <button wire:key="btn-cobrar-pedido" class="btn-checkout bg-green"
                                wire:click="pagarOrden" wire:loading.attr="disabled">

                                <span wire:loading.remove wire:target="pagarOrden">
                                    COBRAR S/ {{ number_format($total, 2) }}
                                </span>

                                <div wire:loading wire:target="pagarOrden">
                                    <x-spiner-text>COBRANDO...</x-spiner-text>
                                </div>
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- COMPONENTES MÓVILES (FUERA DEL LAYOUT GRID) --}}
    {{-- ========================================================= --}}

    {{-- 1. BOTÓN FLOTANTE (FAB) - SOLO VISIBLE EN MÓVIL --}}
    <button class="mobile-fab-cart" @click="mobileCartOpen = true">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"
            stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span>S/ {{ number_format($total, 2) }}</span>
    </button>

    {{-- 2. MODAL CARRITO MÓVIL --}}
    <div class="mobile-cart-overlay" style="display: none;" x-show="mobileCartOpen"
        x-transition.opacity.duration.300ms>

        {{-- Click fuera cierra el modal --}}
        {{-- <div class="absolute inset-0" @click="mobileCartOpen = false"></div> --}}

        <div class="mobile-cart-content" @click.stop>
            <div class="mobile-cart-header">
                <div class="flex items-center gap-3">
                    <div class="flex flex-col">
                        <span class="text-[10px] text-blue-500 uppercase font-bold tracking-tighter">
                            {{ $canal === 'delivery' ? '🛵 Delivery' : ($canal === 'llevar' ? '🛍️ Llevar' : '🏢 Salón') }}
                            {{-- Nombre del repartidor en línea pequeña --}}
                            {!! $canal === 'delivery' && $nombre_repartidor
                                ? " | <span class='text-gray-400'>$nombre_repartidor</span>"
                                : '' !!}
                        </span>
                        <span class="text-sm font-bold text-gray-800 dark:text-white">
                            {{ $canal === 'salon' ? 'Mesa ' . $mesa : $nombre_cliente ?? 'Cliente' }}
                        </span>
                    </div>
                    @if ($pedido && !(count($carrito) === 0))
                        {{ $this->anularPedidoAction }}
                    @endif
                </div>
                <button class="close-modal-btn" @click="mobileCartOpen = false">✕</button>
            </div>

            {{-- REPETICIÓN DEL LOOP DEL CARRITO (PARA MÓVIL) --}}
            <div class="cart-items-container">
                @forelse($carrito as $index => $item)
                    @php
                        // LÓGICA DE COLORES
                        $claseFondo = '';
                        $esGuardado = isset($item['guardado']) && $item['guardado'];
                        if (!$esGuardado) {
                            // CASO 1: NUEVO -> VERDE
                            $claseFondo = 'item-nuevo';
                        } else {
                            // CASO 2: EXISTENTE -> VERIFICAR CAMBIOS
                            $id = $item['item_id'];
                            $cantOrig = $this->cantidadesOriginales[$id] ?? 0;
                            $precioOrig = $this->preciosOriginales[$id] ?? 0;

                            $cambioCantidad = $item['quantity'] != $cantOrig;
                            $cambioPrecio = abs(floatval($item['price']) - floatval($precioOrig)) > 0.001;

                            if ($cambioCantidad || $cambioPrecio) {
                                $claseFondo = 'item-actualizado'; // AMARILLO
                            }
                        }
                    @endphp

                    {{-- TU ESTRUCTURA ORIGINAL + CLASE DE COLOR --}}
                    <div class="cart-item {{ $claseFondo }}" wire:key="cart-item-{{ $item['item_id'] }}">
                        <div class="cart-item-info">
                            <div class="cart-item-title">
                                {{ $item['name'] }}
                                @if ($item['is_cortesia'])
                                    <span class="text-xs text-orange-500 font-bold">(Cortesía)</span>
                                @endif
                            </div>

                            <div class="cart-item-price">
                                {{-- AQUÍ ESTÁ EL CAMBIO: INPUT SI ES NUEVO, TEXTO SI ES VIEJO --}}
                                S/ {{ number_format($item['price'], 2) }} u.

                                @if ($item['notes'])
                                    <div class="text-xs text-gray-400 italic mt-1">
                                        Nota: {{ Str::limit($item['notes'], 20) }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="cart-item-actions">
                            <div class="font-bold">S/ {{ number_format($item['total'], 2) }}</div>
                            <div class="qty-control">
                                @if ($item['quantity'] == 1)
                                    <button class="btn-qty" wire:click="eliminarItem({{ $index }})">
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

                                <input type="number" min="1" class="qty-input"
                                    value="{{ $item['quantity'] }}"
                                    wire:change="actualizarCantidadManual({{ $index }}, $event.target.value)">

                                <button class="btn-qty"
                                    wire:click="incrementarCantidad({{ $index }})">+</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-gray-400 text-center py-10">
                        <svg style="width:48px;height:48px;margin:0 auto 10px;opacity:0.5" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        <span class="text-sm">La orden está vacía</span>
                    </div>
                @endforelse
            </div>

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

                <div class="mt-4">
                    @if (!$pedido)
                        {{-- CASO 1: ORDENAR NUEVO --}}
                        <button wire:key="btn-ordenar-nuevo" class="btn-checkout bg-blue" wire:click="procesarOrden"
                            wire:loading.attr="disabled" @if (count($carrito) == 0) disabled @endif>

                            <span wire:loading.remove wire:target="procesarOrden">
                                ORDENAR (S/ {{ number_format($total, 2) }})
                            </span>

                            <div wire:loading wire:target="procesarOrden">
                                <x-spiner-text>PROCESANDO...</x-spiner-text>
                            </div>
                        </button>
                    @else
                        @if ($hayCambios)
                            {{-- SUB-CASO A: ACTUALIZAR --}}
                            <button wire:key="btn-actualizar-pedido" class="btn-checkout bg-yellow"
                                wire:click="actualizarOrden" wire:loading.attr="disabled">

                                <span wire:loading.remove wire:target="actualizarOrden">ACTUALIZAR</span>

                                <div wire:loading wire:target="actualizarOrden">
                                    <x-spiner-text>GUARDANDO...</x-spiner-text>
                                </div>
                            </button>
                        @elseif (count($carrito) === 0)
                            {{-- SUB-CASO B: ANULAR --}}
                            <button wire:key="btn-anular-pedido" class="btn-checkout bg-red"
                                wire:click="mountAction('anularPedido')" wire:loading.attr="disabled">

                                <span wire:loading.remove wire:target="mountAction('anularPedido')">ANULAR
                                    PEDIDO</span>

                                <div wire:loading wire:target="mountAction('anularPedido')">
                                    <x-spiner-text>ANULANDO...</x-spiner-text>
                                </div>
                            </button>
                        @else
                            {{-- SUB-CASO C: COBRAR --}}
                            <button wire:key="btn-cobrar-pedido" class="btn-checkout bg-green"
                                wire:click="pagarOrden" wire:loading.attr="disabled">

                                <span wire:loading.remove wire:target="pagarOrden">
                                    COBRAR S/ {{ number_format($total, 2) }}
                                </span>

                                <div wire:loading wire:target="pagarOrden">
                                    <x-spiner-text>COBRANDO...</x-spiner-text>
                                </div>
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- LÓGICA DE PREPARACIÓN DE PESTAÑAS Y LLAMADA AL MODAL --}}
    {{-- ========================================================================= --}}
    @php
        $jobId = session('print_job_id');
        $areasCollection = collect();

        // 1. Intentamos obtener datos del Cache (Impresión Parcial/Actualización)
        $datosCache = $jobId ? \Illuminate\Support\Facades\Cache::get($jobId) : null;

        if ($datosCache) {
            // Fusionamos 'nuevos' y 'cancelados' que vienen del cache
            $items = array_merge($datosCache['nuevos'] ?? [], $datosCache['cancelados'] ?? []);
            foreach ($items as $item) {
                // Aquí usamos el 'area_id' que agregamos al backend en el paso anterior
                $areasCollection->push([
                    'id' => $item['area_id'] ?? 'general',
                    'name' => $item['area_nombre'] ?? 'GENERAL',
                ]);
            }
        }
        // 2. Si no hay cache, usamos el objeto Order completo (Impresión Total/Reimpresión)
        elseif ($ordenGenerada) {
            foreach ($ordenGenerada->details as $det) {
                $prod = $det->product->production ?? null;
                $printer = $prod?->printer ?? null;

                if ($prod && $prod->status && $printer && $printer->status) {
                    $areasCollection->push(['id' => $prod->id, 'name' => $prod->name]);
                } else {
                    $areasCollection->push(['id' => 'general', 'name' => 'GENERAL']);
                }
            }
        }

        // Filtramos para tener solo una pestaña por área
        $areasUnicas = $areasCollection->unique('id');
    @endphp

    @if ($mostrarModalComanda && $ordenGenerada)
        {{-- PASAMOS LA VARIABLE $areasUnicas AL COMPONENTE --}}
        <x-modal-ticket :orderId="$ordenGenerada->id" :jobId="$jobId" :areas="$areasUnicas" />
    @endif
    {{-- ========================================================================= --}}

    @if ($productoSeleccionado)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="absolute inset-0" wire:click="cerrarModal"></div>
            <div class="relative z-10 w-full max-w-md">
                <x-cardproduct :product="$productoSeleccionado" :variantId="$variantSeleccionadaId" />
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            let scrollAnimation;

            function scrollCategories(direction) {
                const container = document.getElementById('categoryList');
                if (!container) return;
                const distance = 100;
                const duration = 400;
                const start = container.scrollLeft;
                const change = direction === 'left' ? -distance : distance;
                const startTime = performance.now();
                cancelAnimationFrame(scrollAnimation);

                function animate(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const ease = 1 - (1 - progress) * (1 - progress);
                    container.scrollLeft = start + (change * ease);
                    if (elapsed < duration) {
                        scrollAnimation = requestAnimationFrame(animate);
                    }
                }
                scrollAnimation = requestAnimationFrame(animate);
            }
        </script>
    @endpush
    <x-filament-actions::modals />
</x-filament-panels::page>
