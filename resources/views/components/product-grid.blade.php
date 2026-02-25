@props(['products' => []])

<div x-data="{
    limit: 20,
    totalCount: {{ count($products) }},
    showMore() {
        this.limit += 20;
    }
}">
    {{-- 🟢 GRID DE PRODUCTOS --}}
    <div id="product-grid"
        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-4 px-4 md:px-0 mb-12">

        @forelse($products as $index => $producto)
            @php
                $p = is_array($producto)
                    ? $producto
                    : (method_exists($producto, 'toArray')
                        ? $producto->toArray()
                        : (array) $producto);

                $name = $p['name'] ?? '';
                $categoriesString = $p['categories'] ?? 'general';
                $price = $p['price'] ?? 0;
                $img = $p['image'] ?? ($p['image_path'] ?? null);
                $shortDesc = $p['description'] ?? '';
                $badge = $p['badge'] ?? null;
                $galleryClean = array_values(array_filter(!empty($p['gallery']) ? $p['gallery'] : [$img]));

                $productPayload = json_encode([
                    'id' => $p['id'] ?? uniqid(),
                    'name' => $name,
                    'p_hash' => base64_encode((string) $price),
                    'desc' => $p['long_description'] ?? $shortDesc,
                    'images' => $galleryClean,
                    'attributes' => $p['attributes'] ?? [],
                    'variants' => $p['variants'] ?? [],
                ]);
            @endphp

            {{-- Elemento de Producto con control de visibilidad --}}
            <div x-show="{{ $index }} < limit" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100" x-data="{ productData: {{ $productPayload }} }"
                class="product-card bg-white rounded-2xl shadow-sm p-3 flex flex-col relative border border-gray-100 transition-all duration-300 hover:shadow-md"
                data-categories="{{ $categoriesString }}" data-name="{{ strtolower($name) }}"
                data-order="{{ $index }}">

                @if ($badge)
                    <span
                        class="absolute top-2 left-2 bg-orange-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm z-10">
                        {{ $badge }}
                    </span>
                @endif

                <div @click="$dispatch('open-modal', productData)"
                    class="h-32 w-full flex items-center justify-center mb-3 relative cursor-pointer group overflow-hidden rounded-xl bg-gray-50">
                    @if ($img)
                        <img src="{{ $img }}"
                            class="w-full h-full object-contain drop-shadow-sm transition-transform duration-300 group-hover:scale-110">
                        <div
                            class="absolute inset-0 bg-black/5 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <x-heroicon-o-shopping-bag class="w-8 h-8 text-white drop-shadow-md" />
                        </div>
                    @else
                        <x-heroicon-o-photo class="w-8 h-8 text-gray-300" />
                    @endif
                </div>

                <h3 class="font-bold text-gray-800 text-xs md:text-sm leading-tight line-clamp-2 h-6 md:h-10 mb-1 cursor-pointer hover:text-[#ce6439]"
                    @click="$dispatch('open-modal', productData)">
                    {{ $name }}
                </h3>

                <div class="flex justify-between items-center mb-3">
                    <p class="text-[10px] md:text-xs text-gray-400 line-clamp-1 flex-1 pr-1">{{ $shortDesc }}</p>
                </div>

                <div class="flex justify-between items-center mt-auto">
                    <div class="font-extrabold text-gray-900 text-sm md:text-base">S/ {{ number_format($price, 2) }}
                    </div>
                    <button @click="$dispatch('open-modal', productData)"
                        class="w-8 h-8 md:w-9 md:h-9 rounded-full text-white flex items-center justify-center active:scale-95 transition-all shadow-sm bg-[#ce6439]">
                        <x-heroicon-o-plus class="w-5 h-5" />
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-10 text-gray-400">No se encontraron productos.</div>
        @endforelse
    </div>

    {{-- 🟢 BOTÓN VER MÁS --}}
    <div x-show="limit < totalCount" class="flex justify-center pb-20">
        <button @click="showMore()"
            class="group flex items-center gap-2 bg-white border-2 border-[#ce6439] text-[#ce6439] px-8 py-3 rounded-2xl font-bold hover:bg-[#ce6439] hover:text-white transition-all duration-300 shadow-sm active:scale-95">
            Ver más productos
            <x-heroicon-o-chevron-down class="w-5 h-5 group-hover:translate-y-1 transition-transform" />
        </button>
    </div>
</div>

{{-- 🟢 MODAL AVANZADO (SE MANTIENE IGUAL) --}}
<div x-data="productModalComponent" @open-modal.window="openModal($event.detail)" x-show="isOpen" style="display: none;"
    class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center">

    <div x-show="isOpen" x-transition.opacity @click="closeModal()"
        class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

    <div x-show="isOpen" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="transform translate-y-full sm:translate-y-10 opacity-0"
        x-transition:enter-end="transform translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="transform translate-y-0 opacity-100"
        x-transition:leave-end="transform translate-y-full sm:translate-y-10 opacity-0"
        class="bg-white w-full sm:max-w-md md:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] relative z-10">

        <button @click="closeModal()"
            class="absolute top-4 right-4 bg-white/80 backdrop-blur text-gray-800 p-2 rounded-full shadow-sm z-30 hover:bg-white">
            <x-heroicon-o-x-mark class="w-5 h-5" />
        </button>

        <div class="relative w-full h-64 sm:h-72 bg-gray-50 shrink-0 flex items-center justify-center p-6">
            <template x-if="displayImage">
                <img :src="displayImage"
                    class="max-w-full max-h-full object-contain drop-shadow-md transition-all duration-300">
            </template>
            <template x-if="!displayImage">
                <x-heroicon-o-photo class="w-16 h-16 text-gray-200" />
            </template>
        </div>

        <div class="p-6 overflow-y-auto">
            <div class="flex justify-between items-start gap-4">
                <div>
                    <h2 x-text="product.name" class="text-xl sm:text-2xl font-black text-gray-900 leading-tight"></h2>
                    <template x-if="activeVariant && activeVariant.stock !== null">
                        <div
                            class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 px-2 py-1 rounded mt-2 text-xs font-bold">
                            <x-heroicon-o-cube class="w-3 h-3" /> Stock disponible: <span
                                x-text="activeVariant.stock"></span>
                        </div>
                    </template>
                </div>
            </div>

            <p x-text="product.desc" class="text-gray-500 text-sm mt-3 leading-relaxed"></p>

            <template x-if="product.attributes && Object.keys(product.attributes).length > 0">
                <div>
                    <template x-for="attr in Object.values(product.attributes)" :key="attr.id">
                        <div class="mt-6">
                            <h3 class="text-xs font-bold text-gray-400 mb-3 uppercase tracking-wider"
                                x-text="attr.name"></h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <template x-for="option in Object.values(attr.options || {})" :key="option.id">
                                    <label
                                        class="flex items-center justify-between p-3 border border-gray-100 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors"
                                        :class="selectedOptions[attr.id] == option.id ? 'border-[#ce6439] bg-green-50/20' : ''">
                                        <div class="flex items-center gap-3 truncate">
                                            <input type="radio" :name="'attr_' + attr.id" :value="option.id"
                                                x-model="selectedOptions[attr.id]"
                                                class="w-5 h-5 text-[#ce6439] rounded-full border-gray-300 focus:ring-[#ce6439] shrink-0">
                                            <span class="text-sm text-gray-700 font-medium truncate"
                                                x-text="option.name"></span>
                                        </div>
                                        <span class="text-xs font-bold text-gray-900 shrink-0 ml-2"
                                            x-show="parseFloat(option.price) > 0"
                                            x-text="'+S/ ' + parseFloat(option.price).toFixed(2)"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <hr class="my-5 border-gray-100">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider">Precio Total</span>
                    <div class="text-2xl font-black text-[#ce6439]" x-text="'S/ ' + total"></div>
                </div>
                <div class="flex items-center bg-gray-100 rounded-full p-1 border border-gray-200">
                    <button @click="if(qty > 1) qty--"
                        class="w-8 h-8 flex items-center justify-center text-gray-600 hover:text-black hover:bg-white rounded-full transition-colors shadow-sm active:scale-95">
                        <x-heroicon-s-minus class="w-4 h-4" />
                    </button>
                    <span x-text="qty" class="w-8 text-center font-bold text-lg text-gray-800"></span>
                    <button @click="qty++"
                        class="w-8 h-8 flex items-center justify-center text-gray-600 hover:text-black hover:bg-white rounded-full transition-colors shadow-sm active:scale-95">
                        <x-heroicon-s-plus class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <button @click="addToCart()"
                class="w-full bg-[#ce6439] text-white font-bold text-lg py-3.5 rounded-2xl hover:bg-amber-500 active:scale-95 transition-all shadow-md flex justify-center items-center gap-2">
                <x-heroicon-o-shopping-bag class="w-6 h-6" /> Agregar <span x-text="'S/ ' + total"></span>
            </button>
        </div>
    </div>
</div>
