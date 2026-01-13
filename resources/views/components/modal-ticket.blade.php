@props(['orderId', 'jobId' => null, 'areas' => []])

<div class="ticket-modal-overlay">

    {{-- Inicializamos Alpine con la primera pestaña activa --}}
    <div class="ticket-modal-content" 
         x-data="{ activeTab: '{{ $areas->first()['id'] ?? 'general' }}' }">

        {{-- Cabecera --}}
        <div class="ticket-modal-header">
            <h3 class="ticket-modal-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Imprimir Comanda #{{ $orderId }}
            </h3>

            <button wire:click="cerrarModalComanda" class="ticket-modal-close-icon" title="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- BARRA DE PESTAÑAS --}}
        <div class="flex border-b border-gray-200 bg-gray-50 overflow-x-auto">
            @foreach($areas as $area)
                <button 
                    @click="activeTab = '{{ $area['id'] }}'"
                    :class="activeTab === '{{ $area['id'] }}' 
                        ? 'border-b-2 border-blue-600 text-blue-600 bg-white' 
                        : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-3 text-sm font-bold uppercase transition-colors whitespace-nowrap focus:outline-none">
                    {{ $area['name'] }}
                </button>
            @endforeach
        </div>

        {{-- Cuerpo: IFRAMES (Uno por cada área) --}}
        <div class="ticket-modal-body bg-gray-100 relative" wire:ignore>
            @foreach($areas as $area)
                {{-- Solo mostramos el iframe si su pestaña está activa --}}
                <div x-show="activeTab === '{{ $area['id'] }}'" class="w-full h-full absolute inset-0">
                    <iframe 
                        id="pdf-frame-{{ $area['id'] }}"
                        {{-- Aquí pasamos el areaId al controlador --}}
                        src="{{ route('imprimir.comanda', ['order' => $orderId, 'jobId' => $jobId, 'areaId' => $area['id']]) }}"
                        class="w-full h-full border-0" 
                        title="Comanda {{ $area['name'] }}">
                    </iframe>
                </div>
            @endforeach
        </div>

        {{-- Footer: Botones --}}
        <div class="ticket-modal-footer">
            <button wire:click="cerrarModalComanda" class="ticket-btn ticket-btn-secondary">
                Cerrar
            </button>

            {{-- Botón IMPRIMIR (Imprime solo el iframe visible) --}}
            <button 
                @click="document.getElementById('pdf-frame-' + activeTab).contentWindow.print()"
                class="ticket-btn ticket-btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                IMPRIMIR ACTUAL
            </button>
        </div>

    </div>
</div>

{{-- Estilos adicionales necesarios para las pestañas se pueden quedar aquí o mover al CSS global --}}
<style>
    /* ... Tus estilos de modalZoomIn y ticket-modal-overlay se mantienen igual ... */
    
    @keyframes modalZoomIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    
    .ticket-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px);
        z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box;
    }

    .ticket-modal-content {
        background-color: white; width: 100%; max-width: 500px; /* Un poco más ancho para las pestañas */
        height: 70vh; /* Un poco más alto */
        border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        display: flex; flex-direction: column; overflow: hidden; animation: modalZoomIn 0.3s ease-out; position: relative;
    }

    .ticket-modal-header { background-color: #1f2937; color: white; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
    .ticket-modal-title { margin: 0; font-size: 16px; font-weight: bold; display: flex; align-items: center; gap: 8px; }
    .ticket-modal-close-icon { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; display: flex; }
    .ticket-modal-close-icon:hover { color: white; }

    /* Ajuste para el cuerpo con pestañas */
    .ticket-modal-body { flex: 1; background-color: #f3f4f6; position: relative; width: 100%; overflow: hidden; }

    .ticket-modal-footer { background-color: white; padding: 16px; border-top: 1px solid #e5e7eb; display: flex; gap: 12px; flex-shrink: 0; }
    .ticket-btn { flex: 1; padding: 12px; border-radius: 6px; font-weight: bold; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background-color 0.2s; border: none; text-transform: uppercase; }
    .ticket-btn-secondary { background-color: #e5e7eb; color: #1f2937; }
    .ticket-btn-secondary:hover { background-color: #d1d5db; }
    .ticket-btn-primary { background-color: #2563eb; color: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .ticket-btn-primary:hover { background-color: #1d4ed8; }
</style>