function pedidoMesa(productosIniciales) {
    return {
        loading: false,
        showSuccess: false,
        productos: productosIniciales,
        categoria: 'todos',
        search: '',
        modal: false,
        productoActual: null,
        variante: null,
        cantidad: 1,
        carritoAbierto: false,
        editando: false,
        imagenVariante: null,
        carrito: [],
        esCortesia: false,
        precioBase: 0,


         productosFiltrados() {
            return this.productos.filter(p => {

                const matchCategoria =
                    this.categoria === 'todos' ||
                    p.categories.includes(Number(this.categoria));

                const matchSearch =
                    this.search === '' ||
                    p.name.toLowerCase().includes(this.search.toLowerCase());

                return matchCategoria && matchSearch;
            });
        },

        stockDisponibleVariante() {
            if (!this.variante) return 0

            const option = this.productoActual.variant_groups
                .flatMap(g => g.options)
                .find(o => o.id === this.variante)

            return option ? option.stock_reserva_total_variante : 0
        },


        abrirModal(producto) {
            this.productoActual = producto
            this.cantidad = 1
            this.nota = ''
            this.editando = false
            const firstOption = producto.variant_groups
                ?.flatMap(g => g.options)
                ?.at(0)
            this.variante = firstOption ? firstOption.id : null
            if (firstOption && firstOption.image_path) {
                this.imagenVariante = firstOption.image_path
            } else {
                this.imagenVariante = producto.image_path ?? null
            }
            this.modal = true
            this.esCortesia = false
            this.precioBase = producto.price
        },

        editarItem(item) {
            this.editando = true
            this.editKey = item.key

            const [productoId, varianteId, tipo] = item.key.split('-')

            const producto = this.productos.find(
                p => p.id === Number(productoId)
            )

            if (!producto) return

            this.productoActual = producto

            // 🔥 CLAVES PARA CONTROL DE STOCK
            this.varianteOriginal = Number(varianteId)
            this.cantidadOriginal = item.cantidad

            // valores editables
            this.variante = Number(varianteId)
            this.cantidad = item.cantidad
            this.nota = item.nota ?? ''
            this.esCortesia = tipo === 'cortesia'

            const option = producto.variant_groups
                ?.flatMap(g => g.options)
                ?.find(o => o.id === Number(varianteId))

            this.imagenVariante = option?.image_path ?? producto.image_path ?? null

            this.modal = true
        },




        precioActual() {
            if (this.esCortesia) return 0

            const option = this.productoActual.variant_groups
                .flatMap(g => g.options)
                .find(o => o.id === this.variante)

            return this.precioBase + (option?.extra_price ?? 0)
        },

        puedeAgregar() {
            if (!this.productoActual) return true
            if (!this.variante) return false

            if (!this.productoActual.control_stock) return true
            if (this.productoActual.venta_sin_stock) return true

            return this.stockDisponibleVariante() >= this.cantidad
        },



        cerrarModal() {
            this.modal = false
            this.productoActual = null
            this.editando = false
            this.editKey = null
            this.varianteOriginal = null
            this.cantidadOriginal = null
            this.variante = null
            this.cantidad = 1
            this.nota = ''
            this.imagenVariante = null
        },



        agregar() {
            const opciones = this.productoActual.variant_groups
                .flatMap(g => g.options)

            const optionNueva = opciones.find(o => o.id === this.variante)

            const tipo = this.esCortesia ? 'cortesia' : 'normal'
            const newKey = `${this.productoActual.id}-${this.variante}-${tipo}`

            // =========================
            // EDITANDO
            // =========================
            if (this.editando) {

                const indexOriginal = this.carrito.findIndex(
                    i => i.key === this.editKey
                )

                if (indexOriginal === -1) {
                    this.cerrarModal()
                    return
                }

                const indexExistente = this.carrito.findIndex(
                    i => i.key === newKey
                )

                // 🔥 DEVOLVER STOCK ORIGINAL
                if (this.productoActual.control_stock) {
                    const optionOriginal = opciones.find(
                        o => o.id === this.varianteOriginal
                    )

                    if (optionOriginal) {
                        optionOriginal.stock_reserva_total_variante += this.cantidadOriginal
                    }
                }

                // 🟢 UNIR SI YA EXISTE
                if (indexExistente !== -1 && indexExistente !== indexOriginal) {

                    this.carrito[indexExistente].cantidad += this.cantidadOriginal

                    // eliminar solo el editado
                    this.carrito.splice(indexOriginal, 1)

                } else {
                    // 🟢 EDITAR NORMAL
                    this.carrito[indexOriginal] = {
                        key: newKey,
                        nombre: `${this.productoActual.name} (${optionNueva.label})`,
                        precio: this.esCortesia ? 0 : this.precioActual(),
                        cantidad: this.cantidad,
                        nota: this.nota.trim(),
                        cortesia: this.esCortesia,
                    }
                }

                // 🔥 DESCONTAR STOCK NUEVO
                if (this.productoActual.control_stock && optionNueva) {
                    optionNueva.stock_reserva_total_variante -= this.cantidad
                }
            }

            // =========================
            // AGREGAR NORMAL
            // =========================
            else {
                const existente = this.carrito.find(i => i.key === newKey)

                if (existente) {
                    existente.cantidad += this.cantidad
                    existente.nota = this.nota.trim()
                } else {
                    this.carrito.push({
                        key: newKey,
                        nombre: `${this.productoActual.name} (${optionNueva.label})`,
                        precio: this.esCortesia ? 0 : this.precioActual(),
                        cantidad: this.cantidad,
                        nota: this.nota.trim(),
                        cortesia: this.esCortesia,
                    })
                }

                // 🔥 DESCONTAR STOCK NORMAL
                if (this.productoActual.control_stock && optionNueva) {
                    optionNueva.stock_reserva_total_variante -= this.cantidad
                }
            }

            // 🔁 RECALCULAR STOCK TOTAL DEL PRODUCTO
            this.recalcularStockProducto(this.productoActual)

            this.cerrarModal()
        },


        total() {
            return this.carrito.reduce((t, i) => t + i.precio * i.cantidad, 0);
        },

        eliminarItem(key) {
            const item = this.carrito.find(i => i.key === key)
            if (!item) return

            // 🔥 key = productoId-varianteId-tipo
            const [productoId, varianteId] = key.split('-')

            const producto = this.productos.find(
                p => p.id === Number(productoId)
            )

            if (!producto) return

            const option = producto.variant_groups
                ?.flatMap(g => g.options)
                ?.find(o => o.id === Number(varianteId))

            // 🔁 DEVOLVER STOCK
            if (
                producto.control_stock &&
                option
            ) {
                option.stock_reserva_total_variante += item.cantidad
                this.recalcularStockProducto(producto)
            }

            // ❌ eliminar item del carrito
            this.carrito = this.carrito.filter(i => i.key !== key)
        },

        aumentar(key) {
            this.carrito = this.carrito.map(item => {
                if (item.key === key) {
                    return { ...item, cantidad: item.cantidad + 1 }
                }
                return item
            })
        },

        disminuir(key) {
            this.carrito = this.carrito
                .map(item => {
                    if (item.key === key) {
                        return { ...item, cantidad: item.cantidad - 1 }
                    }
                    return item
                })
                .filter(item => item.cantidad > 0)
        },

        recalcularStockProducto(producto) {
            // Si el producto no controla stock, no hacemos nada
            if (!producto.control_stock) return

            // 1️⃣ Obtener el stock total de cada variante
            const stockPorVariante = producto.variant_groups
                .flatMap(grupo => grupo.options)
                .map(variante => variante.stock_reserva_total_variante)

            // 2️⃣ Verificar si existe al menos una variante con stock positivo
            const existeStockPositivo = stockPorVariante.some(
                stockVariante => stockVariante > 0
            )

            // 3️⃣ Calcular el stock del producto
            if (existeStockPositivo) {
                // Sumar SOLO los stocks positivos
                producto.stock_reserva_total = stockPorVariante
                    .filter(stockVariante => stockVariante > 0)
                    .reduce(
                        (total, stockVariante) => total + stockVariante,
                        0
                    )
            } else {
                // Todas las variantes están en 0 o negativo
                producto.stock_reserva_total = stockPorVariante.reduce(
                    (total, stockVariante) => total + stockVariante,
                    0
                )
            }
        },

        ordenarPedido() {
            if (this.carrito.length === 0) {
                alert('El carrito está vacío')
                return
            }

            const payload = {
                total: this.total(),
                items: this.carrito.map(item => {
                    const [producto_id, variante_id, tipo] = item.key.split('-')
                    return {
                        producto_id: Number(producto_id),
                        variante_id: Number(variante_id),
                        nombre: item.nombre,
                        cantidad: item.cantidad,
                        precio: item.precio,
                        nota: item.nota ?? '',
                        cortesia: tipo === 'cortesia',
                        subtotal: item.precio * item.cantidad,
                    }
                })
            }

            this.$wire.ordenar(payload)
        }




}
}
