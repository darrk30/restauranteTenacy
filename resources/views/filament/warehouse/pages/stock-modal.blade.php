<div class="space-y-3">
    @foreach ($stocks as $stock)
        <div
            class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 
                    flex justify-between items-center bg-white dark:bg-gray-900 
                    shadow-sm hover:shadow-md transition-shadow duration-200">

            <div class="space-y-0.5">
                <div class="font-semibold text-gray-900 dark:text-gray-100 text-sm">
                    {{ $stock->warehouse->name }}
                </div>

                @if ($stock->warehouse->address)
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $stock->warehouse->address }}
                    </div>
                @endif
            </div>

            <div
                class="text-sm font-bold px-3 py-1 rounded-lg
                        bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                {{ $stock->stock_real ?? ($stock->stock ?? 0) }}
            </div>
            <div class="text-xs text-yellow-600 font-semibold">
                Mín: {{ $stock->min_stock }}
            </div>
            <x-filament::badge color="info">{{ $variant->product->unit->name ?? '—' }}</x-filament::badge>
        </div>
    @endforeach
</div>
