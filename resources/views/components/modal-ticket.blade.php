@props(['orderId', 'jobId' => null])

<div class="ticket-modal-overlay">

    {{-- Contenedor del Modal --}}
    <div class="ticket-modal-content">

        {{-- Cabecera --}}
        <div class="ticket-modal-header">
            <h3 class="ticket-modal-title">
                {{-- Icono Impresora SVG --}}
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Imprimir Comanda #{{ $orderId }}
            </h3>

            {{-- Botón X (Cerrar) --}}
            <button wire:click="cerrarModalComanda" class="ticket-modal-close-icon" title="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Cuerpo: IFRAME PDF --}}
        {{-- wire:ignore evita recargas innecesarias --}}
        <div class="ticket-modal-body" wire:ignore>
            <iframe id="pdf-frame-{{ $orderId }}" {{-- Agregamos el parámetro jobId a la URL --}}
                src="{{ route('imprimir.comanda', ['order' => $orderId, 'jobId' => $jobId]) }}"
                class="w-full h-full border-0" title="Comanda PDF">
            </iframe>
        </div>

        {{-- Footer: Botones --}}
        <div class="ticket-modal-footer">

            <button wire:click="cerrarModalComanda" class="ticket-btn ticket-btn-secondary">
                Cerrar
            </button>

            {{-- Botón IMPRIMIR (JS Puro) --}}
            <button onclick="document.getElementById('pdf-frame-{{ $orderId }}').contentWindow.print()"
                class="ticket-btn ticket-btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                IMPRIMIR
            </button>
        </div>

    </div>
</div>

<style>
    /* Animación de entrada */
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

    /* Fondo oscuro (Overlay) */
    .ticket-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        /* Oscuro con transparencia */
        backdrop-filter: blur(4px);
        /* Efecto borroso */
        z-index: 9999;
        /* Muy alto para estar encima de todo */
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        box-sizing: border-box;
    }

    /* Contenedor Principal del Modal */
    .ticket-modal-content {
        background-color: white;
        width: 100%;
        max-width: 450px;
        height: 60vh;
        /* 80% del alto de la pantalla */
        border-radius: 8px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: modalZoomIn 0.3s ease-out;
        position: relative;
    }

    /* Cabecera */
    .ticket-modal-header {
        background-color: #1f2937;
        /* Gris oscuro */
        color: white;
        padding: 12px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        /* No encoger */
    }

    .ticket-modal-title {
        margin: 0;
        font-size: 16px;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ticket-modal-close-icon {
        background: none;
        border: none;
        color: #9ca3af;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s;
    }

    .ticket-modal-close-icon:hover {
        color: white;
    }

    /* Cuerpo (Iframe) */
    .ticket-modal-body {
        flex: 1;
        /* Ocupa el espacio restante */
        background-color: #f3f4f6;
        position: relative;
        width: 100%;
    }

    .ticket-modal-body iframe {
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }

    /* Footer */
    .ticket-modal-footer {
        background-color: white;
        padding: 16px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 12px;
        flex-shrink: 0;
    }

    /* Botones Generales */
    .ticket-btn {
        flex: 1;
        padding: 12px;
        border-radius: 6px;
        font-weight: bold;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background-color 0.2s;
        border: none;
        text-transform: uppercase;
    }

    /* Botón Cerrar (Secundario) */
    .ticket-btn-secondary {
        background-color: #e5e7eb;
        color: #1f2937;
    }

    .ticket-btn-secondary:hover {
        background-color: #d1d5db;
    }

    /* Botón Imprimir (Primario) */
    .ticket-btn-primary {
        background-color: #2563eb;
        /* Azul */
        color: white;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .ticket-btn-primary:hover {
        background-color: #1d4ed8;
        /* Azul más oscuro */
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
</style>
