<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Filtros Superiores --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            {{ $this->form }}
        </div>

        {{-- Selector de Pestañas Estilo Filament --}}
        <div
            class="flex items-center p-1 bg-gray-100 dark:bg-gray-800 rounded-lg w-fit border border-gray-200 dark:border-gray-700">
            <button wire:click="setActiveTab('ordenes')" @class([
                'px-6 py-2 text-sm font-semibold rounded-md transition-all duration-200',
                'bg-white dark:bg-gray-700 shadow-sm text-primary-600 dark:text-primary-400' =>
                    $activeTab === 'ordenes',
                'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' =>
                    $activeTab !== 'ordenes',
            ])>
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shopping-cart class="w-4 h-4" />
                    Órdenes Completas
                </div>
            </button>

            <button wire:click="setActiveTab('productos')" @class([
                'px-6 py-2 text-sm font-semibold rounded-md transition-all duration-200',
                'bg-white dark:bg-gray-700 shadow-sm text-primary-600 dark:text-primary-400' =>
                    $activeTab === 'productos',
                'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' =>
                    $activeTab !== 'productos',
            ])>
                <div class="flex items-center gap-2">
                    <x-heroicon-o-beaker class="w-4 h-4" />
                    Productos Individuales
                </div>
            </button>
        </div>

        {{-- La Tabla --}}
        <div
            class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
