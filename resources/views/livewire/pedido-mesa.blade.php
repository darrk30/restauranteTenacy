<div x-data="pedidoMesa(@js($productos))" class="pdv">
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
                            <span class="product-stock">Stock: 12</span>
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

        <div class="order-header" style="display: flex; align-items: center; justify-content: center;">
            <span>Mesa {{ $mesa }}</span>
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

                        <button class="btn-remove" @click="eliminarItem(item.key)">
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

            <button class="order-btn">
                ORDENAR
            </button>
        </div>
    </div>

    <!-- ================= MODAL ================= -->
    <template x-if="modal">
        <div class="modal-backdrop">

            <div class="modal">

                <div class="modal-header" x-text="productoActual.name"></div>

                <div class="modal-body">

                    <!-- VARIANTES AGRUPADAS -->
                    <template x-for="group in productoActual.variant_groups" :key="group.attribute">
                        <div class="mb-4">
                            <div class="font-semibold mb-2" x-text="group.attribute"></div>

                            <div class="flex gap-2 flex-wrap">
                                <template x-for="opt in group.options" :key="opt.id">
                                    <button class="variant-btn" :class="{ active: variante == opt.id }"
                                        @click="variante = opt.id">

                                        <span class="variant-label" x-text="opt.label"></span>

                                        <template x-if="opt.extra_price > 0">
                                            <span class="price-badge">
                                                + S/ <span x-text="opt.extra_price.toFixed(2)"></span>
                                            </span>
                                        </template>

                                    </button>

                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- NOTAS PARA COCINA -->
                    <div class="note-box">
                        <label class="note-label">Notas para cocina</label>
                        <textarea x-model="nota" placeholder="Ej: sin cebolla, bien cocido, poco picante..." class="note-input" rows="2"></textarea>
                    </div>


                    <!-- CANTIDAD -->
                    <div class="qty-box">
                        <span>Cantidad</span>

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
                    <button class="btn-confirm" :disabled="!variante" @click="agregar()">
                        <span x-text="editando ? 'Actualizar' : 'Agregar'"></span>
                    </button>
                </div>

            </div>
        </div>
    </template>
</div>
