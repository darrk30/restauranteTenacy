@props(['categories' => []])

<style>
    /* Ocultar scrollbar pero mantener funcionalidad de scroll */
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }

    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>

<div class="max-w-6xl mx-auto px-4 mb-6">

    <div class="flex justify-between items-center mb-3 relative">
        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-1 cursor-pointer">
            Todas las Categorías
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </h2>

        <div class="relative inline-block text-left">
            <button id="sort-btn"
                class="flex items-center gap-1 px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 transition shadow-sm z-20 relative bg-white">
                <span id="sort-text">Ordenar por</span>
                <svg class="w-3.5 h-3.5 text-gray-500 transition-transform duration-200" id="sort-icon" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div id="sort-dropdown"
                class="hidden absolute right-0 top-full mt-1 z-50 w-40 origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none overflow-hidden transition-all duration-200 opacity-0 -translate-y-2">
                <div class="py-1">
                    <button
                        class="sort-option block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-[#ce6439] hover:text-white transition-colors"
                        data-sort="default">Por defecto</button>
                    <button
                        class="sort-option block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-[#ce6439] hover:text-white transition-colors"
                        data-sort="price_asc">Menor Precio</button>
                    <button
                        class="sort-option block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-[#ce6439] hover:text-white transition-colors"
                        data-sort="price_desc">Mayor Precio</button>
                    <button
                        class="sort-option block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-[#ce6439] hover:text-white transition-colors"
                        data-sort="name_asc">A - Z</button>
                    <button
                        class="sort-option block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-[#ce6439] hover:text-white transition-colors"
                        data-sort="name_desc">Z - A</button>
                </div>
            </div>
        </div>
    </div>

    @if (count($categories) > 0)
        <div class="flex overflow-x-auto no-scrollbar gap-2 pb-2 -mx-4 px-4 md:mx-0 md:px-0">
            <button data-filter="todos"
                class="category-btn shrink-0 px-5 py-2 bg-[#ce6439] text-white text-sm font-semibold rounded-full shadow-md transition-transform active:scale-95">
                Todos
            </button>
            @foreach ($categories as $category)
                <button data-filter="{{ strtolower(is_array($category) ? $category['name'] : $category->name) }}"
                    class="category-btn shrink-0 px-5 py-2 bg-white border border-gray-200 text-gray-600 text-sm font-medium rounded-full hover:bg-[#ce6439]/10 hover:text-[#ce6439] hover:border-[#ce6439] transition-all active:scale-95 shadow-sm">
                    {{ is_array($category) ? $category['name'] : $category->name }}
                </button>
            @endforeach
        </div>
    @endif

</div>
