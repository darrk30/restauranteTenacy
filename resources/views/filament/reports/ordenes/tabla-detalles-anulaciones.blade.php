<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
    <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
                <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Cant.</th>
                <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Producto</th>
                <th class="px-4 py-3 font-medium text-right text-gray-900 dark:text-white">Subtotal</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10 bg-white dark:bg-gray-900">
            @forelse ($detalles as $item)
                <tr>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                        {{ $item->cantidad }}
                    </td>
                    <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">
                        {{ $item->product_name }}
                    </td>
                    <td class="px-4 py-3 font-bold text-right text-danger-600 dark:text-danger-400">
                        S/ {{ number_format($item->subTotal, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                        No hay productos registrados en este pedido.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
