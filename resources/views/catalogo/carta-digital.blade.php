<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú - {{ $tenant->name ?? 'Kipu' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/CartStore.js') }}" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.css" />
    <script src="https://unpkg.com/leaflet-geosearch@3.11.0/dist/bundle.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/slider.css') }}">
</head>

<body class="bg-gray-50">
    <x-header :tenant="$tenant ?? null" :cartCount="0" />
    <x-cart-sidebar :mesaId="$mesaId ?? null" :metodosPago="$metodosPago" :ofreceDelivery="$ofreceDelivery" :ofreceRecojo="$ofreceRecojo" :guardarPedidosWeb="$guardarPedidosWeb" />

    <main class="max-w-6xl mx-auto pt-4 md:pt-8">
        <x-slider :slides="$promociones" :interval="4000" />
        <x-category-filter :categories="$categorias" />
        <x-product-grid :products="$productos" />
        <div x-data x-show="$store.toast.show" x-transition x-cloak
            class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[999]">

            <div :class="$store.toast.type === 'success' ?
                'bg-green-600' :
                'bg-red-600'"
                class="text-white px-6 py-3 rounded-xl shadow-xl font-semibold">

                <span x-text="$store.toast.message"></span>
            </div>
        </div>
    </main>
    @push('js')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('cartSidebarComponent', (mesaId = null) => ({
                    mesaId: mesaId,
                    confirmModal: false,
                    wspModal: false,
                    showCheckoutForm: false,
                    isProcessing: false,
                    activeVariant: null,

                    form: {
                        tipo: 'delivery',
                        nombre: '',
                        telefono: '',
                        direccion: '',
                        metodo_pago: '',
                        notas: ''
                    },

                    resetForm() {
                        this.form = {
                            tipo: 'delivery',
                            nombre: '',
                            telefono: '',
                            direccion: '',
                            metodo_pago: '',
                            notas: ''
                        };
                    },
                    @if ($guardarPedidosWeb)
                        async submitOrder(mesa = null) {
                            if (this.isProcessing) return;
                            this.isProcessing = true;

                            try {
                                const response = await fetch('/carta/procesar-pedido', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector(
                                            'meta[name="csrf-token"]').content
                                    },
                                    body: JSON.stringify({
                                        mesa_id: mesa ?? this.mesaId,
                                        tipo_pedido: this.form.tipo,
                                        cliente_nombre: this.form.nombre,
                                        cliente_telefono: this.form.telefono,
                                        cliente_direccion: this.form.direccion,
                                        metodo_pago: this.form.metodo_pago,
                                        notas: this.form.notas,
                                        items: Alpine.store('cart').items
                                    })
                                });

                                const data = await response.json();

                                if (!data.success) {
                                    Alpine.store('toast').trigger(data.message || 'Error al procesar',
                                        'error');
                                    return;
                                }

                                if (mesa || this.mesaId) {
                                    Alpine.store('toast').trigger('Pedido enviado a cocina 🍽️');
                                    Alpine.store('cart').clear();
                                    this.showCheckoutForm = false;
                                    this.resetForm();
                                    return;
                                }

                                if (data.whatsapp_url) {
                                    window.open(data.whatsapp_url, '_blank');
                                }

                                Alpine.store('cart').clear();
                                this.showCheckoutForm = false;
                                this.resetForm();
                                Alpine.store('toast').trigger('Pedido enviado correctamente 🚀');

                            } catch (error) {
                                Alpine.store('toast').trigger('Error de conexión', 'error');
                            } finally {
                                this.isProcessing = false;
                            }
                        }
                    @else
                        async submitOrder(mesa = null) {
                            if (this.isProcessing) return;
                            this.isProcessing = true;

                            try {
                                const response = await fetch('/carta/procesar-wsp', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector(
                                            'meta[name="csrf-token"]').content
                                    },
                                    body: JSON.stringify({
                                        items: Alpine.store('cart').items,
                                        mesa_id: this.mesaId,
                                        tipo_pedido: this.form.tipo,
                                        cliente_nombre: this.form.nombre,
                                        cliente_telefono: this.form.telefono,
                                        cliente_direccion: this.form.direccion,
                                        metodo_pago: this.form.metodo_pago,
                                        notas: this.form.notas
                                    })
                                });

                                const data = await response.json();

                                if (!data.success) {
                                    Alpine.store('toast').trigger(data.message ||
                                        'Error al generar enlace', 'error');
                                    return;
                                }

                                window.open(data.whatsapp_url, '_blank');

                                this.wspModal = false;
                                this.showCheckoutForm = false;
                                Alpine.store('cart').clear();
                                Alpine.store('cart').toggleSidebar();
                                this.resetForm();
                                Alpine.store('toast').trigger('Redirigiendo a WhatsApp... 🚀');

                            } catch (error) {
                                Alpine.store('toast').trigger('Error de conexión', 'error');
                            } finally {
                                this.isProcessing = false;
                            }
                        }
                    @endif
                }));

                Alpine.data('leafletMapComponent', () => ({
                    map: null,
                    marker: null,
                    searchControl: null, // Guardamos la referencia del buscador

                    initLeaflet() {
                        const backupPos = [-12.0463, -77.0427]; // Coordenadas por defecto (Lima)

                        setTimeout(() => {
                            this.map = L.map('map-leaflet').setView(backupPos, 15);

                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '© OpenStreetMap'
                            }).addTo(this.map);

                            this.marker = L.marker(backupPos, {
                                draggable: true
                            }).addTo(this.map);

                            // --- CONFIGURACIÓN DEL BUSCADOR ---
                            const provider = new window.GeoSearch.OpenStreetMapProvider({
                                params: {
                                    'accept-language': 'es', // Resultados en español
                                    countrycodes: 'pe', // 🇵🇪 Restringir solo a Perú (cámbialo si es otro país)
                                },
                            });

                            this.searchControl = new window.GeoSearch.GeoSearchControl({
                                provider: provider,
                                style: 'bar', // Estilo barra de búsqueda
                                showMarker: false, // No crear un nuevo marcador (usaremos el nuestro)
                                showPopup: false,
                                autoClose: true,
                                retainZoomLevel: false,
                                animateZoom: true,
                                keepResult: true,
                                searchLabel: 'Escribe tu calle o lugar...',
                            });

                            this.map.addControl(this.searchControl);

                            // --- EVENTO: CUANDO EL USUARIO SELECCIONA UN RESULTADO DEL BUSCADOR ---
                            this.map.on('geosearch/showlocation', (result) => {
                                const pos = {
                                    lat: result.location.y,
                                    lng: result.location.x
                                };
                                this.marker.setLatLng(pos);
                                this.form.direccion = result.location
                                    .label; // Actualizamos el input
                            });

                            // --- EVENTOS MANUALES (CLIC Y DRAG) ---
                            this.marker.on('dragend', () => {
                                const pos = this.marker.getLatLng();
                                this.getAddress(pos.lat, pos.lng);
                            });

                            this.map.on('click', (e) => {
                                this.marker.setLatLng(e.latlng);
                                this.getAddress(e.latlng.lat, e.latlng.lng);
                            });

                            this.locateUser();
                        }, 400);
                    },

                    // ... (Mantenemos locateUser y getAddress igual que antes)
                    locateUser() {
                        if (!navigator.geolocation) return;

                        // Forzamos alta precisión para móviles
                        const options = {
                            enableHighAccuracy: true,
                            timeout: 5000,
                            maximumAge: 0
                        };

                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                const pos = {
                                    lat: position.coords.latitude,
                                    lng: position.coords.longitude
                                };
                                this.map.setView(pos, 17); // Zoom más cercano
                                this.marker.setLatLng(pos);
                                this.getAddress(pos.lat, pos.lng);
                            },
                            (error) => {
                                console.error("Error detectado:", error.message);
                                // Intento de respaldo si falla el GPS fino
                                this.map.locate({
                                    setView: true,
                                    maxZoom: 17
                                });
                            },
                            options
                        );
                    },

                    async getAddress(lat, lng) {
                        try {
                            // Añadimos 'addressdetails=1' para que nos separe calle, número, etc.
                            const response = await fetch(
                                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`
                            );
                            const data = await response.json();

                            if (data.address) {
                                const a = data.address;
                                // Construimos una dirección "limpia" (Calle + Número o Proximidad)
                                const calle = a.road || a.suburb || '';
                                const numero = a.house_number ? ` ${a.house_number}` : '';
                                const distrito = a.city_district || a.district || a.town || '';

                                // Si Nominatim no encuentra calle, usamos el display_name como respaldo
                                const direccionCorta = calle ? `${calle}${numero}, ${distrito}` : data
                                    .display_name;

                                // IMPORTANTE: Asegúrate de que 'this.form' sea accesible desde este componente
                                // Si leafletMapComponent está separado de cartSidebarComponent, 
                                // usa un evento para pasar la dirección:
                                this.form.direccion = direccionCorta;

                                // Opcional: Emitir evento por si otro componente lo necesita
                                window.dispatchEvent(new CustomEvent('direccion-actualizada', {
                                    detail: direccionCorta
                                }));
                            }
                        } catch (error) {
                            console.error("Error al obtener dirección:", error);
                        }
                    }
                }));


            });
        </script>
    @endpush
    @stack('js')
</body>

</html>
