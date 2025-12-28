<div wire:ignore x-data="pedidoMesa(@js($productos), {{ $mesa }}, {{ $pedido ?? 'null' }}, @js($carrito))" x-init="loading = false;
showSuccess = false;
pedidoCancelado = false;
detalleCancelado = false;
$wire.on('pedido-guardado', () => {
    loading = false;
    showSuccess = true;
});
$wire.on('pedido-anulado', () => {
    loading = false;
    pedidoCancelado = true;
});
$wire.on('detalle-cancelado', () => {
    loading = false;
    detalleCancelado = true;
});" class="pdv">
    <!-- ================= LEFT ================= -->
    <div class="left-panel">

        <!-- BUSCADOR -->
        <div class="product-search">
            <input type="text" placeholder="Buscar producto..." x-model.debounce.300ms="search">
        </div>

        <button class="cart-fab" @click="carritoAbierto = true">
            🛒 <span x-text="carrito.length"></span>
        </button>

        <!-- CATEGORÍAS -->
        <div class="categories">
            <button class="category-btn" :class="{ active: categoria === 'todos' }" @click="categoria = 'todos'">
                Todos
            </button>

            @foreach ($categorias as $cat)
                <button class="category-btn" :class="{ active: categoria === '{{ $cat->id }}' }"
                    @click="categoria = '{{ $cat->id }}'">
                    {{ $cat->name }}
                </button>
            @endforeach
        </div>

        <!-- PRODUCTOS -->
        <div class="products-container">
            <div class="products-grid">
                <template x-for="producto in productosFiltrados()" :key="producto.id">
                    <div class="product-card" @click="abrirModal(producto)">
                        <div class="product-image">
                            <span class="product-stock" x-show="producto.control_stock">
                                Stock:
                                <span x-text="producto.stock_reserva_total"></span>
                            </span>


                            <img
                                :src="producto.image_path ?
                                    `/storage/${producto.image_path}` :
                                    '/img/productdefault.jpg'">
                        </div>

                        <div class="product-info">
                            <div class="product-name" x-text="producto.name"></div>
                            <div class="product-price">
                                S/ <span x-text="producto.price.toFixed(2)"></span>
                            </div>


                        </div>
                    </div>

                </template>
            </div>
        </div>


        <div class="empty-state" x-show="productosFiltrados().length === 0">
            <img src="/img/sinproductos.avif" alt="Sin productos">
            <h3>No se encontraron productos</h3>
            <p>Intenta cambiar la categoría o buscar otro nombre</p>
        </div>

    </div>

    <!-- ================= RIGHT ================= -->
    <div class="right-panel" :class="{ 'open': carritoAbierto }">
        <button class="close-cart" @click="carritoAbierto = false">
            ✕
        </button>

        <div class="order-header">

            <!-- Info Mesa / Pedido -->
            <div class="order-info">
                <span class="mesa">Mesa {{ $mesa }}</span>

                @if ($pedido)
                    <span class="pedido">
                        Pedido #<span>{{$pedidocompleto->code}}</span>
                    </span>
                @endif
            </div>

            <!-- Acciones -->
            <div class="order-actions">
                <button class="btn-remove btn-remove-pedido" title="Anular pedido" x-show="pedidoId" x-on:click="abrirModalAnular">
                    <x-heroicon-o-trash class="w-5 h-5" />
                </button>

                <!-- aquí puedes agregar más botones -->
            </div>

        </div>

        <div class="order-items">
            <template x-for="item in carrito" :key="item.key">
                <div class="order-item">

                    <div class="order-left" @click="editarItem(item)">
                        <div class="order-qty">
                            x <span x-text="item.cantidad"></span>
                        </div>
                        <div class="order-name">
                            <div x-text="item.nombre"></div>
                            <template x-if="item.nota">
                                <div class="order-note" x-text="item.nota"></div>
                            </template>
                        </div>

                    </div>

                    <div class="order-actions">
                        <span>
                            S/
                            <span x-text="(item.precio * item.cantidad).toFixed(2)"></span>
                        </span>

                        <button class="btn-remove" @click="abrirModalAnularDetalle(item)">
                            <x-heroicon-o-trash class="w-5 h-5" />
                        </button>

                    </div>

                </div>
            </template>
        </div>


        <div class="order-footer">
            <div class="order-total">
                <span>Total</span>
                <span>S/ <span x-text="total().toFixed(2)"></span></span>
            </div>

            <button class="order-btn flex items-center justify-center gap-2" :disabled="loading"
                @click=" loading = true; ordenarPedido();">
                <template x-if="!loading">
                    <span x-text="pedidoId ? 'ACTUALIZAR' : 'ORDENAR'"></span>
                </template>

                <template x-if="loading">
                    <span class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                            </path>
                        </svg>
                        Guardando...
                    </span>
                </template>
            </button>



        </div>
    </div>

    <!-- ================= MODAL ANULAR PEDIDO ================= -->
    <div class="modal-backdrop" x-show="modalAnular" x-transition>
        <div class="modal-confirm">

            <h3>¿Anular pedido?</h3>

            <p>
                Esta acción no se puede deshacer.<br>
                El pedido será anulado y la mesa quedará libre.
            </p>

            <div class="modal-actions">
                <button class="btn cancel" x-on:click="cerrarModalAnular">
                    Cancelar
                </button>

                <button class="btn danger" x-on:click="confirmarAnular">
                    Anular
                </button>
            </div>

        </div>
    </div>
    <!-- ================= MODAL ANULAR DETALLE PEDIDO ================= -->
    <div class="modal-backdrop" x-show="modalAnularDetalle" x-transition>
        <div class="modal-confirm">

            <h3>¿Anular Item?</h3>

            <p>
                Esta acción no se puede deshacer.<br>
                El producto será anulado y se notificara a cocina.
            </p>

            <div class="modal-actions">
                <button class="btn cancel" x-on:click="cerrarModalAnularDetalle">
                    Cancelar
                </button>

                <button class="btn danger" x-on:click="confirmarAnularDetalle">
                    Anular
                </button>
            </div>

        </div>
    </div>

    <!-- ================= MODAL PARA AGREGAR/EDITAR VARIANTE ================= -->
    <template x-if="modal">
            <div class="modal-backdrop moda-variats modal-enter"
         x-init="
            requestAnimationFrame(() => {
                $el.classList.add('modal-enter-active')
            })
         "
         @click.self="cerrarModal()">

        <div class="modal modal-scale">
                <div class="modal-header" x-text="productoActual.name"></div>
                <template x-if="imagenVariante">
                    <div class="variant-image-box">
                        <img :src="imagenVariante ? `/storage/${imagenVariante}` : ''" alt="Variante"
                            class="variant-image">
                    </div>
                </template>
                <template x-if="modal && !puedeAgregar()">
                    <div class="stock-alert">
                        Stock insuficiente para esta variante
                    </div>
                </template>


                <template x-if="productoActual.cortesia">
                    <label class="cortesia-switch">
                        <span class="cortesia-text">Cortesía</span>

                        <input type="checkbox" x-model="esCortesia" class="sr-only">

                        <div class="switch">
                            <div class="switch-dot"></div>
                        </div>
                    </label>
                </template>


                <div class="modal-body">

                    <!-- VARIANTES AGRUPADAS -->
                    <template x-for="group in productoActual.variant_groups" :key="group.attribute">
                        <div>
                            <div class="note-label" x-text="group.attribute"></div>

                            <div class="flex gap-2 flex-wrap">
                                <template x-for="opt in group.options" :key="opt.id">
                                    <button class="variant-btn" :class="{ active: variante == opt.id }"
                                        @click="variante = opt.id; imagenVariante = opt.image_path ?? null">

                                        <span class="variant-label" x-text="opt.label"></span>

                                        <template x-if="opt.extra_price > 0">
                                            <span class="price-badge">
                                                + S/ <span x-text="opt.extra_price.toFixed(2)"></span>
                                            </span>
                                        </template>

                                        <!-- STOCK POR VARIANTE -->
                                        <template x-if="productoActual.control_stock">
                                            <div class="text-xs text-gray-500 mt-1">
                                                Stock:
                                                <span x-text="opt.stock_reserva_total_variante"></span>
                                            </div>
                                        </template>


                                    </button>
                                </template>

                            </div>
                        </div>
                    </template>

                    <!-- NOTAS PARA COCINA -->
                    <div class="note-box">
                        <label class="note-label">Notas para cocina</label>
                        <textarea x-model="nota" placeholder="Ej: sin cebolla, bien cocido, poco picante..." class="note-input"
                            rows="2"></textarea>
                    </div>


                    <!-- CANTIDAD -->
                    <div class="qty-box">
                        <span class="note-label">Cantidad</span>

                        <div class="qty-controls">
                            <button type="button" @click="cantidad = Math.max(1, cantidad - 1)">
                                −
                            </button>
                            <input type="text" x-model.number="cantidad" inputmode="numeric" class="qty-input"
                                @input=" cantidad = cantidad
                                    .replace(/\D/g, '')
                                    .replace(/^0+/, '')"
                                @blur=" if (!cantidad || cantidad < 1) {
                                    cantidad = 1
                                }">
                            <button type="button" @click="cantidad++">
                                +
                            </button>
                        </div>
                    </div>


                </div>

                <div class="modal-footer">
                    <button class="btn-cancel" @click="cerrarModal()">Cancelar</button>
                    <button class="btn-confirm" :disabled="!variante || !puedeAgregar()" @click="agregar()"
                        x-text="editando ? 'Actualizar' : 'Agregar'">
                    </button>

                </div>

            </div>
        </div>
    </template>

    <!-- ================= MODAL ÉXITO PEDIDO ================= -->
    <div x-show="showSuccess" x-transition.opacity class="success-overlay">
        <div class="success-modal">

            <h2 class="success-title">
                ✅ Pedido registrado
            </h2>

            <p class="success-text">
                El pedido fue enviado correctamente a cocina.
            </p>

            <button type="button" class="success-btn"
                @click="
                showSuccess = false;
                window.location.href =
                    '/restaurants/{{ $restaurantSlug }}/point-of-sale';
            ">
                Aceptar
            </button>

        </div>
    </div>

    <!-- ================= MODAL ÉXITO PEDIDO CANCELADO ================= -->
    <div x-show="pedidoCancelado" x-transition.opacity class="success-overlay">
        <div class="success-modal">

            <h2 class="success-title">
                Pedido Cancelado
            </h2>

            <p class="success-text">
                El pedido fue cancelado correctamente y se notifico a cocina.
            </p>

            <button type="button" class="success-btn"
                @click="
                pedidoCancelado = false;
                window.location.href =
                    '/restaurants/{{ $restaurantSlug }}/point-of-sale';
            ">
                Aceptar
            </button>

        </div>
    </div>

    <!-- ================= MODAL ÉXITO DETALLE CANCELADO ================= -->
    <div x-show="detalleCancelado" x-transition.opacity class="success-overlay">
        <div class="success-modal">

            <h2 class="success-title">
                Detalle Cancelado
            </h2>

            <p class="success-text">
                El detalle fue cancelado correctamente y se notifico a cocina.
            </p>

            <button @click="detalleCancelado = false" class="success-btn">
                Aceptar
            </button>


        </div>
    </div>
</div>
