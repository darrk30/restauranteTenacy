@props(['products' => []])

<div id="product-grid"
    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-4 px-4 md:px-0 mb-24">
    @forelse($products as $producto)
        @php
            $name = is_array($producto) ? $producto['name'] : $producto->name;
            $category = is_array($producto) ? $producto['category'] : $producto->category;
            $price = is_array($producto) ? $producto['price'] : $producto->price;
            $img = is_array($producto) ? $producto['image'] ?? null : $producto->image_path ?? null;

            $shortDesc = is_array($producto) ? $producto['description'] ?? '1 un.' : $producto->description ?? '1 un.';
            $longDesc = is_array($producto)
                ? $producto['long_description'] ?? $shortDesc
                : $producto->long_description ?? $shortDesc;

            $galleryArray = is_array($producto) ? $producto['gallery'] ?? [$img] : $producto->gallery ?? [$img];
            $galleryJSON = json_encode($galleryArray);
        @endphp

        <div x-data="{ added: false }"
            class="product-card bg-white rounded-2xl shadow-sm p-3 flex flex-col relative border border-gray-100 transition-all duration-300 hover:shadow-md"
            data-category="{{ strtolower($category) }}" data-name="{{ strtolower($name) }}"
            data-price="{{ $price }}">

            @if (isset($producto['badge']))
                <span
                    class="absolute top-2 left-2 bg-orange-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm z-10">
                    {{ $producto['badge'] }}
                </span>
            @endif

            <div @click="$dispatch('open-modal', { name: '{{ addslashes($name) }}', price: {{ $price }}, desc: '{{ addslashes($longDesc) }}', images: {{ $galleryJSON }} })"
                class="h-32 w-full flex items-center justify-center mb-3 relative cursor-pointer group overflow-hidden rounded-xl bg-gray-50">

                @if ($img)
                    <img src="{{ $img }}"
                        class="w-full h-full object-contain drop-shadow-sm transition-transform duration-300 group-hover:scale-110">
                    <div
                        class="absolute inset-0 bg-black/5 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <svg class="w-8 h-8 text-white drop-shadow-md" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                            </path>
                        </svg>
                    </div>
                @else
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                        </path>
                    </svg>
                @endif
            </div>

            <h3 class="font-bold text-gray-800 text-xs md:text-sm leading-tight line-clamp-2 h-8 md:h-10 mb-1 cursor-pointer hover:text-[#0f643b]"
                @click="$dispatch('open-modal', { name: '{{ addslashes($name) }}', price: {{ $price }}, desc: '{{ addslashes($longDesc) }}', images: {{ $galleryJSON }} })">
                {{ $name }}
            </h3>

            <div class="flex justify-between items-center mt-1 mb-3">
                <p class="text-[10px] md:text-xs text-gray-400 line-clamp-1 flex-1 pr-1">{{ $shortDesc }}</p>
                <div class="flex items-center text-[10px] md:text-xs text-gray-500 font-medium">
                    <svg class="w-3 h-3 text-orange-400 mr-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                        </path>
                    </svg>
                    4.8
                </div>
            </div>

            <div class="flex justify-between items-center mt-auto">
                <div class="font-extrabold text-gray-900 text-sm md:text-base">S/ {{ number_format($price, 2) }}</div>

                <button
                    @click="
                            $store.cart.add({ id: '{{ addslashes($name) }}', name: '{{ addslashes($name) }}', price: {{ $price }}, image: '{{ $img ?? 'https://via.placeholder.com/150' }}', qty: 1 });
                            added = true; 
                            setTimeout(() => added = false, 1000);
                        "
                    :class="added ? 'bg-green-500' : 'bg-[#0f643b]'"
                    class="w-8 h-8 md:w-9 md:h-9 rounded-full text-white flex items-center justify-center active:scale-95 transition-all shadow-sm">

                    <svg x-show="!added" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <svg x-show="added" style="display: none;" class="w-5 h-5 animate-bounce" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </button>
            </div>
        </div>
    @empty
        <div class="col-span-2 sm:col-span-3 md:col-span-4 xl:col-span-5 text-center py-10 text-gray-400">No se
            encontraron productos.</div>
    @endforelse
</div>

<div id="no-results" class="hidden text-center py-16 px-4 w-full">
    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
    <h3 class="text-lg font-bold text-gray-700">No encontramos lo que buscas</h3>
    <p class="text-gray-500 text-sm mt-1">Intenta con otra palabra o categoría.</p>
</div>

<div x-data="{
    isOpen: false,
    product: { name: '', price: 0, desc: '', images: [] },
    qty: 1,
    get total() { return (this.product.price * this.qty).toFixed(2); }
}"
    @open-modal.window="product = $event.detail; qty = 1; isOpen = true; document.body.style.overflow = 'hidden'"
    x-show="isOpen" style="display: none;" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center">

    <div x-show="isOpen" x-transition.opacity @click="isOpen = false; document.body.style.overflow = 'auto'"
        class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

    <div x-show="isOpen" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="transform translate-y-full sm:translate-y-10 opacity-0"
        x-transition:enter-end="transform translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="transform translate-y-0 opacity-100"
        x-transition:leave-end="transform translate-y-full sm:translate-y-10 opacity-0"
        class="bg-white w-full sm:max-w-md md:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] relative z-10">

        <button @click="isOpen = false; document.body.style.overflow = 'auto'"
            class="absolute top-4 right-4 bg-white/80 backdrop-blur text-gray-800 p-2 rounded-full shadow-sm z-30 hover:bg-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>

        <div
            class="relative w-full h-64 sm:h-72 bg-gray-50 shrink-0 group flex overflow-x-auto snap-x snap-mandatory no-scrollbar">
            <template x-for="img in product.images">
                <div class="w-full h-full shrink-0 snap-center p-6 flex items-center justify-center">
                    <img :src="img" class="max-w-full max-h-full object-contain drop-shadow-md">
                </div>
            </template>
        </div>

        <div class="p-6 overflow-y-auto">
            <h2 x-text="product.name" class="text-xl sm:text-2xl font-black text-gray-900 leading-tight"></h2>
            <p x-text="product.desc" class="text-gray-500 text-sm mt-3 leading-relaxed"></p>

            <hr class="my-5 border-gray-100">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider">Precio Total</span>
                    <div class="text-2xl font-black text-[#0f643b]" x-text="'S/ ' + total"></div>
                </div>

                <div class="flex items-center bg-gray-100 rounded-full p-1 border border-gray-200">
                    <button @click="if(qty > 1) qty--"
                        class="w-8 h-8 flex items-center justify-center text-gray-600 hover:text-black hover:bg-white rounded-full transition-colors shadow-sm active:scale-95"><svg
                            class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4">
                            </path>
                        </svg></button>
                    <span x-text="qty" class="w-8 text-center font-bold text-lg text-gray-800"></span>
                    <button @click="qty++"
                        class="w-8 h-8 flex items-center justify-center text-gray-600 hover:text-black hover:bg-white rounded-full transition-colors shadow-sm active:scale-95"><svg
                            class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4">
                            </path>
                        </svg></button>
                </div>
            </div>

            <button
                @click="
                        $store.cart.add({ id: product.name, name: product.name, price: product.price, image: product.images[0], qty: qty });
                        isOpen = false; document.body.style.overflow = 'auto';
                    "
                class="w-full bg-[#0f643b] text-white font-bold text-lg py-3.5 rounded-2xl hover:bg-green-800 active:scale-95 transition-all shadow-md flex justify-center items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                Agregar <span x-text="'S/ ' + total"></span>
            </button>
        </div>
    </div>
</div>
