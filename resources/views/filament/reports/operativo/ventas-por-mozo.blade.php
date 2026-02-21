<x-filament-panels::page>

    {{-- Botones de cabecera (Exportar PDF general) --}}
    @section('header-actions')
        @foreach ($this->getCachedHeaderActions() as $action)
            {{ $action }}
        @endforeach
    @endsection

    {{-- Formulario de Filtros --}}
    <form wire:submit.prevent="aplicarFiltros" class="flex flex-col md:flex-row md:items-end gap-4">
        <div class="flex-grow">
            {{ $this->form }}
        </div>
        <div>
            <x-filament::button type="submit" icon="heroicon-m-funnel" size="lg" color="primary">
                Filtrar Datos
            </x-filament::button>
        </div>
    </form>

    {{-- Tabla de Filament --}}
    {{ $this->table }}

</x-filament-panels::page>