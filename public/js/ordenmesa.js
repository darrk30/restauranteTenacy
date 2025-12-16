function pedidoMesa(productosIniciales) {
    return {
        productos: productosIniciales,
        categoria: 'todos',
        search: '',

        modal: false,
        productoActual: null,
        variante: null,
        cantidad: 1,
        carritoAbierto: false,
        editando: false,

        carrito: [],

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


        abrirModal(producto) {
            this.productoActual = producto;
            this.cantidad = 1;
            const firstOption = producto.variant_groups
                ?.flatMap(g => g.options)
                ?.at(0);
            this.variante = firstOption ? firstOption.id : null;
            this.modal = true;
            this.nota = ''
        },

        editarItem(item) {
            this.editando = true
            this.editKey = item.key

            // buscar producto original
            const productoId = Number(item.key.split('-')[0])
            const varianteId = Number(item.key.split('-')[1])

            const producto = this.productos.find(p => p.id === productoId)

            this.productoActual = producto
            this.variante = varianteId
            this.cantidad = item.cantidad
            this.nota = item.nota ?? ''

            this.modal = true
        },



        cerrarModal() {
            this.modal = false
            this.productoActual = null
            this.editando = false
            this.editKey = null
            this.cantidad = 1
            this.nota = ''
        },


        agregar() {
            const option = this.productoActual.variant_groups
                .flatMap(g => g.options)
                .find(o => o.id === this.variante)

            const newKey = `${this.productoActual.id}-${this.variante}`

            // 🟡 SI ESTAMOS EDITANDO
            if (this.editando) {

                const index = this.carrito.findIndex(i => i.key === this.editKey)

                if (index !== -1) {
                    this.carrito[index] = {
                        key: newKey,
                        nombre: this.productoActual.name + ' (' + option.label + ')',
                        precio: this.productoActual.price + option.extra_price,
                        cantidad: this.cantidad,
                        nota: this.nota.trim(),
                    }
                }

            } else {
                // 🟢 AGREGAR NORMAL
                const existente = this.carrito.find(i => i.key === newKey)

                if (existente) {
                    existente.cantidad += this.cantidad
                    existente.nota = this.nota.trim()
                } else {
                    this.carrito.push({
                        key: newKey,
                        nombre: this.productoActual.name + ' (' + option.label + ')',
                        precio: this.productoActual.price + option.extra_price,
                        cantidad: this.cantidad,
                        nota: this.nota.trim(),
                    })
                }
            }

            this.cerrarModal()
        },

        total() {
            return this.carrito.reduce((t, i) => t + i.precio * i.cantidad, 0);
        },

        eliminarItem(key) {
            this.carrito = this.carrito.filter(i => i.key !== key);
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

}
}
