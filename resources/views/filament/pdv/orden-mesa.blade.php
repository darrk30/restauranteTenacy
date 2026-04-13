@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ordenmesa.css') }}">
@endpush

{{-- AGREGAMOS x-data AQUÍ PARA CONTROLAR EL CARRITO MÓVIL EN TODA LA PÁGINA --}}
<x-filament-panels::page x-data="{ mobileCartOpen: false }">
    <div>
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
                <div class="search-container" style="position: relative; display: flex; align-items: center;">

                    {{-- 1. Icono de Lupa (Izquierda) --}}
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>

                    {{-- 2. Input de Búsqueda --}}
                    <input type="text" placeholder="Buscar producto..." class="search-input"
                        wire:model.live.debounce.300ms="search">

                    {{-- 3. Botón "X" para limpiar (Derecha) --}}
                    {{-- Usamos Alpine (x-data y x-show) para que solo aparezca si $wire.search tiene texto --}}
                    <button type="button" x-data x-show="$wire.search && $wire.search.length > 0" x-cloak
                        class="clear-btn" wire:click="$set('search', '')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- 3. PRODUCTOS --}}
                <div class="pos-products-area" wire:loading.class="opacity-50 pointer-events-none"
                    wire:target="search, categoriaSeleccionada">
                    <div class="products-grid">
                        @forelse ($productos as $product)
                            @php
                                $tipoProducto =
                                    $product->type instanceof \App\Enums\TipoProducto
                                        ? $product->type->value
                                        : $product->type;
                                $metodoTarget = $tipoProducto === 'Promocion' ? 'agregarPromocion' : 'agregarProducto';
                            @endphp

                            <div wire:key="item-{{ $tipoProducto }}-{{ $product->id }}"
                                class="product-card group select-none {{ $product->esta_agotado ? 'opacity-50' : 'cursor-pointer' }}"
                                style="position: relative; touch-action: manipulation; -webkit-touch-callout: none; overflow: hidden; border-radius: 12px;"
                                oncontextmenu="return false;" x-data="{
                                    tooltipOpen: false,
                                    isPressing: false,
                                    pressTimer: null,
                                
                                    startPress() {
                                        @if ($product->esta_agotado) return; @endif
                                
                                        this.isPressing = true;
                                        this.pressTimer = setTimeout(() => {
                                            this.isPressing = false;
                                            this.tooltipOpen = true;
                                        }, 500); // 500ms para mantener presionado
                                    },
                                    endPress() {
                                        clearTimeout(this.pressTimer);
                                        if (this.isPressing && !this.tooltipOpen) {
                                            this.isPressing = false;
                                            @if(!$product->esta_agotado)
                                            $wire.{{ $metodoTarget }}({{ $product->id }});
                                            @endif
                                        }
                                    },
                                    cancelPress() {
                                        clearTimeout(this.pressTimer);
                                        this.isPressing = false;
                                    }
                                }" @mousedown="startPress"
                                @mouseup="endPress" @mouseleave="cancelPress" @touchstart.passive="startPress"
                                @touchend.passive="endPress" @touchcancel="cancelPress">

                                {{-- 1. SPINNER DE LIVEWIRE (Se muestra al hacer click rápido) --}}
                                {{-- NOTA: Usamos wire:loading.flex para que Livewire lo controle correctamente --}}
                                <div wire:loading.flex wire:target="{{ $metodoTarget }}({{ $product->id }})"
                                    class="absolute inset-0 z-50 items-center justify-center rounded-xl"
                                    style="background-color: rgba(255,255,255,0.7); backdrop-filter: blur(2px);">
                                    <svg class="animate-spin h-8 w-8 text-primary-600" viewBox="0 0 24 24"
                                        fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </div>

                                {{-- 2. FEEDBACK VISUAL MIENTRAS MANTIENES PRESIONADO --}}
                                <div x-show="isPressing" x-transition.opacity.duration.200ms
                                    class="absolute inset-0 z-40 flex items-center justify-center rounded-xl pointer-events-none"
                                    style="display: none; background-color: rgba(0,0,0,0.15);">
                                    <svg class="animate-spin h-10 w-10 text-white opacity-80" viewBox="0 0 24 24"
                                        fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </div>

                                {{-- ================= CONTENIDO NORMAL DE LA TARJETA ================= --}}
                                <div class="product-image-container relative">
                                    @if ($tipoProducto === 'Promocion')
                                        <div class="pos-badge">COMBO</div>
                                    @endif
                                    @if ($tipoProducto === 'Servicio')
                                        <div class="pos-badge">SERVICIO</div>
                                    @endif

                                    @if ($product->image_path)
                                        <img src="{{ asset('storage/' . $product->image_path) }}"
                                            alt="{{ $product->name }}" class="product-img">
                                    @else
                                        <div class="flex items-center justify-center h-full text-gray-400 bg-gray-50">
                                            <svg style="width:32px; height:32px" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                        </div>
                                    @endif

                                    @if ($product->esta_agotado)
                                        <div class="agotado-overlay"><span class="agotado-badge">AGOTADO</span></div>
                                    @endif

                                    <div class="price-tag-overlay">
                                        <span
                                            class="text-xs font-medium">S/</span>{{ number_format($product->price, 2) }}
                                    </div>
                                </div>

                                <div class="product-info" style="padding-bottom: 12px;">
                                    <h3 class="product-title">{{ $product->name }}</h3>
                                    @if ($tipoProducto === 'Producto' && $product->control_stock == 1)
                                        <p
                                            class="stock-text {{ $product->stock_visible > 0 ? 'text-gray-500' : 'text-red-500' }}">
                                            Stock: {{ $product->stock_visible }}</p>
                                    @endif
                                    @if ($tipoProducto === 'Promocion' && $product->tiene_limite)
                                        <p
                                            class="stock-text {{ $product->stock_visible > 0 ? 'text-purple-600' : 'text-red-500' }}">
                                            Restantes hoy: {{ $product->stock_visible }}</p>
                                    @endif
                                    @if ($tipoProducto === 'Promocion' && !$product->tiene_limite)
                                        <p class="text-[10px] text-purple-500 font-medium italic">Disponible</p>
                                    @endif
                                    @if ($tipoProducto === 'Servicio')
                                        <p class="text-[10px] text-blue-500 italic">Servicio</p>
                                    @endif
                                </div>

                                {{-- 3. EL TOOLTIP INFORMATIVO OVERLAY (Se abre al completar el Hold) --}}
                                @if ($tipoProducto === 'Producto' || $tipoProducto === 'Promocion')
                                    <div x-show="tooltipOpen" x-transition:enter="transition ease-out duration-200" class="tooltip-overlay"
                                        x-transition:enter-start="opacity-0 transform scale-95"
                                        x-transition:enter-end="opacity-100 transform scale-100"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100 transform scale-100"
                                        x-transition:leave-end="opacity-0 transform scale-95"
                                        @click.stop="tooltipOpen = false" {{-- Un clic en el overlay lo cierra --}} {{-- SE AGREGÓ: -webkit-backdrop-filter y backdrop-filter, y se ajustó la opacidad del background a 0.85 --}}
                                    >
                                        <h4
                                            class="text-xs font-bold text-gray-300 uppercase mb-3 border-b border-gray-600 pb-2 flex justify-between items-center">
                                            {{ $tipoProducto === 'Promocion' ? 'Descripción' : 'Detalles' }}
                                            <button @click="tooltipOpen = false" style="color: white">✕</button>
                                        </h4>

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
                                                <span class="text-xs italic" style="color: white">Sin detalles adicionales</span>
                                            @endif
                                        @elseif($tipoProducto === 'Promocion')
                                            <div class="text-xs text-gray-200 leading-relaxed">
                                                {{ $product->description ?? 'Sin descripción disponible.' }}
                                            </div>
                                        @endif
                                    </div>
                                @endif
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
                <div class="cart-header flex justify-between items-start w-full pb-3 border-b border-gray-100 mb-4">

                    {{-- LADO IZQUIERDO: Título, Código y Botones de Acción --}}
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-black text-gray-800">Orden Actual</span>

                            @if ($codigoOrden)
                                <span
                                    class="bg-blue-50 text-blue-600 border border-blue-200 text-[10px] font-bold px-2 py-0.5 rounded-md tracking-wider">
                                    #{{ $codigoOrden }}
                                </span>
                            @endif
                        </div>

                        {{-- Botones de Acción (Solo si hay pedido e ítems) --}}
                        @if ($pedido && count($carrito) > 0)
                            <div class="flex items-center gap-2 mt-1">
                                @can('anular_pedido_rest')
                                    {{ $this->anularPedidoAction }}
                                @endcan
                                {{ $this->mostrarPrecuenta }}
                            </div>
                        @endif
                    </div>

                    {{-- LADO DERECHO: Contexto de la Orden (Mesa / Delivery / Llevar) --}}
                    <div class="flex flex-col items-end text-right">

                        @if ($canal === 'salon')
                            {{-- Diseño para Salón --}}
                            <span
                                class="bg-gray-100 text-gray-600 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded mb-1">
                                🏢 Salón
                            </span>
                            <div class="flex items-baseline gap-1 text-gray-500">
                                <span class="text-xs">Mesa</span>
                                <span class="text-xl font-black text-gray-800 leading-none">{{ $mesa }}</span>
                            </div>
                        @else
                            {{-- Diseño para Delivery / Llevar --}}
                            <span
                                class="bg-{{ $canal === 'delivery' ? 'blue' : 'emerald' }}-50 text-{{ $canal === 'delivery' ? 'blue' : 'emerald' }}-600 border border-{{ $canal === 'delivery' ? 'blue' : 'emerald' }}-200 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded mb-1.5">
                                {{ $canal === 'delivery' ? '🛵 Delivery' : '🛍️ Llevar' }}
                            </span>

                            <div class="flex items-center gap-1.5 text-sm font-bold text-gray-800 truncate max-w-[160px]"
                                title="{{ $nombre_cliente }}">
                                <x-heroicon-m-user class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                <span class="truncate">{{ $nombre_cliente ?? 'Público general' }}</span>
                            </div>

                            @if ($canal === 'delivery' && $nombre_repartidor)
                                <div class="flex items-center gap-1 mt-1 text-[11px] font-medium text-blue-500">
                                    <x-heroicon-m-truck class="w-3.5 h-3.5" />
                                    <span>Rep: {{ $nombre_repartidor }}</span>
                                </div>
                            @endif
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
                        <span class="text-gray-500">IGV ({{ get_tax_percentage() }}%)</span>
                        <span class="font-bold">S/ {{ number_format($igv, 2) }}</span>
                    </div>
                    <div class="cart-total-row cart-total-final">
                        <span>Total</span>
                        <span>S/ {{ number_format($total, 2) }}</span>
                    </div>

                    <div class="mt-4">
                        @if (!$pedido)
                            @can('ordenar_pedido_rest')
                                {{-- CASO 1: ORDENAR NUEVO --}}
                                <button wire:key="btn-ordenar-nuevo" class="btn-checkout bg-blue"
                                    wire:click="procesarOrden" wire:loading.attr="disabled"
                                    @if (count($carrito) == 0) disabled @endif>

                                    <span wire:loading.remove wire:target="procesarOrden">
                                        ORDENAR (S/ {{ number_format($total, 2) }})
                                    </span>

                                    <div wire:loading wire:target="procesarOrden">
                                        <x-spiner-text>PROCESANDO...</x-spiner-text>
                                    </div>
                                </button>
                            @endcan
                        @else
                            @if ($hayCambios)
                                @can('ordenar_pedido_rest')
                                    {{-- SUB-CASO A: ACTUALIZAR --}}
                                    <button wire:key="btn-actualizar-pedido" class="btn-checkout bg-yellow"
                                        wire:click="actualizarOrden" wire:loading.attr="disabled">

                                        <span wire:loading.remove wire:target="actualizarOrden">ACTUALIZAR</span>

                                        <div wire:loading wire:target="actualizarOrden">
                                            <x-spiner-text>GUARDANDO...</x-spiner-text>
                                        </div>
                                    </button>
                                @endcan
                            @elseif (count($carrito) === 0)
                                {{-- SUB-CASO B: ANULAR --}}
                                @can('ordenar_pedido_rest')
                                    <button wire:key="btn-anular-pedido" class="btn-checkout bg-red"
                                        wire:click="mountAction('anularPedido')" wire:loading.attr="disabled">

                                        <span wire:loading.remove wire:target="mountAction('anularPedido')">ANULAR
                                            PEDIDO</span>

                                        <div wire:loading wire:target="mountAction('anularPedido')">
                                            <x-spiner-text>ANULANDO...</x-spiner-text>
                                        </div>
                                    </button>
                                @endcan
                            @else
                                {{-- SUB-CASO C: COBRAR --}}
                                @can('cobrar_pedido_rest')
                                    <button wire:key="btn-cobrar-pedido" class="btn-checkout bg-green"
                                        wire:click="pagarOrden" wire:loading.attr="disabled">

                                        <span wire:loading.remove wire:target="pagarOrden">
                                            COBRAR S/ {{ number_format($total, 2) }}
                                        </span>

                                        <div wire:loading wire:target="pagarOrden">
                                            <x-spiner-text>COBRANDO...</x-spiner-text>
                                        </div>
                                    </button>
                                @endcan
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
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                            @can('anular_pedido_rest')
                                {{ $this->anularPedidoAction }}
                            @endcan
                            {{ $this->mostrarPrecuenta }}
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
                            @can('ordenar_pedido_rest')
                                <button wire:key="btn-ordenar-nuevo" class="btn-checkout bg-blue"
                                    wire:click="procesarOrden" wire:loading.attr="disabled"
                                    @if (count($carrito) == 0) disabled @endif>

                                    <span wire:loading.remove wire:target="procesarOrden">
                                        ORDENAR (S/ {{ number_format($total, 2) }})
                                    </span>

                                    <div wire:loading wire:target="procesarOrden">
                                        <x-spiner-text>PROCESANDO...</x-spiner-text>
                                    </div>
                                </button>
                            @endcan
                        @else
                            @if ($hayCambios)
                                @can('ordenar_pedido_rest')
                                    {{-- SUB-CASO A: ACTUALIZAR --}}
                                    <button wire:key="btn-actualizar-pedido" class="btn-checkout bg-yellow"
                                        wire:click="actualizarOrden" wire:loading.attr="disabled">

                                        <span wire:loading.remove wire:target="actualizarOrden">ACTUALIZAR</span>

                                        <div wire:loading wire:target="actualizarOrden">
                                            <x-spiner-text>GUARDANDO...</x-spiner-text>
                                        </div>
                                    </button>
                                @endcan
                            @elseif (count($carrito) === 0)
                                {{-- SUB-CASO B: ANULAR --}}
                                @can('ordenar_pedido_rest')
                                    <button wire:key="btn-anular-pedido" class="btn-checkout bg-red"
                                        wire:click="mountAction('anularPedido')" wire:loading.attr="disabled">

                                        <span wire:loading.remove wire:target="mountAction('anularPedido')">ANULAR
                                            PEDIDO</span>

                                        <div wire:loading wire:target="mountAction('anularPedido')">
                                            <x-spiner-text>ANULANDO...</x-spiner-text>
                                        </div>
                                    </button>
                                @endcan
                            @else
                                {{-- SUB-CASO C: COBRAR --}}
                                @can('cobrar_pedido_rest')
                                    <button wire:key="btn-cobrar-pedido" class="btn-checkout bg-green"
                                        wire:click="pagarOrden" wire:loading.attr="disabled">

                                        <span wire:loading.remove wire:target="pagarOrden">
                                            COBRAR S/ {{ number_format($total, 2) }}
                                        </span>

                                        <div wire:loading wire:target="pagarOrden">
                                            <x-spiner-text>COBRANDO...</x-spiner-text>
                                        </div>
                                    </button>
                                @endcan
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

        @if ($mostrarModalPrecuenta)
            <div class="pantalla-exito-overlay"
                style="display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.7); position: fixed; inset: 0; z-index: 9999;">
                <div class="modal-exito-contenedor"
                    style="background: white; padding: 20px; border-radius: 12px; width: 90%; max-width: 400px; text-align: center;">
                    <h2 style="font-weight: bold; margin-bottom: 15px;">Imprimir Pre-cuenta</h2>

                    <div style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
                        <iframe id="iframe-precuenta" src="{{ route('order.precuenta.print', $pedido) }}"
                            style="width: 100%; height: 350px; border: none;"></iframe>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button wire:click="cerrarPrecuenta"
                            style="flex: 1; padding: 12px; border-radius: 8px; background: #EEE; font-weight: bold;">CERRAR</button>
                        <button onclick="imprimirIframe('iframe-precuenta')"
                            style="flex: 1; padding: 12px; border-radius: 8px; background: #2563EB; color: white; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 5px;">
                            <x-heroicon-o-printer style="width: 18px; height: 18px;" /> IMPRIMIR
                        </button>
                    </div>
                </div>
            </div>
        @endif

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
                window.scrollAnimation = window.scrollAnimation || null;

                function imprimirIframe(id) {
                    const frame = document.getElementById(id);
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                }

                function scrollCategories(direction) {
                    const container = document.getElementById('categoryList');
                    if (!container) return;
                    const distance = 100;
                    const duration = 400;
                    const start = container.scrollLeft;
                    const change = direction === 'left' ? -distance : distance;
                    const startTime = performance.now();
                    cancelAnimationFrame(window.scrollAnimation);

                    function animate(currentTime) {
                        const elapsed = currentTime - startTime;
                        const progress = Math.min(elapsed / duration, 1);
                        const ease = 1 - (1 - progress) * (1 - progress);
                        container.scrollLeft = start + (change * ease);
                        if (elapsed < duration) {
                            window.scrollAnimation = requestAnimationFrame(animate);
                        }
                    }
                    window.scrollAnimation = requestAnimationFrame(animate);
                }
            </script>
        @endpush
        <x-filament-actions::modals />
    </div>
</x-filament-panels::page>
