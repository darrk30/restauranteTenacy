@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pagarOrden.css') }}">
@endpush

<x-filament-panels::page>
    <div>
        <div class="pos-layout" x-data="posCheckout({
            subtotalBase: {{ (float) $subtotal_base }},
            metodosBackend: {{ $metodos_pago->toJson() }},
            taxRate: {{ (float) get_tax_percentage() }}
        })">

            {{-- LAYOUT PRINCIPAL: 2 COLUMNAS --}}
            <div class="layout-grid">

                {{-- COLUMNA IZQUIERDA: CLIENTE, COMPROBANTE, MÉTODOS --}}
                <div class="col-left">

                    {{-- CLIENTE SECTION --}}
                    <div class="card-section">
                        <div class="section-header">
                            <h3 class="section-title">CLIENTE</h3>
                            <button class="btn-nuevo" wire:click="mountAction('crearCliente')">NUEVO</button>
                        </div>

                        @if (!$cliente_seleccionado)
                            <div class="search-box" style="position: relative;">
                                {{-- Icono Lupa / Spinner --}}
                                <div
                                    style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); z-index: 10;">
                                    <div wire:loading wire:target="search_cliente">
                                        <svg class="animate-spin h-5 w-5 text-primary-600" viewBox="0 0 24 24"
                                            fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                                stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                    <svg wire:loading.remove wire:target="search_cliente" class="search-icon"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.35-4.35"></path>
                                    </svg>
                                </div>

                                <input type="text" class="search-input"
                                    wire:model.live.debounce.400ms="search_cliente"
                                    placeholder="Buscar por DNI, RUC o Nombre..."
                                    style="padding-left: 40px; padding-right: 40px; width: 100%;">

                                @if (strlen($search_cliente) > 0)
                                    <button type="button" wire:click="$set('search_cliente', '')"
                                        style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); z-index: 10;">
                                        <svg class="h-5 w-5 text-gray-400 hover:text-red-500" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                @endif

                                @if (count($resultados_clientes) > 0)
                                    <div class="dropdown-results">
                                        @foreach ($resultados_clientes as $c)
                                            <div class="result-row"
                                                wire:click="seleccionarCliente({{ $c->id }})">
                                                <div class="result-data">
                                                    <div class="result-name">
                                                        {{ $c->razon_social ?? $c->nombres . ' ' . $c->apellidos }}
                                                    </div>
                                                    <div class="result-doc">{{ $c->numero }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif(strlen($search_cliente) > 2)
                                    <div class="dropdown-results"
                                        style="padding: 10px; text-align: center; color: #ef4444; font-size: 0.85rem;">
                                        ⚠️ No se encontró el cliente
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="client-active">
                                <div class="client-data">
                                    <div class="client-name">
                                        {{ $cliente_seleccionado->razon_social ?? $cliente_seleccionado->nombres }}
                                    </div>
                                    <div class="client-doc">{{ $cliente_seleccionado->numero }}</div>
                                </div>
                                <button class="btn-change" wire:click="removerCliente">CAMBIAR</button>
                            </div>
                        @endif
                    </div>

                    {{-- COMPROBANTE SECTION --}}
                    <div class="card-section">
                        <div class="tabs-inline">
                            @foreach ($series as $serie)
                                <button type="button" class="tab-btn"
                                    :class="{ 'active': tipo === '{{ $serie->type_documento->value }}' }"
                                    @click="tipo = '{{ $serie->type_documento->value }}'; $wire.set('tipo_comprobante', '{{ $serie->type_documento->value }}')">

                                    <span>{{ strtoupper(str_replace('_', ' ', $serie->type_documento->value)) }}</span>
                                    <small>({{ $serie->serie }})</small>
                                </button>
                            @endforeach
                        </div>

                        {{-- Banner error --}}
                        <div x-show="tipo === 'Factura' && !esClienteRucValido" x-transition class="error-banner">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0z" />
                            </svg>
                            <span>Debe seleccionar un cliente con RUC válido (11 dígitos)</span>
                        </div>
                    </div>


                    {{-- MÉTODOS DE PAGO --}}
                    {{-- Sección de Métodos de Pago --}}
                    {{-- SECCIÓN MÉTODO DE PAGO --}}
                    <div class="card-section">
                        <h3 class="section-title">MÉTODO DE PAGO</h3>

                        <div class="payment-grid">
                            @foreach ($metodos_pago as $metodo)
                                <button type="button"
                                    class="payment-btn {{ $metodo_pago_seleccionado_id == $metodo->id ? 'active' : '' }}"
                                    wire:click="seleccionarMetodo({{ $metodo->id }})">
                                    <div class="payment-icon">
                                        @if ($metodo->image_path)
                                            <img src="{{ asset('storage/' . $metodo->image_path) }}">
                                        @else
                                            <x-heroicon-o-banknotes class="w-6 h-6" />
                                        @endif
                                    </div>
                                    <span class="payment-name">{{ $metodo->name }}</span>
                                </button>
                            @endforeach
                        </div>

                        <div class="responsive-payment-row">
                            {{-- Input con prefijo S/ --}}
                            <div class="flex-1">
                                <div style="position: relative; display: flex; align-items: center;">
                                    <span
                                        style="position: absolute; left: 12px; color: #6b7280; font-weight: bold; pointer-events: none;">S/</span>
                                    <input type="number" step="0.01" class="amount-input" wire:model="monto_a_pagar"
                                        style="padding-left: 35px; width: 100%;" placeholder="0.00">
                                </div>
                            </div>

                            @if ($requiere_referencia)
                                <div class="flex-1">
                                    <input type="text" wire:model="referencia_pago" class="amount-input"
                                        style="border-color: #f59e0b;" placeholder="N° Operación (Opcional)">
                                </div>
                            @endif

                            <div class="flex-btn">
                                <button class="btn-agregar" wire:click="agregarPago" wire:loading.attr="disabled"
                                    wire:target="agregarPago">
                                    <span wire:loading.remove wire:target="agregarPago">+ AGREGAR</span>
                                    <div wire:loading wire:target="agregarPago">
                                        <svg class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24"
                                            fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                                stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- SECCIÓN PAGOS REGISTRADOS (CON DISEÑO DE CARD) --}}
                    {{-- SECCIÓN PAGOS REGISTRADOS --}}
                    <div class="card-section">
                        <h3 class="section-title-modern">PAGOS REGISTRADOS</h3>

                        <div class="pagos-grid-modern">
                            @forelse ($pagos_agregados as $index => $pago)
                                <div class="pago-card"
                                    style="position: relative; overflow: hidden; background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div class="pago-card-content">
                                        <div class="pago-details" style="display: flex; flex-direction: column;">
                                            <span class="pago-name-tag"
                                                style="font-weight: bold; color: #374151; font-size: 0.85rem; text-transform: uppercase;">
                                                {{ $pago['name'] }}
                                            </span>

                                            @if (!empty($pago['referencia']))
                                                <span style="font-size: 11px; color: #f59e0b; font-weight: 600;">
                                                    Ref: {{ $pago['referencia'] }}
                                                </span>
                                            @endif

                                            <div class="pago-amount-display"
                                                style="margin-top: 4px; color: #111827; font-size: 1.1rem; font-weight: 800;">
                                                <span>S/ {{ number_format($pago['amount'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" class="btn-delete-minimal"
                                        wire:click="quitarPago({{ $index }})" wire:loading.attr="disabled"
                                        wire:target="quitarPago({{ $index }})"
                                        style="color: #ef4444; background: #fef2f2; border-radius: 8px; padding: 8px; border: none; cursor: pointer;">

                                        <div wire:loading.remove wire:target="quitarPago({{ $index }})">
                                            <x-heroicon-o-trash class="w-5 h-5" />
                                        </div>

                                        <div wire:loading wire:target="quitarPago({{ $index }})">
                                            <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                        </div>
                                    </button>
                                </div>
                                {{-- 🟢 LA CORRECCIÓN ESTÁ AQUÍ: De @afterelse a @empty --}}
                            @empty
                                <div class="empty-state-simple"
                                    style="text-align: center; color: #9ca3af; padding: 20px;">
                                    No hay pagos registrados
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- COLUMNA DERECHA: RESUMEN --}}
                <div class="col-right">
                    <div class="card-section white-bg">
                        <h3 class="section-title">RESUMEN</h3>
                        <div class="items-summary">
                            @foreach ($items as $item)
                                <div class="item-summary-row">
                                    <span class="item-number">x{{ $item->cantidad }}</span>
                                    <div class="item-summary-name">{{ $item->product_name }}</div>
                                    <span class="item-summary-price">S/
                                        {{ number_format($item->subTotal ?? $item->price * $item->cantidad, 2) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="discount-wrapper">
                            <label class="discount-label">Descuento Global</label>
                            <div class="discount-field">
                                <span class="currency-symbol">S/</span>
                                <input type="number" x-model="discountInput" @blur="validateDiscount()"
                                    class="minimal-input" placeholder="0.00">
                            </div>
                        </div>

                        <div class="totals-summary">
                            <div class="summary-row"><span>GRAVADA</span><span x-text="'S/ ' + opGravada"></span>
                            </div>
                            <div class="summary-row">
                                <span>IGV (<span x-text="taxPercentage"></span>%)</span>
                                <span x-text="'S/ ' + igv"></span>
                            </div>
                            <div class="summary-row" style="color: #EF4444;"><span>DESCUENTO</span><span
                                    x-text="'- S/ ' + (parseFloat(discountInput)||0).toFixed(2)"></span></div>
                            <div class="divider"></div>
                            <div class="summary-row final"><span>TOTAL</span><span
                                    x-text="'S/ ' + totalFinal.toFixed(2)"></span></div>
                        </div>
                    </div>

                    <div class="card-section white-bg">
                        <div class="dark-row"><span>SALDO</span><span>PAGADO</span></div>
                        <div class="dark-amount">
                            <span class="amount-pending" x-text="'S/ ' + (faltante > 0 ? faltante : '0.00')"></span>
                            <span class="amount-paid" x-text="'S/ ' + totalPagado.toFixed(2)"></span>
                        </div>
                        <div x-show="vuelto > 0" class="vuelto-section">
                            <span class="vuelto-label">VUELTO</span>
                            <span class="vuelto-amount" x-text="'S/ ' + vuelto"></span>
                        </div>
                    </div>

                    {{-- ✅ MEJORADO: Botón deshabilitado si es Factura sin RUC --}}
                    @can('cobrar_pedido_rest')
                        <button class="btn-finish" wire:click="procesarPagoFinal" wire:loading.attr="disabled"
                            :disabled="faltante > 0 || (tipo === 'Factura' && !esClienteRucValido)">

                            <span wire:loading.remove wire:target="procesarPagoFinal">COMPLETAR VENTA</span>
                            <span wire:loading wire:target="procesarPagoFinal">PROCESANDO...</span>
                        </button>
                    @endcan
                </div>
            </div>
        </div>

        {{-- PANTALLA COMPLETA DE VENTA EXITOSA --}}
        <div id="contenedor-pantalla-exito">
            @if ($mostrarPantallaExito && $ventaExitosaId)
                <div class="pantalla-exito-overlay">
                    <div class="modal-exito-contenedor animate-zoom-in"
                        style="max-width: 450px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">

                        {{-- HEADER --}}
                        <div class="modal-exito-header"
                            style="padding: 20px; background: #10b981; color: white; text-align: center;">
                            <div class="icono-check-circulo" style="margin-bottom: 10px;">
                                <x-heroicon-o-check-badge
                                    style="width: 50px; height: 50px; color: white; margin: 0 auto;" />
                            </div>
                            <h2 style="font-size: 1.5rem; font-weight: bold; margin:0;">¡Venta Exitosa!</h2>
                            <p style="margin: 5px 0 0 0; font-size: 0.9rem; opacity: 0.9;">Documento registrado
                                correctamente</p>
                        </div>

                        <div class="modal-exito-body" style="padding: 20px;">

                            {{-- ESCENARIO: MODAL DE COMPROBANTE ACTIVO --}}
                            @if ($puedeImprimirComprobante)
                                <div class="ticket-wrapper"
                                    style="position: relative; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 20px; background: #f9fafb;">

                                    {{-- LA CINTA DE ESTADO --}}
                                    @if ($esDirecta && !$impresionFallida)
                                        <div
                                            style="background: #ecfdf5; color: #065f46; padding: 8px; text-align: center; font-size: 0.75rem; font-weight: bold; border-bottom: 1px solid #10b981; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                            <x-heroicon-s-printer class="w-4 h-4" />
                                            ✔ TICKET ENVIADO A IMPRESORA
                                        </div>
                                    @elseif($impresionFallida)
                                        <div
                                            style="background: #fef2f2; color: #991b1b; padding: 8px; text-align: center; font-size: 0.75rem; font-weight: bold; border-bottom: 1px solid #f87171; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                            <x-heroicon-s-exclamation-triangle class="w-4 h-4" />
                                            ⚠ FALLÓ LA IMPRESIÓN AUTOMÁTICA
                                        </div>
                                    @endif

                                    <iframe
                                        src="{{ route('sales.print.ticket', ['sale' => $ventaExitosaId]) }}?hide_actions=1"
                                        class="ticket-iframe"
                                        style="width: 100%; height: 300px; border: none; display: block;">
                                    </iframe>
                                </div>
                            @else
                                {{-- ESCENARIO: COMPROBANTE OCULTO PERO IMPRESIÓN DIRECTA ACTIVA --}}
                                <div
                                    style="text-align: center; padding: 20px; background: #f9fafb; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #d1d5db;">
                                    <x-heroicon-o-document-check class="w-12 h-12 mx-auto text-gray-400" />
                                    @if ($esDirecta && !$impresionFallida)
                                        <p
                                            style="margin-top: 10px; color: #059669; font-weight: bold; font-size: 0.9rem;">
                                            ✔ Ticket enviado a impresora</p>
                                    @else
                                        <p style="margin-top: 10px; color: #6b7280; font-size: 0.9rem;">Comprobante
                                            guardado correctamente</p>
                                    @endif
                                </div>
                            @endif

                            {{-- GRUPO DE BOTONES --}}
                            <div class="grupo-botones-exito"
                                style="display: grid; grid-template-columns: {{ $puedeImprimirComprobante || $esDirecta ? '1fr 1fr' : '1fr' }}; gap: 10px;">

                                @if ($puedeImprimirComprobante || $esDirecta)
                                    <button onclick="manejadorImpresion(this)" class="btn-exito"
                                        style="background: {{ $impresionFallida ? '#fee2e2' : '#f3f4f6' }}; color: {{ $impresionFallida ? '#991b1b' : '#374151' }}; border: 1px solid {{ $impresionFallida ? '#f87171' : '#d1d5db' }}; border-radius: 8px; padding: 10px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;">

                                        @if ($esDirecta && !$impresionFallida)
                                            <x-heroicon-o-bolt class="w-4 h-4" /> <span>Reimprimir</span>
                                        @else
                                            <x-heroicon-o-printer class="w-4 h-4" /> <span>Mostrar Ticket</span>
                                        @endif
                                    </button>
                                @endif

                                <button wire:click="terminarProcesoVenta" class="btn-exito"
                                    style="background: #10b981; color: white; border: none; border-radius: 8px; padding: 10px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;">
                                    <span>Siguiente</span>
                                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        <x-filament-actions::modals />
        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('posCheckout', (config) => ({
                        // 🟢 Datos base (Vienen del servidor o configuración)
                        subtotalBase: @entangle('subtotal_base'),
                        taxPercentage: parseFloat(config.taxRate || 18),

                        // 🟢 Sincronización Directa (Alpine solo "mira" lo que PHP hace)
                        discountInput: @entangle('monto_descuento'),
                        pagos: @entangle('pagos_agregados'),
                        tipo: @entangle('tipo_comprobante'),
                        clienteTieneRuc: @entangle('cliente_tiene_ruc'),

                        // 🟢 Variables de UI para el formulario de entrada
                        // Nota: Estas ahora se controlan mejor mediante wire:model en el HTML
                        currentAmount: @entangle('monto_a_pagar'),
                        requiereReferencia: @entangle('requiere_referencia'),

                        init() {
                            // Reacciona cuando el cliente cambia para ajustar el tipo de comprobante
                            Livewire.on('cliente-seleccionado', ({
                                esRuc
                            }) => {
                                if (esRuc) this.tipo = 'Factura';
                            });
                        },

                        // --- CÁLCULOS DINÁMICOS (Solo para visualización en el resumen) ---
                        get totalFinal() {
                            return Math.max(0, this.subtotalBase - (parseFloat(this.discountInput) || 0));
                        },

                        get divisor() {
                            return 1 + (this.taxPercentage / 100);
                        },

                        get opGravada() {
                            return (this.totalFinal / this.divisor).toFixed(2);
                        },

                        get igv() {
                            return (this.totalFinal - (this.totalFinal / this.divisor)).toFixed(2);
                        },

                        get totalPagado() {
                            return (this.pagos || []).reduce((sum, p) => sum + parseFloat(p.amount || 0),
                                0);
                        },

                        get faltante() {
                            let f = this.totalFinal - this.totalPagado;
                            return f > 0.001 ? f.toFixed(2) : '0.00';
                        },

                        get vuelto() {
                            let v = this.totalPagado - this.totalFinal;
                            return v > 0.001 ? v.toFixed(2) : '0.00';
                        },

                        get esClienteRucValido() {
                            return this.clienteTieneRuc === true;
                        },

                        // --- MÉTODOS DE APOYO VISUAL ---
                        validateDiscount() {
                            // El servidor procesará el descuento, pero esto ayuda a la UI a no mostrar negativos
                            if (parseFloat(this.discountInput) > this.subtotalBase) {
                                this.discountInput = this.subtotalBase;
                            }
                        }
                    }));
                });

                async function manejadorImpresion(btn) {
                    // 1. Obtenemos los valores EN TIEMPO REAL desde el objeto @this
                    const esDirecta = await @this.get('esDirecta');
                    const impresionFallida = await @this.get('impresionFallida');
                    const ventaId = await @this.get('ventaExitosaId');
                    if (esDirecta && !impresionFallida) {
                        const originalHTML = btn.innerHTML;
                        btn.innerHTML = '<span>Procesando...</span>';
                        btn.disabled = true;
                        const exito = await @this.reimprimirDirecto();
                        btn.disabled = false;
                        if (!exito) {
                            console.log("El envío directo falló, el botón cambiará de estado.");
                        } else {
                            console.log("Envío directo exitoso.");
                            btn.innerHTML = `<x-heroicon-o-printer class="w-4 h-4" /> <span>Reimprimir</span>`;
                        }
                        return;
                    }

                    const iframe = document.querySelector('.ticket-iframe');
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.print();
                    } else {
                        if (ventaId) {
                            const url = `/sales/print-ticket/${ventaId}`;
                            window.open(url, '_blank');
                        } else {
                            alert("No se encontró el ID de la venta. Intenta refrescar.");
                        }
                    }
                }
            </script>
        @endpush
    </div>
</x-filament-panels::page>
