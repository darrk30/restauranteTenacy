<div class="p-2">
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-b">
                <th class="py-2">Producto</th>
                <th class="py-2 text-center">Cantidad</th>
                <th class="py-2">Unidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr class="border-b last:border-0">
                    <td class="py-2">
                        <span class="font-bold">{{ $item->product->name }}</span>
                        <br><small class="text-gray-500">{{ $item->variant->full_name }}</small>
                    </td>
                    <td class="py-2 text-center font-mono">{{ number_format($item->cantidad, 2) }}</td>
                    <td class="py-2 text-gray-600">{{ $item->unit->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
