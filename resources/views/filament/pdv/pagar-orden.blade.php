@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pagarOrden.css') }}">
@endpush

<x-filament-panels::page>
    <div class="pos-layout" x-data="posCheckout({
        subtotalBase: {{ $subtotal_base }},
        metodosBackend: {{ $metodos_pago->toJson() }}
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

                            <input type="text" class="search-input" wire:model.live.debounce.400ms="search_cliente"
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
                                        <div class="result-row" wire:click="seleccionarCliente({{ $c->id }})">
                                            <div class="result-data">
                                                <div class="result-name">
                                                    {{ $c->razon_social ?? $c->nombres . ' ' . $c->apellidos }}</div>
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
                                    {{ $cliente_seleccionado->razon_social ?? $cliente_seleccionado->nombres }}</div>
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
                <div class="card-section" x-data="{
                    requiereReferencia: false,
                    handleSelect(metodo) {
                        this.selectMethod(metodo);
                        this.requiereReferencia = metodo.requiere_referencia;
                        if (!this.requiereReferencia) $wire.referencia_pago = '';
                    }
                }">
                    <h3 class="section-title" style="margin-bottom: 12px;">MÉTODO DE PAGO</h3>

                    <div class="payment-grid">
                        <template x-for="metodo in metodosDisponibles" :key="metodo.id">
                            <button class="payment-btn" :class="{ 'active': currentMethodId == metodo.id }"
                                @click="handleSelect(metodo)">
                                <div class="payment-icon">
                                    <template x-if="metodo.image_path">
                                        <img :src="'/storage/' + metodo.image_path">
                                    </template>
                                    <template x-if="!metodo.image_path">
                                        <svg viewBox="0 0 24 24" fill="currentColor">
                                            <path
                                                d="M20 8H4V6h16m0 10H4v-4h16m0-6H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" />
                                        </svg>
                                    </template>
                                </div>
                                <span class="payment-name" x-text="metodo.name"></span>
                            </button>
                        </template>
                    </div>

                    <div class="responsive-payment-row">

                        <div class="flex-1">
                            <input type="number" class="amount-input" x-model="currentAmount"
                                @keydown.enter="addPayment()" placeholder="S/ 0.00" onfocus="this.select()">
                        </div>

                        <template x-if="requiereReferencia">
                            <div class="flex-1" style="animation: fadeIn 0.3s;">
                                <input type="text" x-model="$wire.referencia_pago" class="amount-input"
                                    style="border-color: #f59e0b;" placeholder="N° Operación...">
                            </div>
                        </template>

                        <div class="flex-btn">
                            <button class="btn-agregar" @click="addPayment(); requiereReferencia = false;"
                                :disabled="!canAddPayment" style="width: 100%; margin: 0;">
                                + AGREGAR
                            </button>
                        </div>
                    </div>
                </div>

                {{-- PAGOS PARCIALES --}}
                <div class="card-section">
                    <h3 class="section-title-modern">PAGOS REGISTRADOS</h3>
                    <div class="pagos-grid-modern">
                        <template x-for="(pago, index) in pagos" :key="index">
                            <div class="pago-card" style="position: relative; overflow: hidden;">
                                {{-- ICONO DE MARCA DE AGUA (BILLETE) --}}
                                <div class="pago-watermark">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M17 9V7C17 5.89543 16.1046 5 15 5H3C1.89543 5 1 5.89543 1 7V15C1 16.1046 1.89543 17 3 17H5M9 9H21C22.1046 9 23 9.89543 23 11V19C23 20.1046 22.1046 21 21 21H9C7.89543 21 7 20.1046 7 19V11C7 9.89543 7.89543 9 9 9ZM15 17C16.1046 17 17 16.1046 17 15C17 13.8954 16.1046 13 15 13C13.8954 13 13 13.8954 13 15C13 16.1046 13.8954 17 15 17Z"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <div class="pago-card-content">
                                    <div class="pago-details">
                                        <div style="display: flex; flex-direction: column;">
                                            <span class="pago-name-tag" x-text="pago.name"></span>

                                            <template x-if="pago.referencia">
                                                <span
                                                    style="font-size: 10px; color: #f59e0b; font-weight: bold; margin-top: 2px;">
                                                    <span style="opacity: 0.7;">Ref:</span> <span
                                                        x-text="pago.referencia"></span>
                                                </span>
                                            </template>
                                        </div>

                                        <div class="pago-amount-display">
                                            <span class="pago-currency">S/</span>
                                            <span class="pago-value"
                                                x-text="parseFloat(pago.amount).toFixed(2)"></span>
                                        </div>
                                    </div>
                                </div>
                                <button class="btn-delete-minimal" @click="removePayment(index)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path
                                            d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                    <div x-show="pagos.length === 0" class="empty-state-simple">No hay pagos registrados</div>
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
                        <div class="summary-row"><span>GRAVADA</span><span x-text="'S/ ' + opGravada"></span></div>
                        <div class="summary-row"><span>IGV (18%)</span><span x-text="'S/ ' + igv"></span></div>
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
                <button class="btn-finish" wire:click="procesarPagoFinal" wire:loading.attr="disabled"
                    :disabled="faltante > 0 || (tipo === 'Factura' && !esClienteRucValido)">

                    <span wire:loading.remove wire:target="procesarPagoFinal">COMPLETAR VENTA</span>
                    <span wire:loading wire:target="procesarPagoFinal">PROCESANDO...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- PANTALLA COMPLETA DE VENTA EXITOSA --}}
    <div id="contenedor-pantalla-exito">
        @if ($mostrarPantallaExito && $ventaExitosaId)
            <div class="pantalla-exito-overlay">
                <div class="modal-exito-contenedor animate-zoom-in">

                    <div class="modal-exito-header">
                        <div class="icono-check-circulo">
                            <x-heroicon-o-check-badge style="width: 40px; height: 40px; color: white;" />
                        </div>
                        <h2 class="titulo-exito" style="font-size: 1.5rem; font-weight: bold; margin:0;">¡Venta
                            Exitosa!</h2>
                        <p style="margin: 5px 0 0 0; opacity: 0.9;">Comprobante generado correctamente</p>
                    </div>

                    <div class="modal-exito-body">
                        <div class="ticket-wrapper">
                            <iframe
                                src="{{ route('sales.print.ticket', ['sale' => $ventaExitosaId]) }}?hide_actions=1"
                                class="ticket-iframe"></iframe>
                        </div>

                        <div class="grupo-botones-exito">
                            <button onclick="ejecutarReimpresion(this)" class="btn-exito btn-reimprimir">
                                <x-heroicon-o-printer class="btn-icon" style="width: 20px; height: 20px;" />

                                <svg class="btn-loader hidden animate-spin" style="width: 20px; height: 20px;"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4" fill="none"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>

                                <span>Reimprimir</span>
                            </button>

                            <button wire:click="terminarProcesoVenta" wire:loading.attr="disabled"
                                class="btn-exito btn-nueva-venta">
                                {{-- Estado Normal --}}
                                <div wire:loading.remove class="flex items-center gap-2">
                                    <span>Nueva Venta</span>
                                    <x-heroicon-o-arrow-right style="width: 20px; height: 20px;" />
                                </div>

                                {{-- Estado Cargando --}}
                                <div wire:loading class="flex items-center gap-2">
                                    <svg class="animate-spin" style="width: 20px; height: 20px;" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4" fill="none"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
    <x-filament-actions::modals />

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('posCheckout', ({
                subtotalBase,
                metodosBackend
            }) => ({
                subtotalBase: parseFloat(subtotalBase),
                discountInput: @entangle('monto_descuento'),
                pagos: @entangle('pagos_agregados'),
                tipo: @entangle('tipo_comprobante'),
                clienteActivo: @entangle('cliente_seleccionado'),
                clienteTieneRuc: @entangle('cliente_tiene_ruc'),


                metodosDisponibles: metodosBackend,
                currentMethodId: metodosBackend.length > 0 ? metodosBackend[0].id : null,
                currentMethodName: metodosBackend.length > 0 ? metodosBackend[0].name : '',
                currentAmount: '',
                requiereReferencia: false,

                init() {
                    this.$nextTick(() => {
                        this.currentAmount = this.totalFinal.toFixed(2);
                    });
                    this.$watch('pagos', value => {
                        $wire.set('pagos_agregados', value);
                    })

                    // ✅ Escuchar cambios desde Livewire
                    Livewire.on('cliente-seleccionado', ({
                        esRuc
                    }) => {
                        if (esRuc) this.tipo = 'Factura';
                    });
                },

                get totalFinal() {
                    return Math.max(0, this.subtotalBase - (parseFloat(this.discountInput) || 0));
                },
                get opGravada() {
                    return (this.totalFinal / 1.18).toFixed(2);
                },
                get igv() {
                    return (this.totalFinal - (this.totalFinal / 1.18)).toFixed(2);
                },
                get totalPagado() {
                    return this.pagos.reduce((sum, p) => sum + parseFloat(p.amount), 0);
                },
                get faltante() {
                    let f = this.totalFinal - this.totalPagado;
                    return f > 0 ? f.toFixed(2) : 0;
                },
                get vuelto() {
                    let v = this.totalPagado - this.totalFinal;
                    return v > 0 ? v.toFixed(2) : 0;
                },
                get canAddPayment() {
                    return parseFloat(this.currentAmount) > 0 && this.currentMethodId != null;
                },

                get esClienteRucValido() {
                    return this.clienteTieneRuc === true;
                },

                validateDiscount() {
                    let num = parseFloat(this.discountInput) || 0;
                    this.discountInput = Math.min(Math.max(0, num), this.subtotalBase);
                    this.updateCurrentAmount();
                },

                selectMethod(metodo) {
                    this.currentMethodId = metodo.id;
                    this.currentMethodName = metodo.name;
                    this.requiereReferencia = metodo.requiere_referencia;
                    this.updateCurrentAmount();
                },

                updateCurrentAmount() {
                    let porPagar = this.totalFinal - this.totalPagado;
                    this.currentAmount = porPagar > 0 ? porPagar.toFixed(2) : '';
                },

                addPayment() {
                    if (!this.canAddPayment) return;

                    let amountToAdd = parseFloat(this.currentAmount);

                    // CORRECCIÓN: Acceder a Livewire correctamente
                    let referenciaActual = this.$wire.referencia_pago || '';

                    let existingIndex = this.pagos.findIndex(p =>
                        p.id == this.currentMethodId && p.referencia == referenciaActual
                    );

                    if (existingIndex !== -1) {
                        this.pagos[existingIndex].amount = (parseFloat(this.pagos[existingIndex]
                            .amount) + amountToAdd);
                    } else {
                        this.pagos.push({
                            id: this.currentMethodId,
                            name: this.currentMethodName,
                            amount: amountToAdd,
                            referencia: referenciaActual
                        });
                    }

                    this.updateCurrentAmount();
                    this.$wire.set('referencia_pago', ''); // Limpiar en el backend
                    this.requiereReferencia = false; // Ahora sí funcionará
                },

                removePayment(index) {
                    this.pagos.splice(index, 1);
                    this.updateCurrentAmount();
                }
            }));
        });

        function ejecutarReimpresion(btn) {
            const icon = btn.querySelector('.btn-icon');
            const loader = btn.querySelector('.btn-loader');
            const frame = document.querySelector('.ticket-iframe');

            // Escuchamos el evento de finalización dentro del IFRAME
            frame.contentWindow.onafterprint = () => {
                icon.classList.remove('hidden');
                loader.classList.add('hidden');
                btn.style.pointerEvents = 'auto';
                console.log("Impresión finalizada o cancelada");
            };

            // Estado cargando
            btn.style.pointerEvents = 'none';
            icon.classList.add('hidden');
            loader.classList.remove('hidden');

            // Ejecutar impresión
            frame.contentWindow.focus();
            frame.contentWindow.print();

            // Fallback: Si por alguna razón el navegador no dispara onafterprint
            setTimeout(() => {
                if (icon.classList.contains('hidden')) {
                    icon.classList.remove('hidden');
                    loader.classList.add('hidden');
                    btn.style.pointerEvents = 'auto';
                }
            }, 5000);
        }
    </script>
</x-filament-panels::page>
