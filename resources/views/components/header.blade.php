@props(['tenant' => null, 'cartCount' => 0])

<header x-data="{ showMobileSearch: false }" class="bg-white shadow-sm sticky top-0 z-50 w-full">
    
    {{-- 1. BARRA PRINCIPAL --}}
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between relative gap-4 bg-white z-50">

        <div class="flex items-center justify-start gap-2 flex-1 md:flex-none md:w-1/3">
            @if ($tenant && $tenant->logo)
                <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}"
                    class="h-8 md:h-10 object-contain">
            @else
                <h1 class="text-xl md:text-2xl font-extrabold text-[#ce6439] tracking-tight">
                    {{ $tenant->name ?? 'Kipu' }}
                </h1>
            @endif
        </div>

        {{-- Búsqueda Escritorio --}}
        <div class="hidden md:flex flex-1 justify-center max-w-lg w-full">
            <div class="relative w-full group">
                <input type="text" placeholder="Buscar en la carta..."
                    class="search-input w-full bg-gray-100/80 border border-transparent rounded-full py-2 pl-10 pr-4 focus:outline-none focus:bg-white focus:border-[#ce6439] focus:ring-4 focus:ring-green-50 text-sm transition-all">
                <svg class="w-5 h-5 absolute left-3 top-2.5 text-gray-400 group-focus-within:text-[#ce6439] transition-colors"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 md:gap-4 flex-1 md:flex-none md:w-1/3">

            {{-- Botón Lupa Móvil (Activa el buscador flotante) --}}
            <button @click="showMobileSearch = !showMobileSearch" 
                    :class="showMobileSearch ? 'text-[#ce6439] bg-green-50 rounded-full' : 'text-gray-600'"
                    class="md:hidden hover:text-[#ce6439] p-2 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </button>

            <button x-data @click="$store.cart.toggleSidebar()"
                class="relative text-gray-700 hover:text-[#ce6439] transition-colors p-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>

                <span x-show="$store.cart.count > 0" x-text="$store.cart.count > 99 ? '99+' : $store.cart.count"
                    style="display: none;"
                    class="absolute top-0 right-0 bg-red-500 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center translate-x-1 -translate-y-1 border-2 border-white"
                    x-transition>
                </span>
            </button>
        </div>
    </div>

    {{-- 2. BARRA DE BÚSQUEDA MÓVIL FLOTANTE --}}
    <div x-show="showMobileSearch" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="md:hidden absolute top-[64px] left-0 w-full bg-white border-b border-gray-100 shadow-lg z-40 px-4 py-3" style="display: none;">
        <div class="relative w-full group">
            <input type="text" placeholder="Buscar en la carta..."
                class="search-input w-full bg-gray-50 border border-gray-200 rounded-full py-2.5 pl-10 pr-4 focus:outline-none focus:bg-white focus:border-[#ce6439] focus:ring-2 focus:ring-green-50 text-sm transition-all"
                x-ref="mobileInput">
            <svg class="w-5 h-5 absolute left-3 top-3 text-gray-400 group-focus-within:text-[#ce6439] transition-colors"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
    </div>

    {{-- 3. BARRA DE UBICACIÓN Y CONTACTO (SUB-HEADER) --}}
    @if ($tenant && ($tenant->address || $tenant->phone))
    <div class="bg-gray-50 border-b border-gray-200 relative z-30">
        <div class="max-w-6xl mx-auto px-4 py-2 flex flex-wrap items-center justify-center md:justify-start gap-x-5 gap-y-2">
            
            {{-- Ubicación (Google Maps) --}}
            @if($tenant->address)
            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($tenant->address . ', ' . $tenant->name) }}" 
               target="_blank"
               class="flex items-center gap-1.5 text-xs md:text-sm text-gray-600 hover:text-[#ce6439] transition-colors group">
                <svg class="w-4 h-4 text-[#ce6439] group-hover:-translate-y-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span class="font-medium truncate max-w-[200px] md:max-w-md">{{ $tenant->address }}</span>
            </a>
            @endif

            {{-- Divisor (Solo aparece en escritorio si están ambos datos) --}}
            @if($tenant->address && $tenant->phone)
            <div class="hidden sm:block w-px h-4 bg-gray-300"></div>
            @endif

            {{-- Contacto (WhatsApp Directo) --}}
            @if($tenant->phone)
            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $tenant->phone) }}" 
               target="_blank"
               class="flex items-center gap-1.5 text-xs md:text-sm text-gray-600 hover:text-[#25D366] transition-colors group">
                <svg class="w-4 h-4 text-[#25D366] group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zM6.654 20.193c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" />
                </svg>
                <span class="font-medium">{{ $tenant->phone }}</span>
            </a>
            @endif

        </div>
    </div>
    @endif

</header>