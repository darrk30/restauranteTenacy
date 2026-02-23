@props(['tenant' => null, 'cartCount' => 0])

<header class="bg-white shadow-sm sticky top-0 z-50 w-full">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between relative gap-4">

        <div class="flex items-center justify-start gap-2 flex-1 md:flex-none md:w-1/3">

            <button class="md:hidden text-gray-600 hover:text-[#0f643b] p-2 -ml-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>

            <h1 class="text-xl md:text-2xl font-extrabold text-[#0f643b] tracking-tight">
                {{ $tenant->name ?? 'Kipu' }}
            </h1>
        </div>

        <div class="hidden md:flex flex-1 justify-center max-w-lg w-full">
            <div class="relative w-full group">
                <input type="text" placeholder="Buscar en la carta..."
                    class="w-full bg-gray-100/80 border border-transparent rounded-full py-2 pl-10 pr-4 focus:outline-none focus:bg-white focus:border-[#0f643b] focus:ring-4 focus:ring-green-50 text-sm transition-all">
                <svg class="w-5 h-5 absolute left-3 top-2.5 text-gray-400 group-focus-within:text-[#0f643b] transition-colors"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 md:gap-4 flex-1 md:flex-none md:w-1/3">

            <button class="md:hidden text-gray-600 hover:text-[#0f643b] p-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </button>

            <button x-data @click="$store.cart.toggleSidebar()"
                class="relative text-gray-700 hover:text-[#0f643b] transition-colors p-2">
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

            <div class="w-px h-6 bg-gray-200 hidden md:block"></div>

            @auth
                <div
                    class="w-8 h-8 md:w-9 md:h-9 rounded-full bg-gray-100 overflow-hidden cursor-pointer border-2 border-transparent hover:border-[#0f643b] transition-all shadow-sm">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'U') }}&background=0f643b&color=fff&bold=true"
                        alt="Perfil">
                </div>
            @else
                <a href="{{ route('login') }}"
                    class="hidden md:flex items-center gap-2 bg-[#0f643b]/10 hover:bg-[#0f643b]/20 text-[#0f643b] text-sm font-bold px-4 py-2 rounded-full transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                        </path>
                    </svg>
                    Ingresar
                </a>

                <a href="{{ route('login') }}" class="md:hidden text-gray-600 hover:text-[#0f643b] p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                        </path>
                    </svg>
                </a>
            @endauth

        </div>

    </div>
</header>
