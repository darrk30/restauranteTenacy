<x-filament-panels::page>
    @php
        $filters = $this->getAppliedFilters();
        $hayFiltros = collect($filters)->flatten()->filter()->isNotEmpty();
    @endphp

    @if ($hayFiltros)
        <div class="mb-6 p-4 border rounded-xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-lg flex items-center gap-2">
                    <x-heroicon-o-adjustments-horizontal class="w-7 h-7" />
                    Filtros aplicados
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @if ($filters['producto_variante']['product_id'] ?? false)
                    @php
                        $product = \App\Models\Product::find($filters['producto_variante']['product_id']);
                    @endphp
                    <div class="flex flex-col">
                        <span class="text-sm">Producto</span>
                        <span class="text-sm font-semibold px-3 py-1 rounded-lg shadow">
                            {{ $product?->name }}
                        </span>
                    </div>
                @endif
                @if ($filters['producto_variante']['variant_id'] ?? false)
                    @php
                        $variant = \App\Models\Variant::find($filters['producto_variante']['variant_id']);
                    @endphp
                    <div class="flex flex-col">
                        <span class="text-sm">Variante</span>
                        <span class="text-sm font-semibold px-3 py-1 rounded-lg shadow">
                            {{ $variant?->full_name }}
                        </span>
                    </div>
                @endif
                @if ($filters['fecha']['desde'] ?? false)
                    <div class="flex flex-col">
                        <span class="text-sm">Desde</span>
                        <span class="text-sm font-semibold px-3 py-1 rounded-lg shadow">
                            {{ $filters['fecha']['desde'] }}
                        </span>
                    </div>
                @endif
                @if ($filters['fecha']['hasta'] ?? false)
                    <div class="flex flex-col">
                        <span class="text-sm">Hasta</span>
                        <span class="text-sm font-semibold px-3 py-1 rounded-lg shadow">
                            {{ $filters['fecha']['hasta'] }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    @endif
    {{ $this->table }}
</x-filament-panels::page>
