<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- SECCIÓN IZQUIERDA: INFORMACIÓN DETALLADA --}}
        <x-filament::section class="md:col-span-2">
            <x-slot name="heading">
                Información del Establecimiento
            </x-slot>

            <div class="grid grid-cols-1 gap-y-4">
                {{-- Datos Fiscales --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b pb-4">
                    <div>
                        <p class="text-sm text-gray-500">Razón Social</p>
                        <p class="font-medium text-gray-900">{{ $restaurant->name ?? 'No registrado' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">RUC</p>
                        <p class="font-medium text-gray-900">{{ $restaurant->ruc ?? 'No registrado' }}</p>
                    </div>
                </div>

                {{-- Contacto --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b pb-4">
                    <div>
                        <p class="text-sm text-gray-500">Correo Electrónico</p>
                        <p class="font-medium text-gray-900">{{ $restaurant->email ?? 'Sin correo' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Teléfono / WhatsApp</p>
                        <p class="font-medium text-gray-900">{{ $restaurant->phone ?? 'Sin teléfono' }}</p>
                    </div>
                </div>

                {{-- Ubicación --}}
                <div class="pt-2">
                    <p class="text-sm text-gray-500">Dirección Completa</p>
                    <p class="font-medium text-gray-900">{{ $restaurant->address ?? 'Sin dirección' }}</p>
                    <p class="text-xs text-gray-400 mt-1 uppercase">
                        {{ $restaurant->district }} - {{ $restaurant->province }} - {{ $restaurant->department }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- SECCIÓN DERECHA: LOGOTIPO Y ESTADO --}}
        <div class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    Identidad Visual
                </x-slot>

                <div class="flex flex-col items-center justify-center py-4">
                    @if ($restaurant->logo)
                        <div class="relative group">
                            <img src="{{ asset('storage/' . $restaurant->logo) }}"
                                class="w-48 h-48 object-contain rounded-xl shadow-md bg-white border p-2">
                        </div>
                    @else
                        <div class="w-48 h-48 bg-gray-50 rounded-xl flex flex-col items-center justify-center border-2 border-dashed border-gray-200">
                            <x-heroicon-o-photo class="w-12 h-12 text-gray-300" />
                            <p class="text-xs text-gray-400 mt-2">Sin Logotipo</p>
                        </div>
                    @endif
                    
                    <h3 class="mt-4 font-bold text-xl text-center text-primary-600">
                        {{ $restaurant->name_comercial }}
                    </h3>
                </div>
            </x-filament::section>

            {{-- Widget de Estado (Opcional) --}}
            <x-filament::section>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500">Estado del Local</span>
                    <x-filament::badge color="{{ $restaurant->status === 'activo' ? 'success' : 'danger' }}">
                        {{ strtoupper($restaurant->status) }}
                    </x-filament::badge>
                </div>
            </x-filament::section>
        </div>

    </div>
</x-filament-panels::page>