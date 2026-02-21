<x-filament-panels::page>
    {{-- Renderiza el Infolist (El Panel de Resumen y Matemática) --}}
    <div>
        {{ $this->infolist }}
    </div>

    {{-- ZONA DE LA TABLA Y SUS PESTAÑAS --}}
    <div class="mt-8">
        <div class="mb-4 flex flex-col gap-y-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Auditoría de Movimientos</h2>

            {{-- 🟢 Componentes Nativos de Filament para Pestañas Principales --}}
            <x-filament::tabs label="Filtros de Movimientos">
                <x-filament::tabs.item wire:click="$set('activeTab', 'todos')" :active="$activeTab === 'todos'">
                    Todos
                </x-filament::tabs.item>

                <x-filament::tabs.item wire:click="$set('activeTab', 'apertura')" :active="$activeTab === 'apertura'">
                    Monto Apertura
                </x-filament::tabs.item>

                <x-filament::tabs.item wire:click="$set('activeTab', 'ventas')" :active="$activeTab === 'ventas'">
                    Ventas Aprobadas
                </x-filament::tabs.item>

                <x-filament::tabs.item wire:click="$set('activeTab', 'ingresos')" :active="$activeTab === 'ingresos'">
                    Ingresos Extras
                </x-filament::tabs.item>
                
                <x-filament::tabs.item wire:click="$set('activeTab', 'egresos')" :active="$activeTab === 'egresos'">
                    Gastos / Retiros
                </x-filament::tabs.item>
                
                <x-filament::tabs.item wire:click="$set('activeTab', 'anulaciones')" :active="$activeTab === 'anulaciones'">
                    Anulaciones
                </x-filament::tabs.item>
            </x-filament::tabs>

            {{-- 🟢 SUB-PESTAÑAS (Solo aparecen en Ventas y Anulaciones) --}}
            @if(in_array($activeTab, ['ventas', 'anulaciones']))
                <div class="flex gap-2 mt-2">
                    <button wire:click="$set('activeCanal', 'todos')" 
                        class="px-4 py-1.5 text-xs font-semibold rounded-full transition-colors {{ $activeCanal === 'todos' ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' }}">
                        Todos los Canales
                    </button>
                    
                    <button wire:click="$set('activeCanal', 'salon')" 
                        class="px-4 py-1.5 text-xs font-semibold rounded-full transition-colors {{ $activeCanal === 'salon' ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' }}">
                        Salón
                    </button>
                    
                    <button wire:click="$set('activeCanal', 'delivery')" 
                        class="px-4 py-1.5 text-xs font-semibold rounded-full transition-colors {{ $activeCanal === 'delivery' ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' }}">
                        Delivery
                    </button>
                    
                    <button wire:click="$set('activeCanal', 'llevar')" 
                        class="px-4 py-1.5 text-xs font-semibold rounded-full transition-colors {{ $activeCanal === 'llevar' ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' }}">
                        Para Llevar
                    </button>
                </div>
            @endif
        </div>

        {{-- Aquí se renderiza la tabla filtrada automáticamente --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>