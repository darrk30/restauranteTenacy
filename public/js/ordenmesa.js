function pedidoMesa(productosIniciales, mesaId, pedidoId = null, carritoInicial = []) {
    return {
        //  ESTADO INICIAL
        mesaId: mesaId,
        pedidoId: pedidoId,
        loading: false,
        navegarLuego: false,
        orderId: null,
        
        // Modales y Alertas
        showSuccess: false,
        pedidoCancelado: false,
        detalleCancelado: false,
        modal: false,
        modalAnular: false,
        modalAnularDetalle: false,
        
        // Datos del catálogo
        productos: productosIniciales,
        categoria: 'todos',
        search: '',
        
        // Selección de producto
        productoActual: null,
        variante: null,
        cantidad: 1,
        nota: '',
        esCortesia: false,
        imagenVariante: null,
        precioBase: 0,
        
        // Estado del Carrito
        carrito: carritoInicial ?? [],
        carritoAbierto: false,
        editando: false,
        editKey: null,
        varianteOriginal: null,
        cantidadOriginal: 0,
        itemAAnular: null,

        // ==========================================
        //  FILTROS Y BÚSQUEDA
        // ==========================================
        productosFiltrados() {
            const termino = this.search.toLowerCase();
            const cat = this.categoria;
            
            return this.productos.filter(p => {
                const matchCategoria = cat === 'todos' || p.categories.includes(Number(cat));
                const matchSearch = termino === '' || p.name.toLowerCase().includes(termino);
                return matchCategoria && matchSearch;
            });
        },

        // ==========================================
        //  LÓGICA DEL MODAL DE PRODUCTO
        // ==========================================
        abrirModal(producto) {
            this.productoActual = producto;
            this.cantidad = 1;
            this.nota = '';
            this.editando = false;
            this.esCortesia = false;
            this.precioBase = producto.price;

            // Auto-seleccionar la primera variante si existe
            const firstOption = producto.variant_groups?.flatMap(g => g.options)?.at(0);
            
            if (firstOption) {
                this.variante = firstOption.id;
                this.imagenVariante = firstOption.image_path ?? producto.image_path;
            } else {
                this.variante = null; // O manejar producto sin variantes si tu lógica lo permite
                this.imagenVariante = producto.image_path ?? null;
            }

            this.modal = true;
        },

        cerrarModal() {
            this.modal = false;
            // Limpiar estado después de la animación de cierre
            this.$nextTick(() => {
                this.productoActual = null;
                this.variante = null;
                this.cantidad = 1;
                this.nota = '';
                this.imagenVariante = null;
                this.editando = false;
                this.editKey = null;
            });
        },

        //  CALCULOS DE PRECIO Y STOCK (FRONTEND)
        precioActual() {
            if (this.esCortesia) return 0;

            const option = this.productoActual?.variant_groups
                ?.flatMap(g => g.options)
                .find(o => o.id === this.variante);

            return this.precioBase + (option?.extra_price ?? 0);
        },

        stockDisponibleVariante() {
            if (!this.variante || !this.productoActual) return 0;

            const option = this.productoActual.variant_groups
                ?.flatMap(g => g.options)
                .find(o => o.id === this.variante);

            if (!option) return 0;

            let stock = option.stock_reserva_total_variante;

            // Si estamos editando, visualmente sumamos lo que ya tenemos en el carrito
            // para permitir que el usuario mantenga su cantidad actual o la aumente hasta el límite real
            if (this.editando && this.variante === this.varianteOriginal) {
                stock += this.cantidadOriginal;
            }

            return stock;
        },

        puedeAgregar() {
            if (!this.productoActual) return true;
            // Si requiere variante y no hay seleccionada
            if (this.productoActual.variant_groups?.length > 0 && !this.variante) return false;

            // Validaciones de stock
            if (!this.productoActual.control_stock) return true;
            if (this.productoActual.venta_sin_stock) return true;

            return this.stockDisponibleVariante() >= this.cantidad;
        },

        // ==========================================
        //  GESTIÓN DEL CARRITO
        // ==========================================
        editarItem(item) {
            this.editando = true;
            this.editKey = item.key;

            // Parsear Key: ID-VARIANTE-TIPO
            const [productoId, varianteId, tipo] = item.key.split('-');

            const producto = this.productos.find(p => p.id === Number(productoId));
            if (!producto) return;

            this.productoActual = producto;
            this.precioBase = producto.price;
            
            this.varianteOriginal = Number(varianteId);
            this.cantidadOriginal = item.cantidad;

            this.variante = Number(varianteId);
            this.cantidad = item.cantidad;
            this.nota = item.nota ?? '';
            this.esCortesia = (tipo === 'cortesia');

            // Imagen
            const option = producto.variant_groups?.flatMap(g => g.options).find(o => o.id === this.variante);
            this.imagenVariante = option?.image_path ?? producto.image_path ?? null;

            this.modal = true;
        },

        agregar() {
            if (!this.puedeAgregar()) return;

            const opciones = this.productoActual.variant_groups.flatMap(g => g.options);
            const optionNueva = opciones.find(o => o.id === this.variante);
            const tipo = this.esCortesia ? 'cortesia' : 'normal';
            
            // KEY ÚNICA
            const newKey = `${this.productoActual.id}-${this.variante}-${tipo}`;

            // Lógica de Stock Visual (Disminuir)
            const descontarStockVisual = (cant) => {
                if (this.productoActual.control_stock && optionNueva) {
                    optionNueva.stock_reserva_total_variante -= cant;
                }
            };

            // Lógica de Stock Visual (Devolver/Aumentar)
            const devolverStockVisual = (idVariante, cant) => {
                if (this.productoActual.control_stock) {
                    const opt = opciones.find(o => o.id === idVariante);
                    if (opt) opt.stock_reserva_total_variante += cant;
                }
            };

            // --- MODO EDICIÓN ---
            if (this.editando) {
                const indexOriginal = this.carrito.findIndex(i => i.key === this.editKey);
                if (indexOriginal === -1) { this.cerrarModal(); return; }

                // 1. Devolver stock de lo que había antes
                devolverStockVisual(this.varianteOriginal, this.cantidadOriginal);

                // 2. Eliminar item antiguo (temporalmente o reemplazarlo)
                // Verificamos si la nueva configuración ya existe en OTRO item (merge)
                const indexExistente = this.carrito.findIndex(i => i.key === newKey);

                if (indexExistente !== -1 && indexExistente !== indexOriginal) {
                    // FUSIONAR: Sumamos al existente y borramos el editado
                    this.carrito[indexExistente].cantidad += this.cantidad;
                    this.carrito.splice(indexOriginal, 1);
                } else {
                    // REEMPLAZAR in-place
                    this.carrito[indexOriginal] = {
                        key: newKey,
                        nombre: `${this.productoActual.name} (${optionNueva?.label || 'Normal'})`,
                        precio: this.esCortesia ? 0 : this.precioActual(),
                        cantidad: this.cantidad,
                        nota: this.nota.trim(),
                        cortesia: this.esCortesia,
                    };
                }
                
                // 3. Descontar stock de lo nuevo
                descontarStockVisual(this.cantidad);

            } 
            // --- MODO AGREGAR ---
            else {
                const existente = this.carrito.find(i => i.key === newKey);

                if (existente) {
                    existente.cantidad += this.cantidad;
                    // Concatenar notas
                    const notaNueva = this.nota.trim();
                    if (notaNueva) {
                        existente.nota = existente.nota ? `${existente.nota}, ${notaNueva}` : notaNueva;
                    }
                } else {
                    this.carrito.push({
                        key: newKey,
                        nombre: `${this.productoActual.name} (${optionNueva?.label || 'Normal'})`,
                        precio: this.esCortesia ? 0 : this.precioActual(),
                        cantidad: this.cantidad,
                        nota: this.nota.trim(),
                        cortesia: this.esCortesia,
                    });
                }
                descontarStockVisual(this.cantidad);
            }

            // Recalcular totales visuales del producto padre
            this.recalcularStockProducto(this.productoActual);
            this.cerrarModal();
        },

        eliminarItem(key) {
            const item = this.carrito.find(i => i.key === key);
            if (!item) return;

            const [productoId, varianteId] = key.split('-');
            const producto = this.productos.find(p => p.id === Number(productoId));

            if (producto && producto.control_stock) {
                const option = producto.variant_groups?.flatMap(g => g.options).find(o => o.id === Number(varianteId));
                if (option) {
                    // Devolvemos el stock visualmente
                    option.stock_reserva_total_variante += item.cantidad;
                    this.recalcularStockProducto(producto);
                }
            }

            this.carrito = this.carrito.filter(i => i.key !== key);
        },

        // Helper para actualizar el badge de stock total en la tarjeta del producto
        recalcularStockProducto(producto) {
            if (!producto.control_stock) return;
            
            const stocks = producto.variant_groups
                ?.flatMap(g => g.options)
                .map(o => o.stock_reserva_total_variante);
            
            if (!stocks) return;

            const hayPositivos = stocks.some(s => s > 0);
            
            if (hayPositivos) {
                producto.stock_reserva_total = stocks.filter(s => s > 0).reduce((a, b) => a + b, 0);
            } else {
                producto.stock_reserva_total = stocks.reduce((a, b) => a + b, 0);
            }
        },

        total() {
            return this.carrito.reduce((t, i) => t + (i.precio * i.cantidad), 0);
        },

        // ==========================================
        //  INTERACCIÓN CON BACKEND
        // ==========================================
        ordenarPedido() {
            if (this.carrito.length === 0) {
                alert('El carrito está vacío');
                return;
            }
            this.loading = true;

            // Construir Payload Limpio y Seguro
            const payload = {
                mesa_id: this.mesaId,
                pedido_id: this.pedidoId,
                total: this.total(), // Referencial para validación, pero el backend recalcula
                items: this.carrito.map(item => {
                    const [prodId, varId, tipo] = item.key.split('-');
                    return {
                        producto_id: Number(prodId),
                        variante_id: Number(varId),
                        cantidad: item.cantidad,
                        nota: item.nota,
                        cortesia: item.cortesia,
                        // El precio aquí es referencial. El backend usa su propia base de datos.
                        precio_referencial: item.precio 
                    };
                })
            };

            this.$wire.ordenar(payload)
                .catch(error => {
                    this.loading = false;
                    console.error('Error en pedido:', error);
                    alert('Hubo un problema al guardar el pedido. Por favor intente nuevamente.');
                });
        },

        // ==========================================
        //  ACCIONES DE ANULACIÓN
        // ==========================================
        abrirModalAnular() { this.modalAnular = true; },
        cerrarModalAnular() { this.modalAnular = false; },

        confirmarAnular() {
            if (!this.pedidoId) return;
            this.loading = true;
            this.$wire.anularPedido(this.pedidoId).then(() => {
                this.modalAnular = false;
            });
        },

        abrirModalAnularDetalle(item) {
            this.itemAAnular = item;
            this.modalAnularDetalle = true;
        },
        cerrarModalAnularDetalle() {
            this.modalAnularDetalle = false;
            this.itemAAnular = null;
        },

        confirmarAnularDetalle() {
            if (!this.itemAAnular) return;

            // 1. Quitar visualmente del carrito (y devolver stock visual)
            this.eliminarItem(this.itemAAnular.key);

            // 2. Si es un item guardado en BD (tiene detail_id), notificar al backend
            if (this.itemAAnular.detail_id) {
                this.loading = true;
                this.$wire.cancelarDetalle(this.itemAAnular.detail_id)
                    .then(() => {
                        this.loading = false;
                    });
            }

            this.cerrarModalAnularDetalle();
        }
    };
}