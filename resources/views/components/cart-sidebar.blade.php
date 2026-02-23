<div x-data class="fixed inset-0 z-[110]" x-show="$store.cart.isSidebarOpen" style="display: none;">

    <div @click="$store.cart.toggleSidebar()" x-show="$store.cart.isSidebarOpen" x-transition.opacity
        class="absolute inset-0 bg-black/60 backdrop-blur-sm cursor-pointer"></div>

    <div x-show="$store.cart.isSidebarOpen" x-transition:enter="transition-transform ease-out duration-300"
        x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform ease-in duration-300" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="absolute inset-y-0 right-0 w-full md:w-[420px] bg-white shadow-2xl flex flex-col">

        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-white z-10 shrink-0">
            <h2 class="text-xl font-black text-gray-800 flex items-center gap-2">
                <svg class="w-6 h-6 text-[#0f643b]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                Mi Pedido
            </h2>
            <button @click="$store.cart.toggleSidebar()"
                class="p-2 text-gray-400 hover:text-gray-800 hover:bg-gray-100 rounded-full transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 bg-gray-50 flex flex-col gap-4">

            <div x-show="$store.cart.items.length === 0"
                class="flex flex-col items-center justify-center h-full text-gray-400 space-y-4 opacity-70">
                <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z">
                    </path>
                </svg>
                <p class="text-base font-medium text-gray-500">Aún no has agregado productos</p>
                <button @click="$store.cart.toggleSidebar()"
                    class="px-6 py-2 bg-white border border-gray-200 rounded-full text-sm font-bold text-gray-700 hover:text-[#0f643b] hover:border-[#0f643b] transition-colors shadow-sm">
                    Explorar la carta
                </button>
            </div>

            <template x-for="item in $store.cart.items" :key="item.id">
                <div
                    class="relative bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex gap-4 transition-all hover:shadow-md">

                    <div
                        class="w-16 h-16 shrink-0 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-center overflow-hidden">
                        <img :src="item.image" class="w-full h-full object-cover">
                    </div>

                    <div class="flex-1 flex flex-col justify-between">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm leading-tight line-clamp-2 pr-2"
                                    x-text="item.name"></h4>
                                <div class="text-xs text-gray-400 mt-1 font-medium"
                                    x-text="`S/ ${item.price.toFixed(2)} c/u`"></div>
                            </div>

                            <button @click="$store.cart.remove(item.id)"
                                class="text-gray-300 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition-colors shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                    </path>
                                </svg>
                            </button>
                        </div>

                        <div class="flex items-center justify-between mt-3">
                            <div class="flex items-center bg-gray-50 rounded-lg border border-gray-200">
                                <button @click="$store.cart.changeQty(item.id, -1)"
                                    class="w-7 h-7 flex items-center justify-center text-gray-500 hover:text-[#0f643b] hover:bg-green-50 rounded-l-lg transition-colors font-bold text-lg leading-none">-</button>
                                <span class="w-8 text-center text-xs font-bold text-gray-800" x-text="item.qty"></span>
                                <button @click="$store.cart.changeQty(item.id, 1)"
                                    class="w-7 h-7 flex items-center justify-center text-gray-500 hover:text-[#0f643b] hover:bg-green-50 rounded-r-lg transition-colors font-bold text-lg leading-none">+</button>
                            </div>
                            <div class="text-[#0f643b] font-black text-sm"
                                x-text="`S/ ${(item.price * item.qty).toFixed(2)}`"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="border-t border-gray-200 p-6 bg-white shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] shrink-0 z-10">
            <div class="flex justify-between items-center mb-4">
                <span class="text-gray-500 font-medium text-sm">Total estimado</span>
                <span class="text-2xl font-black text-[#0f643b]" x-text="`S/ ${$store.cart.total.toFixed(2)}`"></span>
            </div>

            <button :disabled="$store.cart.items.length === 0"
                class="w-full bg-[#0f643b] text-white font-bold text-lg py-4 rounded-2xl hover:bg-green-800 active:scale-95 transition-all shadow-md flex justify-center items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                Continuar Pedido
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3">
                    </path>
                </svg>
            </button>
        </div>
    </div>
</div>
