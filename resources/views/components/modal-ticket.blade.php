<div class="ticket-modal-overlay" wire:key="modal-comanda-{{ $orderId }}">
    <div class="ticket-modal-content" x-data="{
        activeTab: '{{ $areas->first()['id'] ?? 'general' }}',
        isPrinting: false,
        imprimir() {
            this.isPrinting = true;
            const frame = document.getElementById('pdf-frame-' + this.activeTab);
            if (frame) {
                frame.contentWindow.focus();
                frame.contentWindow.print();
                frame.contentWindow.onafterprint = () => { this.isPrinting = false; };
                setTimeout(() => { this.isPrinting = false; }, 1000);
            }
        }
    }">

        {{-- Cabecera --}}
        <div class="ticket-modal-header">
            <h3 class="ticket-modal-title">
                <x-heroicon-o-printer class="w-5 h-5" />
                Comanda #{{ $orderId }}
            </h3>
            <button wire:click="cerrarModalComanda" class="ticket-modal-close-icon">
                <x-heroicon-o-x-mark class="w-6 h-6" />
            </button>
        </div>

        {{-- Pestañas --}}
        <div class="flex border-b border-gray-200 bg-white overflow-x-auto scrollbar-hide">
            @foreach ($areas as $area)
                <button @click="activeTab = '{{ $area['id'] }}'"
                    :class="activeTab === '{{ $area['id'] }}'
                        ?
                        'border-b-2 border-blue-600 text-blue-600' :
                        'text-gray-500 hover:text-gray-700'"
                    class="px-6 py-4 text-xs font-bold uppercase transition-all whitespace-nowrap focus:outline-none">
                    {{ $area['name'] }}
                </button>
            @endforeach
        </div>

        {{-- Cuerpo: Solo el iframe --}}
        <div class="ticket-modal-body relative bg-gray-50"> {{-- Un gris muy tenue de fondo general --}}
            @foreach ($areas as $area)
                <div x-show="activeTab === '{{ $area['id'] }}'"
                    x-transition:enter="transition opacity ease-out duration-300" x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="w-full h-full absolute inset-0 flex justify-center overflow-y-auto">

                    <iframe id="pdf-frame-{{ $area['id'] }}"
                        src="{{ route('imprimir.comanda', ['order' => $orderId, 'jobId' => $jobId, 'areaId' => $area['id']]) }}"
                        class="w-full h-full border-none" title="Comanda {{ $area['name'] }}">
                    </iframe>
                </div>
            @endforeach
        </div>

        {{-- Footer --}}
        <div class="ticket-modal-footer">
            <button wire:click="cerrarModalComanda" wire:loading.attr="disabled"
                class="ticket-btn ticket-btn-secondary">
                <span wire:loading.remove wire:target="cerrarModalComanda">CERRAR</span>
                <span wire:loading wire:target="cerrarModalComanda" class="animate-spin">
                    <x-heroicon-o-arrow-path class="w-5 h-5" />
                </span>
            </button>

            <button @click="imprimir()" :disabled="isPrinting"
                :class="isPrinting ? 'ticket-btn-disabled' : 'ticket-btn-primary'" class="ticket-btn">

                <template x-if="isPrinting">
                    <svg class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </template>

                <template x-if="!isPrinting">
                    <x-heroicon-o-printer class="w-5 h-5" />
                </template>

                <span x-text="isPrinting ? 'PROCESANDO...' : 'IMPRIMIR ' + activeTab.toUpperCase()"></span>
            </button>
        </div>
    </div>
</div>
<style>
    .ticket-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(17, 24, 39, 0.8);
        /* Gris azulado oscuro */
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .ticket-modal-content {
        background-color: white;
        width: 100%;
        max-width: 400px;
        height: 60vh;
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        animation: modalZoomIn 0.3s ease-out;
    }

    .ticket-modal-header {
        background-color: #111827;
        color: white;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ticket-modal-title {
        font-size: 1.1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .ticket-modal-close-icon {
        color: #9ca3af;
        transition: color 0.2s;
        border: none;
        background: none;
        cursor: pointer;
    }

    .ticket-modal-close-icon:hover {
        color: white;
    }

    /* Eliminamos el padding y permitimos que el iframe llene el espacio */
    .ticket-modal-body {
        flex: 1;
        position: relative;
        overflow: hidden;
    }

    .ticket-modal-footer {
        padding: 20px;
        border-top: 1px solid #f3f4f6;
        display: flex;
        gap: 12px;
        background-color: white;
    }

    .ticket-btn {
        flex: 1;
        padding: 12px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s;
        border: none;
        text-transform: uppercase;
    }

    .ticket-btn-primary {
        background-color: #2563eb;
        color: white;
    }

    .ticket-btn-primary:hover {
        background-color: #1d4ed8;
    }

    .ticket-btn-secondary {
        background-color: #f3f4f6;
        color: #4b5563;
    }

    .ticket-btn-secondary:hover {
        background-color: #e5e7eb;
    }

    .ticket-btn-disabled {
        background-color: #d1d5db;
        color: #9ca3af;
        cursor: not-allowed;
    }

    @keyframes modalZoomIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }
</style>
