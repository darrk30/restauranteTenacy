<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <x-filament::section class="md:col-span-2">
            <x-slot name="heading">
                Información del Establecimiento
            </x-slot>

            <div class="space-y-4">
                <div class="flex justify-between border-b pb-2">
                    <span class="font-bold">Nombre:</span>
                    <span>{{ $restaurant->name_comercial ?? 'Sin nombre' }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="font-bold">RUC:</span>
                    <span>{{ $restaurant->ruc ?? 'No registrado' }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="font-bold">Dirección:</span>
                    <span>{{ $restaurant->address ?? 'Sin dirección' }}</span>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Identidad
            </x-slot>

            <div class="flex flex-col items-center justify-center space-y-4">
                {{-- Verificamos si existe el objeto y si tiene el campo logo (por si lo agregas después) --}}
                @if (isset($restaurant->logo) && $restaurant->logo)
                    <img src="{{ asset('storage/' . $restaurant->logo) }}"
                        class="w-62 h-32 rounded-4xl object-cover shadow">
                @else
                    {{-- Si no hay objeto, o no hay campo logo, o está vacío, muestra el icono --}}
                    <div class="w-32 h-32 bg-gray-200 rounded-full flex items-center justify-center shadow-inner">
                        <x-heroicon-o-building-storefront class="w-12 h-12 text-gray-400" />
                    </div>
                @endif
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
