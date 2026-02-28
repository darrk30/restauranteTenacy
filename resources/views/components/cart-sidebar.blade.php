@props([
    'mesaId' => null,
    'metodosPago' => [],
    'ofreceDelivery' => false,
    'ofreceRecojo' => false,
    'guardarPedidosWeb' => false,
])

{{-- 🟢 1. RAÍZ DEL CARRITO --}}
<div x-data="cartSidebarComponent({{ $mesaId ?? 'null' }})" x-init="wspModal = false;" class="fixed inset-0 z-[110]" x-show="$store.cart.isSidebarOpen"
    style="display: none;">

    <div @click="$store.cart.toggleSidebar()" x-transition.opacity
        class="absolute inset-0 bg-black/60 backdrop-blur-sm cursor-pointer">
    </div>

    <div x-transition:enter="transition-transform ease-out duration-300" x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0" x-transition:leave="transition-transform ease-in duration-300"
        x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        class="absolute inset-y-0 right-0 w-full md:w-[420px] bg-white shadow-2xl flex flex-col">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-white shrink-0">
            <h2 class="text-xl font-black text-gray-800 flex items-center gap-2">
                <x-heroicon-o-shopping-bag class="w-6 h-6 text-[#ce6439]" />
                Mi Pedido
            </h2>
            <button @click="$store.cart.toggleSidebar()"
                class="p-2 text-gray-400 hover:text-gray-800 hover:bg-gray-100 rounded-full transition-colors">
                <x-heroicon-o-x-mark class="w-6 h-6" />
            </button>
        </div>

        {{-- Lista Productos --}}
        <div class="flex-1 overflow-y-auto p-6 bg-gray-50 flex flex-col gap-4">
            <div x-show="$store.cart.items.length === 0"
                class="flex flex-col items-center justify-center h-full text-gray-400 space-y-4 opacity-70"
                style="display: none;">
                <x-heroicon-o-shopping-cart class="w-20 h-20" />
                <p class="text-base font-medium text-gray-500">Aún no has agregado productos</p>
            </div>

            <template x-for="item in $store.cart.items" :key="item.cartItemId">
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex gap-4">
                    <div class="w-16 h-16 shrink-0 bg-gray-50 rounded-xl overflow-hidden">
                        <template x-if="item.image">
                            <img :src="item.image" class="w-full h-full object-cover">
                        </template>
                    </div>

                    <div class="flex-1 flex flex-col justify-between">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-gray-800 text-sm" x-text="item.name"></h4>
                            <button @click="$store.cart.remove(item.cartItemId)"
                                class="text-gray-300 hover:text-red-500">
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="flex items-center justify-between mt-3">
                            <div class="flex items-center bg-gray-50 rounded-lg border border-gray-200">
                                <button @click="$store.cart.changeQty(item.cartItemId, -1)" class="px-2 py-1">-</button>
                                <span class="px-2 text-xs font-bold" x-text="item.qty"></span>
                                <button @click="$store.cart.changeQty(item.cartItemId, 1)" class="px-2 py-1">+</button>
                            </div>
                            <div class="text-[#ce6439] font-black text-sm"
                                x-text="`S/ ${(item.price * item.qty).toFixed(2)}`"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- FOOTER PRINCIPAL --}}
        <div x-show="!showCheckoutForm" class="border-t border-gray-200 p-6 bg-white" style="display: none;">
            <div class="flex justify-between items-center mb-4">
                <span class="text-gray-500 text-sm">Total estimado</span>
                <span class="text-2xl font-black text-[#ce6439]"
                    x-text="`S/ ${$store.cart.total.toFixed(2)}`"></span>
            </div>

            <button :disabled="$store.cart.items.length === 0 || isProcessing"
                @click="
                    if (mesaId) {
                        {{ $guardarPedidosWeb ? 'confirmModal = true;' : 'wspModal = true;' }}
                    } else if (!{{ $ofreceDelivery ? 'true' : 'false' }} && !{{ $ofreceRecojo ? 'true' : 'false' }}) {
                        wspModal = true;
                    } else {
                        showCheckoutForm = true;
                    }
                "
                class="w-full bg-[#ce6439] text-white font-bold py-4 rounded-2xl disabled:opacity-50 transition-colors hover:bg-[#0c4e2e]">
                <span
                    x-text="isProcessing ? 'Procesando...' : (mesaId ? 'Confirmar Pedido' : 'Continuar Pedido')"></span>
            </button>
        </div>

        {{-- FORMULARIO DE CHECKOUT --}}
        <div x-show="showCheckoutForm" x-transition class="absolute inset-0 bg-white z-50 flex flex-col"
            style="display: none;">

            <div class="px-6 py-4 border-b flex items-center gap-3">
                <button @click="showCheckoutForm = false" class="p-2 bg-gray-100 rounded-full">
                    <x-heroicon-o-chevron-left class="w-5 h-5" />
                </button>
                <h2 class="text-lg font-bold">Detalles del Pedido</h2>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-5">
                {{-- Selector Tipo Pedido --}}
                <div class="flex gap-3 bg-gray-100 p-1.5 rounded-xl" x-init="@if (!$ofreceDelivery && $ofreceRecojo) form.tipo = 'llevar'; @endif @if (!$ofreceRecojo && $ofreceDelivery) form.tipo = 'delivery'; @endif">
                    @if ($ofreceDelivery)
                        <button @click="form.tipo = 'delivery'"
                            :class="form.tipo === 'delivery' ? 'bg-white shadow text-[#ce6439]' : 'text-gray-500'"
                            class="flex-1 py-2 rounded-lg font-bold text-sm">Delivery</button>
                    @endif
                    @if ($ofreceRecojo)
                        <button @click="form.tipo = 'llevar'"
                            :class="form.tipo === 'llevar' ? 'bg-white shadow text-[#ce6439]' : 'text-gray-500'"
                            class="flex-1 py-2 rounded-lg font-bold text-sm">Para Llevar</button>
                    @endif
                </div>

                <div class="space-y-4">
                    <input type="text" x-model="form.nombre" class="w-full border rounded-xl p-3"
                        placeholder="Tu Nombre">
                    <input type="tel" x-model="form.telefono" class="w-full border rounded-xl p-3"
                        placeholder="Teléfono / WhatsApp">

                    {{-- MAPA Y DIRECCIÓN --}}
                    <template x-if="form.tipo === 'delivery'">
                        <div class="space-y-4">
                            <div class="space-y-3" x-data="leafletMapComponent()" x-init="initLeaflet()">
                                <label class="text-xs font-bold text-gray-400 uppercase">Ubicación de entrega</label>

                                {{-- Contenedor del Mapa con Buscador --}}
                                <div class="relative group">
                                    <div id="map-leaflet"
                                        class="w-full h-64 rounded-2xl border-2 border-gray-100 shadow-sm z-10"
                                        x-ignore>
                                    </div>

                                    {{-- Botón "Mi ubicación" --}}
                                    <button type="button" @click="locateUser()"
                                        class="absolute bottom-3 right-3 z-[1000] bg-white p-2.5 rounded-full shadow-md border border-gray-100 text-[#ce6439] hover:bg-orange-50 active:scale-95 transition-all">
                                        <x-heroicon-s-map-pin class="w-5 h-5" />
                                    </button>
                                </div>

                                {{-- Dirección Final --}}
                                <input type="text" x-model="form.direccion"
                                    class="w-full border-2 border-gray-100 rounded-xl p-3 bg-gray-50 text-sm font-medium"
                                    placeholder="Usa el buscador del mapa o mueve el pin...">

                                <p class="text-[10px] text-gray-400 italic leading-tight">
                                    * El buscador está dentro del mapa. También puedes hacer clic en cualquier punto
                                    para marcar tu puerta.
                                </p>
                            </div>

                            <select x-model="form.metodo_pago" class="w-full border rounded-xl p-3 bg-white">
                                <option value="">Método de Pago</option>
                                @foreach ($metodosPago as $id => $nombre)
                                    <option value="{{ $id }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <textarea x-model="form.notas" rows="2" class="w-full border rounded-xl p-3"
                        placeholder="Notas adicionales..."></textarea>
                </div>
            </div>

            <div class="p-6 border-t">
                <button :disabled="$store.cart.items.length === 0 || isProcessing"
                    @click="{{ $guardarPedidosWeb ? 'submitOrder()' : 'wspModal = true;' }}"
                    class="w-full bg-[#ce6439] text-white font-bold py-4 rounded-2xl disabled:opacity-50 transition-colors hover:bg-[#0c4e2e]">
                    <span x-text="isProcessing ? 'Procesando...' : 'Confirmar Pedido'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- MODAL CONFIRMACIÓN COCINA --}}
    <div x-show="confirmModal" x-transition
        class="fixed inset-0 z-[120] flex items-center justify-center bg-black/50 backdrop-blur-sm"
        style="display: none;">
        <div class="bg-white w-[90%] max-w-md rounded-2xl p-6 shadow-2xl text-center">
            <h3 class="text-lg font-bold text-gray-800 mb-3">Confirmar Pedido</h3>
            <p class="text-gray-600 mb-6">¿Deseas enviar este pedido a cocina?</p>
            <div class="flex gap-3">
                <button @click="confirmModal = false"
                    class="flex-1 py-3 rounded-xl bg-gray-100 font-semibold">Cancelar</button>
                <button @click="confirmModal = false; submitOrder(mesaId)"
                    class="flex-1 py-3 rounded-xl bg-[#ce6439] text-white font-bold">Sí, Enviar</button>
            </div>
        </div>
    </div>

    {{-- MODAL WHATSAPP --}}
    <div x-show="wspModal" x-transition
        class="fixed inset-0 z-[120] flex items-center justify-center bg-black/50 backdrop-blur-sm"
        style="display: none;">
        <div class="bg-white w-[90%] max-w-md rounded-2xl p-6 shadow-2xl text-center">
            <h3 class="text-xl font-black text-gray-800 mb-3 flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#25D366]" fill="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zM6.654 20.193c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z" />
                </svg>
                Enviar por WhatsApp
            </h3>
            <p class="text-gray-600 mb-6 text-sm">Serás redirigido a WhatsApp para enviar el detalle de tu pedido.
                ¿Deseas continuar?</p>
            <div class="flex gap-3">
                <button @click="wspModal = false" class="flex-1 py-3 rounded-xl bg-gray-100 font-semibold"
                    :disabled="isProcessing">Cancelar</button>
                <button @click="submitWspOrder()"
                    class="flex-1 py-3 rounded-xl bg-[#25D366] text-white font-bold flex justify-center items-center"
                    :disabled="isProcessing">
                    <span x-text="isProcessing ? 'Generando...' : 'Sí, Enviar'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
