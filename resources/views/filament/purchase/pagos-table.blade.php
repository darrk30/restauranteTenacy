@if ($pagos->isNotEmpty())
    @php
        $totalPagado = $pagos->sum('monto');
    @endphp
    <div class="overflow-hidden rounded-xl border">
        <table class="w-full text-sm divide-y">
            <thead class="bg-gray-800 ">
                <tr class="text-center">
                    <th class="px-5 py-3 font-semibold">Método</th>
                    <th class="px-5 py-3 font-semibold">Monto</th>
                    <th class="px-5 py-3 font-semibold">Referencia</th>
                    <th class="px-5 py-3 font-semibold">Acciones</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-700">
                @foreach ($pagos as $pago)
                    <tr class="hover:bg-gray-800/60 transition">
                        <td class="text-center  font-medium">
                            {{ $pago->paymentMethod?->name ?? '-' }}
                        </td>

                        <td class="text-center  font-medium">
                            S/ {{ number_format($pago->monto, 2) }}
                        </td>

                        <td class="text-center text-gray-300">
                            {{ $pago->referencia ?? '-' }}
                        </td>

                        <td class="text-center">
                            <button wire:click="eliminarPago({{ $pago->id }})" x-data="{ tooltip: false }"
                                @mouseenter="tooltip = true" @mouseleave="tooltip = false"
                                class="relative text-red-500 hover:text-red-300 p-2 rounded-lg hover:bg-red-500/10 transition">

                                <x-filament::icon icon="heroicon-o-trash" class="w-5 h-5" />

                                {{-- Tooltip --}}
                                <div x-show="tooltip" x-transition
                                    class="absolute -top-8 left-1/2 -translate-x-1/2 text-xs px-2 py-1 rounded shadow">
                                    Eliminar pago
                                </div>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot class="bg-gray-900 border-t border-gray-700">
                <tr>
                    <td class="px-5 py-3 text-center text-gray-300 font-semibold" colspan="1">
                        Total:
                    </td>
                    <td class="px-5 py-3 font-bold  text-center">
                        S/ {{ number_format($totalPagado, 2) }}
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
@else
    <p class="font-bold italic text-center ">No hay pagos registrados.</p>
@endif
