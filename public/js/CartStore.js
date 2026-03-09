/**
 * MOTOR UNIFICADO DE LA CARTA DIGITAL (Archivo JS Externo)
 */

document.addEventListener('alpine:init', () => {

    // ==========================================
    // 1. STORE GLOBAL DE NOTIFICACIONES (TOAST)
    // ==========================================
    Alpine.store('toast', {
        show: false,
        message: '',
        type: 'success',

        trigger(message, type = 'success') {
            this.message = message;
            this.type = type;
            this.show = true;

            setTimeout(() => {
                this.show = false;
            }, 2500);
        }
    });

    // ==========================================
    // 2. STORE GLOBAL DEL CARRITO
    // ==========================================
    Alpine.store('cart', {
        items: [],
        isSidebarOpen: false,

        add(product) {
            const existing = this.items.find(i => i.cartItemId === product.cartItemId);
            if (existing) {
                existing.qty += product.qty;
            } else {
                this.items.push(product);
            }
            Alpine.store('toast').trigger('Producto agregado al carrito 🛒');
        },

        remove(cartItemId) {
            this.items = this.items.filter(i => i.cartItemId !== cartItemId);
        },

        changeQty(cartItemId, delta) {
            const item = this.items.find(i => i.cartItemId === cartItemId);
            if (!item) return;

            item.qty += delta;
            if (item.qty <= 0) this.remove(cartItemId);
        },

        get count() {
            return this.items.reduce((sum, item) => sum + item.qty, 0);
        },

        get total() {
            return this.items.reduce((sum, item) => sum + (item.price * item.qty), 0);
        },

        toggleSidebar() {
            this.isSidebarOpen = !this.isSidebarOpen;
            document.body.style.overflow = this.isSidebarOpen ? 'hidden' : 'auto';
        },

        clear() {
            this.items = [];
        }
    });

    // ==========================================
    // 3. MODAL DE PRODUCTO (Opciones y Variantes)
    // ==========================================
    Alpine.data('productModalComponent', () => ({
        isOpen: false,
        product: {},
        qty: 1,
        selectedOptions: {},

        get basePrice() {
            if (!this.product.p_hash) return 0;
            try {
                return parseFloat(atob(this.product.p_hash)) || 0;
            } catch {
                return 0;
            }
        },

        get activeVariant() {
            if (!this.product.variants || this.product.variants.length === 0) return null;
            
            let selectedNames = [];
            Object.values(this.product.attributes || {}).forEach(attr => {
                let optId = this.selectedOptions[attr.id];
                if (optId && attr.options) {
                    let opt = Object.values(attr.options).find(o => o.id == optId);
                    if (opt) selectedNames.push(opt.name);
                }
            });
            
            return this.product.variants.find(v => {
                if (!v.name) return false;
                return selectedNames.every(n => v.name.includes(n));
            });
        },

        get displayImage() {
            if (this.activeVariant && this.activeVariant.image) return this.activeVariant.image;
            return this.product.images?.[0] || null;
        },

        get variantsTotal() {
            let total = 0;
            Object.values(this.product.attributes || {}).forEach(attr => {
                const opt = Object.values(attr.options || {})
                    .find(o => o.id == this.selectedOptions[attr.id]);
                if (opt) total += parseFloat(opt.price) || 0;
            });
            return total;
        },

        get total() {
            return ((this.basePrice + this.variantsTotal) * this.qty).toFixed(2);
        },

        openModal(productData) {
            this.product = productData;
            this.qty = 1;
            this.selectedOptions = {};

            Object.values(this.product.attributes || {}).forEach(attr => {
                if (attr.options?.[0]) {
                    this.selectedOptions[attr.id] = attr.options[0].id;
                }
            });

            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            this.isOpen = false;
            document.body.style.overflow = 'auto';
        },

        addToCart() {
            let optionValues = [];
            let cartVariantIds = [];
            
            Object.values(this.product.attributes || {}).forEach(attr => {
                let opt = Object.values(attr.options || {}).find(o => o.id == this.selectedOptions[attr.id]);
                if (opt) { 
                    optionValues.push(opt.name); 
                    cartVariantIds.push(opt.id); 
                }
            });

            let finalName = this.product.name;
            if (optionValues.length > 0) {
                finalName += ' (' + optionValues.join(' / ') + ')';
            }

            let cartItemId = this.product.id + (cartVariantIds.length > 0 ? '_' + cartVariantIds.sort().join('_') : '');

            Alpine.store('cart').add({
                cartItemId: cartItemId,
                id: this.product.id,
                name: finalName,
                price: this.basePrice + this.variantsTotal,
                basePrice: this.basePrice,
                image: this.displayImage,
                qty: this.qty,
                variant_id: this.activeVariant ? this.activeVariant.id : null
            });

            this.closeModal();
        }
    }));
});


// ==========================================
// 4. LÓGICA DE DOM (Filtros, Buscador, Ordenamiento y Slider)
// ==========================================
document.addEventListener('DOMContentLoaded', () => {

    // --- VARIABLES DE DOM ---
    const searchInputs = document.querySelectorAll('.search-input'); // Captura el de PC y el Móvil
    const categoryButtons = document.querySelectorAll('.category-btn');
    const products = Array.from(document.querySelectorAll('.product-card'));
    const gridContainer = document.getElementById('product-grid');
    
    let currentCategory = 'todos';

    // --- FUNCIÓN DE FILTRADO ---
    const filterProducts = (searchValue) => {
        const search = (searchValue || '').toLowerCase().trim();

        products.forEach(p => {
            const matchesCat = currentCategory === 'todos' || p.dataset.categories.includes(currentCategory);
            const matchesSearch = p.dataset.name.toLowerCase().includes(search);
            p.style.display = (matchesCat && matchesSearch) ? 'flex' : 'none';
        });
    };

    // --- EVENTOS DEL BUSCADOR (Sincroniza PC y Móvil) ---
    searchInputs.forEach(input => {
        input.addEventListener('input', (e) => {
            searchInputs.forEach(i => { 
                if (i !== e.target) i.value = e.target.value; 
            });
            filterProducts(e.target.value);
        });
    });

    // --- EVENTOS DE CATEGORÍAS ---
    categoryButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Apagar todos
            categoryButtons.forEach(b => {
                b.classList.remove('bg-[#ce6439]', 'text-white', 'border-transparent');
                b.classList.add('bg-white', 'border-gray-200', 'text-gray-600');
            });
            
            // Encender el actual
            const target = e.currentTarget;
            target.classList.remove('bg-white', 'border-gray-200', 'text-gray-600');
            target.classList.add('bg-[#ce6439]', 'text-white', 'border-transparent');
            
            currentCategory = target.dataset.filter;
            
            // Mantener la búsqueda si hay algo escrito
            const currentSearchValue = searchInputs.length > 0 ? searchInputs[0].value : '';
            filterProducts(currentSearchValue);
        });
    });

// --- ORDENAMIENTO DE PRODUCTOS ---
    const sortBtn = document.getElementById('sort-btn');
    const sortDropdown = document.getElementById('sort-dropdown');
    const sortOptions = document.querySelectorAll('.sort-option');
    const sortText = document.getElementById('sort-text');
    const sortIcon = document.getElementById('sort-icon');

    if (sortBtn && sortDropdown && gridContainer) {
        
        // 🟢 1. LA FOTO ORIGINAL: Guardamos el estado exacto de cómo cargó la web
        const originalProducts = Array.from(gridContainer.querySelectorAll('.product-card'));
        
        // Creamos una copia para poder jugar con ella sin arruinar la original
        let productsToSort = [...originalProducts];

        sortBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = sortDropdown.classList.contains('hidden');
            if (isHidden) {
                sortDropdown.classList.remove('hidden');
                setTimeout(() => {
                    sortDropdown.classList.remove('opacity-0', '-translate-y-2');
                    sortIcon.classList.add('rotate-180');
                }, 10);
            } else {
                closeSortDropdown();
            }
        });

        sortOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                const sortMode = e.target.dataset.sort;
                sortText.innerText = e.target.innerText;

                // 🟢 2. MODO RESET: Limpia todo y restaura la web a su estado original
                if (sortMode === 'default') {
                    gridContainer.innerHTML = '';
                    
                    // Restauramos los productos originales intactos
                    originalProducts.forEach(p => {
                        // Nos aseguramos de que vuelvan a ser visibles si estaban ocultos por filtros
                        p.style.display = ''; 
                        gridContainer.appendChild(p);
                    });
                    
                    // Limpiamos los filtros de categorías simulando un clic en "Todos"
                    const btnTodos = document.querySelector('button[data-filter="todos"]');
                    if (btnTodos) btnTodos.click();

                    closeSortDropdown();
                    return; // Terminamos aquí, no seguimos ordenando
                }

                // 3. MODO ORDENAR: Ordenamos la copia de trabajo
                productsToSort.sort((a, b) => {
                    const priceA = parseFloat(a.dataset.price) || 0;
                    const priceB = parseFloat(b.dataset.price) || 0;
                    const nameA = a.dataset.name || '';
                    const nameB = b.dataset.name || '';

                    if (sortMode === 'price_asc') return priceA - priceB;
                    if (sortMode === 'price_desc') return priceB - priceA;
                    if (sortMode === 'name_asc') return nameA.localeCompare(nameB);
                    if (sortMode === 'name_desc') return nameB.localeCompare(nameA);
                    
                    return 0;
                });

                // Pintamos los elementos ordenados
                gridContainer.innerHTML = '';
                productsToSort.forEach(p => gridContainer.appendChild(p));
                
                closeSortDropdown();
            });
        });

        function closeSortDropdown() {
            sortDropdown.classList.add('opacity-0', '-translate-y-2');
            sortIcon.classList.remove('rotate-180');
            setTimeout(() => sortDropdown.classList.add('hidden'), 200);
        }

        document.addEventListener('click', (e) => {
            if (!sortBtn.contains(e.target) && !sortDropdown.classList.contains('hidden')) {
                closeSortDropdown();
            }
        });
    }

    // --- SLIDER MEJORADO ---
    document.querySelectorAll('.app-slider-wrapper').forEach(slider => {
        const track = slider.querySelector('.slider-track');
        const dots = slider.querySelectorAll('.dot');
        const prevBtn = slider.querySelector('.prev-btn');
        const nextBtn = slider.querySelector('.next-btn');
        const interval = parseInt(slider.dataset.interval) || 4000;

        let index = 0;

        function goTo(i) {
            index = i;
            track.scrollTo({ left: index * track.clientWidth, behavior: 'smooth' });
            dots.forEach((d, idx) => {
                d.classList.toggle('w-10', idx === index);
                d.classList.toggle('bg-white', idx === index);
            });
        }

        nextBtn?.addEventListener('click', () => {
            index = (index + 1) % track.children.length;
            goTo(index);
        });

        prevBtn?.addEventListener('click', () => {
            index = (index - 1 + track.children.length) % track.children.length;
            goTo(index);
        });

        dots.forEach(dot => {
            dot.addEventListener('click', () => goTo(parseInt(dot.dataset.index)));
        });

        setInterval(() => {
            index = (index + 1) % track.children.length;
            goTo(index);
        }, interval);
    });

});