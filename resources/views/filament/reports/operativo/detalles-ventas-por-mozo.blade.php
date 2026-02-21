<x-filament-panels::page>
    {{-- Información adicional de las fechas (El nombre y el botón volver ya están en la cabecera) --}}
    <div class="fi-ta-header flex flex-col gap-4">
        <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">
            <x-heroicon-m-calendar-days class="inline-block w-5 h-5 mr-1" />
            Periodo evaluado: 
            <span class="text-primary-600 dark:text-primary-400">
                {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y H:i') }}
            </span> 
            hasta 
            <span class="text-primary-600 dark:text-primary-400">
                {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y H:i') }}
            </span>
        </p>
    </div>

    {{-- 🟢 AQUÍ SE RENDERIZA TODA LA TABLA DE FILAMENT --}}
    {{ $this->table }}

</x-filament-panels::page>