<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::grid default="1" md="2" lg="4" class="gap-6">
            <x-filament::section class="text-center">
                <p class="text-sm font-medium text-gray-500">Documento</p>
                <div class="mt-1">
                    {{ $purchase->serie }}-{{ $purchase->numero }}
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <p class="text-sm font-medium text-gray-500">Fecha de Compra</p>
                <p class="mt-1 text-lg font-semibold">{{ $purchase->fecha_compra->format('d/m/Y') }}</p>
            </x-filament::section>

            <x-filament::section class="text-center">
                <p class="text-sm font-medium text-gray-500">Métodos de Pago</p>
                <div class="mt-2 flex flex-wrap justify-center gap-1">
                    @foreach ($purchase->paymentMethods as $pm)
                        <x-filament::badge color="success">
                            {{ $pm->paymentMethod->name }}: S/ {{ number_format($pm->monto, 2) }}
                        </x-filament::badge>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <p class="text-sm font-medium text-gray-500">Total Inversión</p>
                <p class="mt-1 text-2xl font-bold text-primary-600">
                    S/ {{ number_format($purchase->total, 2) }}
                </p>
            </x-filament::section>
        </x-filament::grid>

        <x-filament::section>
            <x-slot name="heading">Productos Ingresados</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">Producto</th>
                            <th class="px-4 py-3 text-sm font-semibold text-center text-gray-900 dark:text-white">
                                Cantidad</th>
                            <th class="px-4 py-3 text-sm font-semibold text-right text-gray-900 dark:text-white">Costo
                                Unit.</th>
                            <th class="px-4 py-3 text-sm font-semibold text-right text-gray-900 dark:text-white">
                                Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($purchase->details as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <td class="px-4 py-3 text-sm">
                                    {{ $item->product->name }}
                                    @if ($item->variant)
                                        <span class="text-xs">{{ $item->variant->fullname }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    {{ $item->cantidad }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    S/ {{ number_format($item->costo, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-medium">
                                    S/ {{ number_format($item->subtotal, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-sm text-right text-gray-500">Subtotal:</td>
                            <td class="px-4 py-2 text-sm text-right">S/ {{ number_format($purchase->subtotal, 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-sm text-right text-gray-500">IGV:</td>
                            <td class="px-4 py-2 text-sm text-right">S/ {{ number_format($purchase->igv, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-right text-base font-bold uppercase">Total Compra:
                            </td>
                            <td class="px-4 py-4 text-right text-xl font-black text-primary-600">
                                S/ {{ number_format($purchase->total, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
