<x-filament-panels::page>
    {{-- 1. Renderizamos los filtros personalizados --}}
    {{ $this->form }}

    {{-- 2. Renderizamos los Widgets (Stats) --}}
    {{-- Filament lo hace automático gracias a getHeaderWidgets, pero si quisieras control manual: --}}
    {{-- <x-filament-widgets::widgets
        :widgets="$this->getHeaderWidgets()"
        :columns="$this->getHeaderWidgetsColumns()"
    /> --}}

    {{-- 3. Renderizamos la Tabla --}}
    {{ $this->table }}
</x-filament-panels::page>