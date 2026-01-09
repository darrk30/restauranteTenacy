<div class="pdv" 
    wire:ignore 
    x-data="pedidoMesa(@js($productos), {{ $mesa }}, {{ $pedido ?? 'null' }}, @js($carrito))" 
    x-init="
        // Listeners para eventos del Backend (Livewire)
        $wire.on('pedido-guardado', (e) => {
            loading = false;
            showSuccess = true;
            // Accedemos al primer elemento del array de parámetros que envía Livewire
            const params = e[0] || e; 
            navegarLuego = params.esNuevo;
            orderId = params.orderId;
        });

        $wire.on('pedido-error', (e) => {
            loading = false;
            // Manejo de error visual
            alert(e[0]?.message || 'Ocurrió un error');
        });

        $wire.on('pedido-anulado', () => { 
            loading = false; 
            pedidoCancelado = true; 
        });

        $wire.on('detalle-cancelado', () => { 
            loading = false; 
            detalleCancelado = true; 
        });
        $wire.on('abrir-impresion', (event) => {
            const data = event[0] || event;
            if(data.url) {
                const w = 350; 
                const h = 400;
                const left = (screen.width/2)-(w/2);
                const top = (screen.height/2)-(h/2);
                const nombreVentana = 'Ticket_' + Date.now(); 
                const newWin = window.open(data.url, nombreVentana, 
                    `toolbar=no, location=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=${w}, height=${h}, top=${top}, left=${left}`
                );
                if (newWin) newWin.focus();
            }
        });
    ">

    {{-- Incluimos el script de lógica aquí para asegurar que cargue --}}
    <script>
        // Pega aquí la función pedidoMesa que te envié en el paso anterior
        // O asegúrate de tenerla en un archivo .js cargado en tu layout
    </script>

    <div class="left-panel">

        <div class="product-search">
            <input type="text" placeholder="Buscar producto..." x-model.debounce.300ms="search">
        </div>

        <button class="cart-fab" @click="carritoAbierto = true">
            🛒 <span x-text="carrito.length"></span>
        </button>

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

        <div class="products-container">
            <div class="products-grid">
                <template x-for="producto in productosFiltrados()" :key="producto.id">
                    <div class="product-card" @click="abrirModal(producto)">
                        
                        <div class="product-image">
                            <span class="product-stock" x-show="producto.control_stock">
                                Stock: <span x-text="producto.stock_reserva_total"></span>
                            </span>

                            <img :src="producto.image_path ? `/storage/${producto.image_path}` : '/img/productdefault.jpg'"
                                 alt="Producto">
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

            <div class="empty-state" x-show="productosFiltrados().length === 0" style="display: none;">
                <img src="/img/sinproductos.avif" alt="Sin productos">
                <h3>No se encontraron productos</h3>
                <p>Intenta cambiar la categoría o buscar otro nombre</p>
            </div>
        </div>
    </div>

    <div class="right-panel" :class="{ 'open': carritoAbierto }">
        <button class="close-cart" @click="carritoAbierto = false">✕</button>

        <div class="order-header">
            <div class="order-info">
                <span class="mesa">Mesa {{ $mesa }}</span>
                @if ($pedido)
                    <span class="pedido">
                        Pedido #<span>{{ $pedidocompleto->code }}</span>
                    </span>
                @endif
            </div>

            <div class="order-actions">
                <button class="btn-remove btn-remove-pedido" title="Anular pedido" x-show="pedidoId"
                    x-on:click="abrirModalAnular">
                    <x-heroicon-o-trash class="w-5 h-5" />
                </button>
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
                            <template x-if="item.cortesia">
                                <span class="text-xs text-green-600 font-bold">(Cortesía)</span>
                            </template>
                        </div>
                    </div>

                    <div class="order-actions">
                        <span>
                            S/ <span x-text="(item.precio * item.cantidad).toFixed(2)"></span>
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
                <span>Total Estimado</span> <span>S/ <span x-text="total().toFixed(2)"></span></span>
            </div>

            <button class="order-btn flex items-center justify-center gap-2" 
                :disabled="loading"
                @click="ordenarPedido()">
                
                <template x-if="!loading">
                    <span x-text="pedidoId ? 'ACTUALIZAR PEDIDO' : 'CONFIRMAR ORDEN'"></span>
                </template>

                <template x-if="loading">
                    <span class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Procesando...
                    </span>
                </template>
            </button>
        </div>
    </div>

    <div class="modal-backdrop" x-show="modalAnular" x-transition style="display: none;">
        <div class="modal-confirm">
            <h3>¿Anular pedido?</h3>
            <p>Esta acción no se puede deshacer. La mesa quedará libre.</p>
            <div class="modal-actions">
                <button class="btn cancel" @click="cerrarModalAnular">Cancelar</button>
                <button class="btn danger" @click="confirmarAnular">Anular</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" x-show="modalAnularDetalle" x-transition style="display: none;">
        <div class="modal-confirm">
            <h3>¿Eliminar Item?</h3>
            <p>Se eliminará del pedido y se devolverá al stock.</p>
            <div class="modal-actions">
                <button class="btn cancel" @click="cerrarModalAnularDetalle">Cancelar</button>
                <button class="btn danger" @click="confirmarAnularDetalle">Eliminar</button>
            </div>
        </div>
    </div>

    <template x-if="modal">
        <div class="modal-backdrop moda-variats modal-enter" 
             x-init="$el.classList.add('modal-enter-active')" 
             @click.self="cerrarModal()">
            
            <div class="modal modal-scale">
                <div class="modal-header" x-text="productoActual.name"></div>
                
                <template x-if="imagenVariante">
                    <div class="variant-image-box">
                        <img :src="`/storage/${imagenVariante}`" class="variant-image">
                    </div>
                </template>

                <template x-if="!puedeAgregar()">
                    <div class="stock-alert">Stock insuficiente</div>
                </template>

                <template x-if="productoActual.cortesia">
                    <label class="cortesia-switch">
                        <span class="cortesia-text">Cortesía</span>
                        <input type="checkbox" x-model="esCortesia" class="sr-only">
                        <div class="switch"><div class="switch-dot"></div></div>
                    </label>
                </template>

                <div class="modal-body">
                    <template x-for="group in productoActual.variant_groups" :key="group.attribute">
                        <div>
                            <div class="note-label" x-text="group.attribute"></div>
                            <div class="flex gap-2 flex-wrap">
                                <template x-for="opt in group.options" :key="opt.id">
                                    <button class="variant-btn" 
                                        :class="{ active: variante == opt.id }"
                                        @click="variante = opt.id; imagenVariante = opt.image_path ?? null">
                                        
                                        <span class="variant-label" x-text="opt.label"></span>
                                        
                                        <template x-if="opt.extra_price > 0">
                                            <span class="price-badge">+ S/ <span x-text="opt.extra_price.toFixed(2)"></span></span>
                                        </template>

                                        <template x-if="productoActual.control_stock">
                                            <div class="text-xs text-gray-500 mt-1">Stock: <span x-text="opt.stock_reserva_total_variante"></span></div>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="note-box">
                        <label class="note-label">Notas para cocina</label>
                        <textarea x-model="nota" class="note-input" rows="2" placeholder="Ej: Sin cebolla..."></textarea>
                    </div>

                    <div class="qty-box">
                        <span class="note-label">Cantidad</span>
                        <div class="qty-controls">
                            <button type="button" @click="cantidad = Math.max(1, cantidad - 1)">−</button>
                            <input type="number" x-model.number="cantidad" class="qty-input">
                            <button type="button" @click="cantidad++">+</button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn-cancel" @click="cerrarModal()">Cancelar</button>
                    <button class="btn-confirm" 
                        :disabled="!puedeAgregar()" 
                        @click="agregar()"
                        x-text="editando ? 'Actualizar' : 'Agregar'">
                    </button>
                </div>
            </div>
        </div>
    </template>

    <div x-show="showSuccess" x-transition.opacity class="success-overlay" style="display: none;">
        <div class="success-modal">
            <h2 class="success-title">✅ Pedido registrado</h2>
            <p class="success-text">El pedido fue enviado a cocina.</p>
            <button type="button" class="success-btn"
                @click="
                    showSuccess = false;
                    if (navegarLuego && orderId) Livewire.navigate(`/restaurants/{{ $restaurantSlug }}/orden-mesa/{{ $mesa }}/${orderId}`);
                ">
                Aceptar
            </button>
        </div>
    </div>

    <div x-show="pedidoCancelado" x-transition.opacity class="success-overlay" style="display: none;">
        <div class="success-modal">
            <h2 class="success-title">Pedido Cancelado</h2>
            <button class="success-btn" @click="window.location.href = '/restaurants/{{ $restaurantSlug }}/point-of-sale'">Aceptar</button>
        </div>
    </div>

    <div x-show="detalleCancelado" x-transition.opacity class="success-overlay" style="display: none;">
        <div class="success-modal">
            <h2 class="success-title">Detalle Eliminado</h2>
            <button class="success-btn" @click="detalleCancelado = false">Aceptar</button>
        </div>
    </div>

</div>