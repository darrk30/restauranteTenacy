<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Encabezado con información principal --}}
        <x-filament::grid default="1" md="2" lg="4" class="gap-6">
            <x-filament::section class="flex flex-col items-center justify-center text-center">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Comprobante</p>
                <div class="mt-1">
                    <x-filament::badge color="info" size="xl">
                        {{ $sale->serie }}-{{ $sale->correlativo }}
                    </x-filament::badge>
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Emisión</p>
                <p class="mt-1 text-lg font-semibold">{{ $sale->created_at->format('d/m/Y') }}</p>
                <p class="text-xs text-gray-400">{{ $sale->created_at->format('H:i A') }}</p>
            </x-filament::section>

            <x-filament::section class="text-center">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Método de Pago</p>
                <div class="mt-2 flex flex-wrap justify-center gap-1">
                    @forelse ($sale->movements as $movement)
                        <x-filament::badge color="success" icon="heroicon-m-banknotes">
                            {{ $movement->paymentMethod->name }}
                        </x-filament::badge>
                    @empty
                        <x-filament::badge color="gray">N/A</x-filament::badge>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Pagado</p>
                <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">
                    S/ {{ number_format($sale->total, 2) }}
                </p>
            </x-filament::section>
        </x-filament::grid>

        {{-- Detalle de Productos --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-shopping-cart" class="h-5 w-5 text-gray-400" />
                    <span>Detalle de Productos</span>
                </div>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">Descripción</th>
                            <th class="px-4 py-3 text-sm font-semibold text-center text-gray-900 dark:text-white">
                                Cantidad</th>
                            <th class="px-4 py-3 text-sm font-semibold text-right text-gray-900 dark:text-white">Precio
                                Unit.</th>
                            <th class="px-4 py-3 text-sm font-semibold text-right text-gray-900 dark:text-white">
                                Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($sale->details as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <td class="px-4 py-3 text-sm ">
                                    {{ $item->product_name }}
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    {{ $item->cantidad }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    S/ {{ number_format($item->precio_unitario, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">
                                    S/ {{ number_format($item->cantidad * $item->precio_unitario, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-white/5">
                        @if ($sale->monto_descuento > 0)
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-sm text-right text-gray-500">Descuento:</td>
                                <td class="px-4 py-2 text-sm text-right text-danger-600 font-medium">- S/
                                    {{ number_format($sale->monto_descuento, 2) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-sm text-right text-gray-500 font-medium">Op.
                                Gravada:</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-white">S/
                                {{ number_format($sale->op_gravada, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-sm text-right text-gray-500 font-medium">IGV (18%):
                            </td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-white">S/
                                {{ number_format($sale->monto_igv, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3"
                                class="px-4 py-4 text-right text-base font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                Total a Pagar:</td>
                            <td class="px-4 py-4 text-right text-xl font-black text-primary-600 dark:text-primary-400">
                                S/ {{ number_format($sale->total, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        {{-- Notas adicionales --}}
        @if ($sale->notas)
            <x-filament::section collapsible collapsed>
                <x-slot name="heading">Observaciones / Notas</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-400 italic">
                    "{{ $sale->notas }}"
                </p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
