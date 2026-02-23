<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú - {{ $tenant->name ?? 'Kipu' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-50">
    <x-header :tenant="$tenant ?? null" :cartCount="0" />
    <x-cart-sidebar />

    <main class="max-w-6xl mx-auto pt-4 md:pt-8">
        <x-slider :slides="$promociones" :interval="4000" />
        <x-category-filter :categories="$categorias" />
        <x-product-grid :products="$productos" />
    </main>

    <script>
        // ==========================================
        // 1. MOTOR DEL CARRITO (CON ALPINE.JS)
        // ==========================================
        document.addEventListener('alpine:init', () => {
            Alpine.store('cart', {
                items: [],
                isSidebarOpen: false,

                // Agregar producto
                add(product) {
                    const existing = this.items.find(i => i.id === product.id);
                    if (existing) {
                        existing.qty += product.qty;
                    } else {
                        this.items.push(product);
                    }
                },
                // Eliminar producto por completo
                remove(id) {
                    this.items = this.items.filter(i => i.id !== id);
                },
                // Cambiar cantidad (+ o -)
                changeQty(id, delta) {
                    const item = this.items.find(i => i.id === id);
                    if (item) {
                        item.qty += delta;
                        if (item.qty <= 0) this.remove(id);
                    }
                },
                // Calcular total de items (para el badge del header)
                get count() {
                    return this.items.reduce((sum, item) => sum + item.qty, 0);
                },
                // Calcular precio total
                get total() {
                    return this.items.reduce((sum, item) => sum + (item.price * item.qty), 0);
                },
                // Abrir/Cerrar el sidebar
                toggleSidebar() {
                    this.isSidebarOpen = !this.isSidebarOpen;
                    document.body.style.overflow = this.isSidebarOpen ? 'hidden' : 'auto';
                }
            });
        });

        // ==========================================
        // 2. LÓGICA DE FILTROS Y ORDENAMIENTO (Vanilla JS)
        // ==========================================
        document.addEventListener('DOMContentLoaded', () => {

            // Filtros y Buscador
            const searchInputs = document.querySelectorAll('input[type="text"]');
            const categoryButtons = document.querySelectorAll('.category-btn');
            const sortBtn = document.getElementById('sort-btn');
            const sortDropdown = document.getElementById('sort-dropdown');
            const sortOptions = document.querySelectorAll('.sort-option');
            const sortText = document.getElementById('sort-text');
            const sortIcon = document.getElementById('sort-icon');
            const products = Array.from(document.querySelectorAll('.product-card'));
            const gridContainer = document.getElementById('product-grid');
            const noResultsMsg = document.getElementById('no-results');

            products.forEach((p, index) => p.setAttribute('data-index', index));

            let currentCategory = 'todos';
            let currentSearch = '';

            const filterProducts = () => {
                let visibleCount = 0;
                products.forEach(product => {
                    const name = product.getAttribute('data-name');
                    const category = product.getAttribute('data-category');
                    const matchesCategory = currentCategory === 'todos' || category === currentCategory;
                    const matchesSearch = name.includes(currentSearch);

                    if (matchesCategory && matchesSearch) {
                        product.style.display = 'flex';
                        visibleCount++;
                    } else {
                        product.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    gridContainer.classList.add('hidden');
                    noResultsMsg.classList.remove('hidden');
                } else {
                    gridContainer.classList.remove('hidden');
                    noResultsMsg.classList.add('hidden');
                }
            };

            searchInputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    currentSearch = e.target.value.toLowerCase().trim();
                    filterProducts();
                });
            });

            categoryButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const targetBtn = e.currentTarget;
                    categoryButtons.forEach(b => {
                        b.classList.remove('bg-[#0f643b]', 'text-white');
                        b.classList.add('bg-white', 'text-gray-600');
                    });
                    targetBtn.classList.remove('bg-white', 'text-gray-600');
                    targetBtn.classList.add('bg-[#0f643b]', 'text-white');

                    currentCategory = targetBtn.getAttribute('data-filter').toLowerCase();
                    filterProducts();
                });
            });

            if (sortBtn && sortDropdown) {
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
                        const sortMode = e.target.getAttribute('data-sort');
                        const sortLabel = e.target.innerText;
                        sortText.innerText = sortMode === 'default' ? 'Ordenar por' : sortLabel;

                        products.sort((a, b) => {
                            const priceA = parseFloat(a.getAttribute('data-price'));
                            const priceB = parseFloat(b.getAttribute('data-price'));
                            const nameA = a.getAttribute('data-name');
                            const nameB = b.getAttribute('data-name');
                            const indexA = parseInt(a.getAttribute('data-index'));
                            const indexB = parseInt(b.getAttribute('data-index'));

                            if (sortMode === 'price_asc') return priceA - priceB;
                            if (sortMode === 'price_desc') return priceB - priceA;
                            if (sortMode === 'name_asc') return nameA.localeCompare(nameB);
                            if (sortMode === 'name_desc') return nameB.localeCompare(nameA);
                            return indexA - indexB;
                        });

                        gridContainer.innerHTML = '';
                        products.forEach(p => gridContainer.appendChild(p));
                        closeSortDropdown();
                    });
                });

                function closeSortDropdown() {
                    sortDropdown.classList.add('opacity-0', '-translate-y-2');
                    sortIcon.classList.remove('rotate-180');
                    setTimeout(() => {
                        sortDropdown.classList.add('hidden');
                    }, 200);
                }

                document.addEventListener('click', (e) => {
                    if (!sortBtn.contains(e.target) && !sortDropdown.contains(e.target) && !sortDropdown.classList.contains('hidden')) {
                        closeSortDropdown();
                    }
                });
            }
        });
    </script>
</body>
</html>