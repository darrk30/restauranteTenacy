<table class="min-w-full divide-y divide-gray-300 text-sm">
    <thead class="bg-gray-100">
        <tr>
            <th class="px-4 py-2 text-left font-semibold text-gray-700">Producto</th>
            <th class="px-4 py-2 text-left font-semibold text-gray-700">Variante</th>
            <th class="px-4 py-2 text-left font-semibold text-gray-700">Cantidad</th>
            <th class="px-4 py-2 text-left font-semibold text-gray-700">Unidad</th>
        </tr>
    </thead>

    <tbody class="divide-y divide-gray-200">
        @foreach($items as $item)

            @php
                $restaurantSlug = $item->product->restaurant->slug;
                $productSlug = $item->product->slug;
                $productUrl = url("/restaurants/{$restaurantSlug}/gestion/products/{$productSlug}/edit");
            @endphp

            <tr>
                <td class="px-4 py-2">
                    <a 
                        href="{{ $productUrl }}"
                        class="text-primary-600 hover:text-primary-800 underline"
                    >
                        {{ $item->product->name }}
                    </a>
                </td>

                <td class="px-4 py-2">{{ $item->variant->full_name ?? '—' }}</td>
                <td class="px-4 py-2">{{ $item->cantidad }}</td>
                <td class="px-4 py-2">{{ $item->unit->name }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
