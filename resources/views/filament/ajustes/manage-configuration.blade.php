<x-filament-panels::page>
    
    {{-- 🟢 Usamos el componente nativo de formulario de Filament --}}
    <x-filament-panels::form wire:submit="save">
        
        {{ $this->form }}

        <div class="flex justify-end">
            {{-- 🟢 Agregamos wire:target="save" para que escuche la carga --}}
            <x-filament::button 
                type="submit" 
                size="lg" 
                icon="heroicon-o-check-circle" 
                color="primary"
                wire:target="save"
            >
                Guardar Configuración
            </x-filament::button>
        </div>
        
    </x-filament-panels::form>

</x-filament-panels::page>