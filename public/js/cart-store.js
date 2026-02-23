document.addEventListener('alpine:init', () => {
        // Estado Global del Carrito
    Alpine.store('cart', {
        items: [],
        isSidebarOpen: false,

        // Métodos del carrito
        add(product) {
            const existing = this.items.find(i => i.id === product.id);
            if (existing) {
                existing.qty += product.qty;
            } else {
                this.items.push(product);
            }
        },
        remove(id) {
            this.items = this.items.filter(i => i.id !== id);
        },
        changeQty(id, delta) {
            const item = this.items.find(i => i.id === id);
            if (item) {
                item.qty += delta;
                if (item.qty <= 0) this.remove(id);
            }
        },
        
        // Calculadoras automáticas
        get count() {
            return this.items.reduce((sum, item) => sum + item.qty, 0);
        },
        get total() {
            return this.items.reduce((sum, item) => sum + (item.price * item.qty), 0);
        },

        // Control de UI
        toggleSidebar() {
            this.isSidebarOpen = !this.isSidebarOpen;
            document.body.style.overflow = this.isSidebarOpen ? 'hidden' : 'auto';
        }
    });
});