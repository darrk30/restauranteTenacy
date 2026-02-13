<x-filament-panels::page>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/pdv_mesas.css') }}">
        <style>
            .ticket-card {
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                transition: transform 0.2s, box-shadow 0.2s;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                border: 1px solid #e5e7eb;
                position: relative;
            }

            .dark .ticket-card {
                background: #1f2937;
                border-color: #374151;
            }

            .ticket-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            }

            /* Borde superior de color (indica estado) */
            .ticket-status-bar {
                height: 6px;
                width: 100%;
            }

            /* Cabecera del ticket */
            .ticket-header {
                padding: 12px 15px;
                border-bottom: 2px dashed #e5e7eb;
                /* Línea punteada clave del efecto ticket */
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: #fcfcfc;
            }

            .dark .ticket-header {
                background-color: #252f3e;
                border-bottom-color: #4b5563;
            }

            /* Cuerpo del ticket */
            .ticket-body {
                padding: 15px;
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            /* Pie del ticket (Botones) */
            .ticket-footer {
                padding: 10px 15px;
                border-top: 1px solid #e5e7eb;
                background-color: #f9fafb;
                display: flex;
                gap: 8px;
            }

            .dark .ticket-footer {
                background-color: #111827;
                border-top-color: #374151;
            }

            /* BADGES */
            .badge-pill {
                font-size: 10px;
                font-weight: 800;
                padding: 3px 8px;
                border-radius: 99px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: inline-block;
            }

            .badge-gray {
                background: #f3f4f6;
                color: #374151;
            }

            /* Pendiente */
            .badge-green {
                background: #dcfce7;
                color: #166534;
            }

            /* Pagado */
            .badge-yellow {
                background: #fef9c3;
                color: #854d0e;
            }

            /* Preparando */
            .badge-blue {
                background: #dbeafe;
                color: #1e40af;
            }

            /* Enviado */

            /* BOTONES */
            .btn-action {
                flex: 1;
                padding: 8px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: bold;
                cursor: pointer;
                border: none;
                transition: 0.2s;
                text-align: center;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 5px;
            }

            .btn-eye {
                background: #e5e7eb;
                color: #374151;
                width: 40px;
                flex: none;
            }

            .btn-eye:hover {
                background: #d1d5db;
            }

            .btn-primary {
                background: #111827;
                color: white;
            }

            .btn-primary:hover {
                background: #000;
            }

            .btn-info {
                background: #3b82f6;
                color: white;
            }

            .btn-info:hover {
                background: #2563eb;
            }

            .btn-success {
                background: #10b981;
                color: white;
            }

            .btn-success:hover {
                background: #059669;
            }

            /* --- ESTILOS MODAL DETALLES (MODERNO) --- */
            .dm-header {
                padding: 20px 25px;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #ffffff;
            }

            .dark .dm-header {
                background: #1f2937;
                border-bottom-color: #374151;
            }

            .dm-title {
                font-size: 18px;
                font-weight: 800;
                color: #0f172a;
                margin: 0;
            }

            .dark .dm-title {
                color: #f3f4f6;
            }

            .dm-close {
                background: #f1f5f9;
                border: none;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                color: #64748b;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: 0.2s;
                font-size: 18px;
            }

            .dm-close:hover {
                background: #e2e8f0;
                color: #0f172a;
            }

            .dark .dm-close {
                background: #374151;
                color: #9ca3af;
            }

            .dark .dm-close:hover {
                background: #4b5563;
                color: white;
            }

            .dm-body {
                padding: 25px;
                overflow-y: auto;
                max-height: 70vh;
            }

            /* Info Cards (Cliente/Envío) */
            .dm-grid-info {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 25px;
            }

            @media(max-width: 600px) {
                .dm-grid-info {
                    grid-template-columns: 1fr;
                }
            }

            .dm-card-info {
                background: #f8fafc;
                border-radius: 12px;
                padding: 15px;
                border: 1px solid #e2e8f0;
            }

            .dark .dm-card-info {
                background: #111827;
                border-color: #374151;
            }

            .dm-label {
                font-size: 11px;
                text-transform: uppercase;
                color: #94a3b8;
                font-weight: 700;
                margin-bottom: 4px;
                display: block;
            }

            .dm-value {
                font-size: 14px;
                font-weight: 600;
                color: #334155;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .dark .dm-value {
                color: #e2e8f0;
            }

            /* Tabla de Productos */
            .dm-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }

            .dm-table th {
                text-align: left;
                font-size: 11px;
                text-transform: uppercase;
                color: #64748b;
                padding: 10px 0;
                border-bottom: 2px solid #f1f5f9;
            }

            .dm-table td {
                padding: 12px 0;
                border-bottom: 1px dashed #e2e8f0;
                color: #334155;
                font-size: 14px;
            }

            .dark .dm-table th {
                color: #94a3b8;
                border-bottom-color: #374151;
            }

            .dark .dm-table td {
                color: #e2e8f0;
                border-bottom-color: #374151;
            }

            .dm-qty-badge {
                background: #e0e7ff;
                color: #4338ca;
                font-weight: 700;
                font-size: 12px;
                width: 24px;
                height: 24px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .dark .dm-qty-badge {
                background: #3730a3;
                color: #e0e7ff;
            }

            .dm-price {
                font-weight: 700;
                text-align: right;
            }

            /* Footer / Total */
            .dm-footer {
                padding: 20px 25px;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .dark .dm-footer {
                background: #111827;
                border-top-color: #374151;
            }

            .dm-total-label {
                font-size: 13px;
                font-weight: 600;
                color: #64748b;
            }

            .dm-total-amount {
                font-size: 24px;
                font-weight: 900;
                color: #0f172a;
            }

            .dark .dm-total-amount {
                color: white;
            }

            .dm-note {
                font-size: 12px;
                color: #ef4444;
                margin-top: 2px;
                font-style: italic;
            }
        </style>
    @endpush

    <div x-data="{
        ...mesasLogic(),
        canalActivo: 'salon',
        modalOpen: false,
        modalType: '',
        openModal(type) {
            this.modalType = type;
            this.modalOpen = true;
        },
        closeModals() {
            this.modalOpen = false;
            $wire.resetForm();
        }
    }" class="w-full h-full" @scroll.window="menuOpen = false">

        <div class="main-channels">
            <button @click="canalActivo = 'salon'" :class="canalActivo === 'salon' ? 'active' : ''"
                class="channel-btn">🏢 SALÓN</button>
            <button @click="canalActivo = 'llevar'" :class="canalActivo === 'llevar' ? 'active' : ''"
                class="channel-btn">🛍️ LLEVAR</button>
            <button @click="canalActivo = 'delivery'" :class="canalActivo === 'delivery' ? 'active' : ''"
                class="channel-btn">🛵 DELIVERY</button>
        </div>

        {{-- SECCIÓN SALÓN --}}
        <div x-show="canalActivo === 'salon'" x-transition:enter.duration.200ms>
            <div x-data="{ tab: 1 }" style="width:100%;">
                <div class="summary-row">
                    <div class="summary-badge free-bg">Libres</div>
                    <div class="summary-badge occ-bg">Ocupadas</div>
                    <div class="summary-badge pay-bg">Pagando</div>
                </div>
                <div class="pdv-tabs">
                    @foreach ($floors as $i => $floor)
                        <button @click="tab = {{ $i + 1 }}"
                            :class="tab === {{ $i + 1 }} ? 'pdv-tab active' : 'pdv-tab'">{{ $floor->name }}</button>
                    @endforeach
                </div>
                @foreach ($floors as $i => $floor)
                    <div x-show="tab === {{ $i + 1 }}" x-transition.opacity.duration.200ms>
                        <div class="pdv-grid">
                            @foreach ($floor->tables as $table)
                                @php
                                    $raw = strtolower($table->estado_mesa ?? ($table->status ?? 'libre'));
                                    $key =
                                        ['ocupada' => 'occupied', 'pagando' => 'paying', 'libre' => 'free'][$raw] ??
                                        'free';
                                    $jsData =
                                        "{ id: {$table->id}, orderId: " .
                                        ($table->order_id ?? 'null') .
                                        ", status: '$key' }";
                                @endphp
                                <div class="pdv-card pdv-{{ $key }}"
                                    @click="handleCardClick({{ $jsData }})"
                                    @contextmenu.prevent.stop="openMenu($event.clientX, $event.clientY, {{ $jsData }})">
                                    <div class="badge">
                                        {{ ['free' => 'Libre', 'occupied' => 'Ocupada', 'paying' => 'Pagando'][$key] }}
                                    </div>
                                    <img class="pdv-icon"
                                        src="{{ asset('img/' . ($key === 'free' ? 'mesalibre.png' : 'mesaocupada.png')) }}">
                                    <div class="mesa-name">{{ $table->name }}</div>
                                    <div class="people">👥 {{ $table->asientos }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- SECCIÓN LLEVAR --}}
        <div x-show="canalActivo === 'llevar'" x-cloak>
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 0 10px 15px;">
                <h2 class="section-title">Órdenes: Llevar</h2>
                <button @click="openModal('llevar')" class="channel-btn active">+ Nueva Orden</button>
            </div>

            <div class="order-grid">
                @forelse($ordersLlevar as $order)
                    @include('filament.pdv.partials.ticket-card', [
                        'order' => $order,
                        'canal' => 'llevar',
                    ])
                @empty
                    <div style="grid-column:1/-1; text-align:center; padding:40px; opacity:0.5;">No hay órdenes
                        pendientes.</div>
                @endforelse
            </div>
        </div>

        {{-- SECCIÓN DELIVERY --}}
        <div x-show="canalActivo === 'delivery'" x-cloak>
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 0 10px 15px;">
                <h2 class="section-title">Órdenes: Delivery</h2>
                <button @click="openModal('delivery')" class="channel-btn active" style="background:#2563eb;">+ Nuevo
                    Delivery</button>
            </div>

            <div class="order-grid">
                @forelse($ordersDelivery as $order)
                    @include('filament.pdv.partials.ticket-card', [
                        'order' => $order,
                        'canal' => 'delivery',
                    ])
                @empty
                    <div style="grid-column:1/-1; text-align:center; padding:40px; opacity:0.5;">No hay envíos
                        pendientes.</div>
                @endforelse
            </div>
        </div>

        {{-- MODAL UNIFICADO (LLEVAR / DELIVERY) --}}
        {{-- MODAL UNIFICADO (LLEVAR / DELIVERY) --}}
        <div x-show="modalOpen" x-cloak class="modal-custom-overlay" x-transition.opacity>
            <div class="modal-custom-box" @click.outside="closeModals()">

                {{-- BARRA DE CARGA SUTIL --}}
                <div wire:loading wire:target="consultarDocumento, prepararClienteYRedirigir"
                    class="subtle-loading-bar"></div>

                <div class="modal-custom-header">
                    <h3 class="flex items-center gap-2">
                        <span x-text="modalType === 'llevar' ? '🛍️ Orden Llevar' : '🛵 Nuevo Delivery'"></span>
                    </h3>
                    <span @click="closeModals()" style="cursor:pointer; font-size:28px;">&times;</span>
                </div>

                <div class="modal-custom-body" wire:loading.class="loading-blur"
                    wire:target="consultarDocumento, prepararClienteYRedirigir">

                    {{-- DOCUMENTO: Se adapta en ancho --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="field-group">
                            <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Tipo Doc:</label>
                            <select wire:model.live="tipoDoc" class="modal-custom-input">
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                            </select>
                        </div>
                        <div class="field-group sm:col-span-2">
                            <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Nro
                                Documento:</label>
                            <div class="input-group-search">
                                <input type="text" wire:model.defer="numDoc"
                                    class="modal-custom-input @error('numDoc') input-error @enderror"
                                    placeholder="Buscar nro...">
                                <button wire:click="consultarDocumento" class="btn-search-doc"
                                    wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="consultarDocumento">🔍</span>
                                    <x-filament::loading-indicator wire:loading wire:target="consultarDocumento"
                                        class="h-4 w-4" />
                                </button>
                            </div>
                            @error('numDoc')
                                <span class="error-text">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    {{-- NOMBRES --}}
                    <div class="field-group">
                        <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">
                            {{ $tipoDoc === 'RUC' ? 'Razón Social:' : 'Nombres:' }}
                        </label>
                        <input type="text" wire:model="nombresCliente"
                            class="modal-custom-input @error('nombresCliente') input-error @enderror"
                            placeholder="Ingrese información...">
                        @error('nombresCliente')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- APELLIDOS --}}
                    @if ($tipoDoc === 'DNI')
                        <div class="field-group" x-transition>
                            <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Apellidos:</label>
                            <input type="text" wire:model="apellidosCliente"
                                class="modal-custom-input @error('apellidosCliente') input-error @enderror"
                                placeholder="Apellido paterno y materno">
                            @error('apellidosCliente')
                                <span class="error-text">{{ $message }}</span>
                            @enderror
                        </div>
                    @endif

                    {{-- TELÉFONO Y DIRECCIÓN --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="field-group">
                            <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Teléfono:</label>
                            <input type="tel" wire:model="telefonoCliente"
                                class="modal-custom-input @error('telefonoCliente') input-error @enderror"
                                placeholder="999...">
                            @error('telefonoCliente')
                                <span class="error-text">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="field-group">
                            <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Dirección:</label>
                            <input type="text" wire:model="direccionCliente"
                                class="modal-custom-input @error('direccionCliente') input-error @enderror"
                                placeholder="Calle, Mz, Lt...">
                            @error('direccionCliente')
                                <span class="error-text">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    {{-- REPARTIDOR --}}
                    <div class="field-group" x-show="modalType === 'delivery'" x-transition>
                        <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Repartidor:</label>
                        <select wire:model="repartidorId"
                            class="modal-custom-input @error('repartidorId') input-error @enderror">
                            <option value="">-- Seleccionar --</option>
                            @foreach ($repartidores as $repa)
                                <option value="{{ $repa->id }}">{{ $repa->name }}</option>
                            @endforeach
                        </select>
                        @error('repartidorId')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="modal-custom-footer">
                    <button @click="closeModals()" class="btn-modal btn-cancel"
                        wire:loading.attr="disabled">Cancelar</button>
                    <button wire:click="prepararClienteYRedirigir(modalType)" class="btn-modal btn-confirm-order"
                        :style="modalType === 'delivery' ? 'background:#2563eb' : ''" wire:loading.attr="disabled">

                        <span wire:loading.remove wire:target="prepararClienteYRedirigir">Ordenar →</span>

                        <div wire:loading wire:target="prepararClienteYRedirigir"
                            class="flex items-center justify-center gap-2">
                            <x-filament::loading-indicator class="h-4 w-4" />
                            <span>Redirigiendo...</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>


        {{-- MODAL DETALLES DEL PRODUCTO --}}
        {{-- MODAL DETALLES DEL PRODUCTO (DISEÑO RENOVADO) --}}
        <div x-data="{
            open: false,
            orderCode: '',
            init() {
                Livewire.on('close-detail-modal', () => { this.open = false; });
            }
        }"
            @open-detail-modal.window="
        open = true; 
        orderCode = $event.detail.code; 
        $wire.cargarDetallesOrden($event.detail.id);
    "
            x-show="open" x-cloak class="modal-custom-overlay" x-transition.opacity.duration.200ms
            style="z-index: 9999;">

            <div class="modal-custom-box" @click.outside="open = false; $wire.limpiarDetalles()">

                {{-- HEADER --}}
                <div class="dm-header">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: #eff6ff; padding: 8px; border-radius: 8px;">
                            <x-heroicon-o-receipt-percent class="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <span class="dm-label">Orden de Venta</span>
                            <h3 class="dm-title">#<span x-text="orderCode"></span></h3>
                        </div>
                    </div>
                    <button @click="open = false; $wire.limpiarDetalles()" class="dm-close">&times;</button>
                </div>

                <div class="dm-body">
                    {{-- SKELETON LOADING --}}
                    <div wire:loading wire:target="cargarDetallesOrden" class="w-full space-y-4">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-4 py-1">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="space-y-2">
                                    <div class="h-4 bg-gray-200 rounded"></div>
                                    <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                                </div>
                            </div>
                        </div>
                        <div class="animate-pulse space-y-2 mt-4">
                            <div class="h-10 bg-gray-100 rounded w-full"></div>
                            <div class="h-10 bg-gray-100 rounded w-full"></div>
                        </div>
                    </div>

                    {{-- CONTENIDO REAL --}}
                    <div wire:loading.remove wire:target="cargarDetallesOrden">
                        @if ($ordenParaDetalles)

                            {{-- Tarjetas de Información (Cliente / Envío) --}}
                            <div class="dm-grid-info">
                                {{-- Card Cliente --}}
                                <div class="dm-card-info">
                                    <span class="dm-label">Cliente</span>
                                    <div class="dm-value">
                                        <x-heroicon-m-user class="w-4 h-4 text-gray-400" />
                                        {{ $ordenParaDetalles->nombre_cliente }}
                                    </div>
                                    <div class="dm-value" style="margin-top: 5px; font-size: 13px; font-weight: 400;">
                                        <x-heroicon-m-calendar class="w-4 h-4 text-gray-400" />
                                        {{ $ordenParaDetalles->created_at->format('d/m/Y h:i A') }}
                                    </div>
                                </div>

                                {{-- Card Entrega --}}
                                @if ($ordenParaDetalles->canal === 'delivery')
                                    <div class="dm-card-info">
                                        <span class="dm-label">Detalles de Entrega</span>
                                        @if ($ordenParaDetalles->direccion)
                                            <div class="dm-value">
                                                <x-heroicon-m-map-pin class="w-4 h-4 text-red-500" />
                                                <span class="truncate">{{ $ordenParaDetalles->direccion }}</span>
                                            </div>
                                        @endif
                                        @if ($ordenParaDetalles->nombre_delivery)
                                            <div class="dm-value" style="margin-top: 5px;">
                                                <x-heroicon-m-truck class="w-4 h-4 text-blue-500" />
                                                <span>Rep: {{ $ordenParaDetalles->nombre_delivery }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="dm-card-info"
                                        style="display: flex; align-items: center; justify-content: center; background: #fff;">
                                        <span
                                            style="font-weight: bold; color: #10b981; display: flex; align-items: center; gap: 5px;">
                                            <x-heroicon-o-shopping-bag class="w-5 h-5" /> Para Llevar
                                        </span>
                                    </div>
                                @endif
                            </div>

                            {{-- Tabla de Productos --}}
                            <div>
                                <span class="dm-label">Productos</span>
                                <table class="dm-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px; text-align: center;">Cant</th>
                                            <th>Descripción</th>
                                            <th style="text-align: right;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($ordenParaDetalles->details as $det)
                                            <tr>
                                                <td style="text-align: center;">
                                                    <div style="display: flex; justify-content: center;">
                                                        <span class="dm-qty-badge">{{ $det->cantidad }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 600;">{{ $det->product_name }}</div>
                                                    @if ($det->notes)
                                                        <div class="dm-note">
                                                            <x-heroicon-s-pencil class="w-3 h-3 inline" />
                                                            {{ $det->notes }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="dm-price">
                                                    S/ {{ number_format($det->subTotal, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                        @endif
                    </div>
                </div>

                {{-- FOOTER / TOTAL --}}
                <div class="dm-footer">
                    <div>
                        @if ($ordenParaDetalles)
                            <div style="font-size: 11px; color: #94a3b8;">
                                Atendido por: {{ $ordenParaDetalles->user->name ?? 'Sistema' }}
                            </div>
                        @endif
                    </div>
                    <div style="text-align: right;">
                        <span class="dm-total-label">TOTAL A PAGAR</span>
                        <div class="dm-total-amount">
                            S/ {{ $ordenParaDetalles ? number_format($ordenParaDetalles->total, 2) : '0.00' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- LÓGICA DE TICKET --}}
        @php
            $jobId = session('print_job_id');
            $areasCollection = collect();
            $datosCache = $jobId ? \Illuminate\Support\Facades\Cache::get($jobId) : null;
            if ($datosCache) {
                $items = array_merge($datosCache['nuevos'] ?? [], $datosCache['cancelados'] ?? []);
                foreach ($items as $item) {
                    $areasCollection->push([
                        'id' => $item['area_id'] ?? 'general',
                        'name' => $item['area_nombre'] ?? 'GENERAL',
                    ]);
                }
            } elseif ($ordenGenerada) {
                foreach ($ordenGenerada->details as $det) {
                    $prod = $det->product->production ?? null;
                    $areasCollection->push(
                        $prod && $prod->status
                            ? ['id' => $prod->id, 'name' => $prod->name]
                            : ['id' => 'general', 'name' => 'GENERAL'],
                    );
                }
            }
            $areasUnicas = $areasCollection->unique('id');
        @endphp

        @if ($mostrarModalComanda && $ordenGenerada)
            <x-modal-ticket :orderId="$ordenGenerada->id" :jobId="$jobId" :areas="$areasUnicas" />
        @endif

        @push('scripts')
            <script>
                window.APP_TENANT = @js($tenant->slug ?? 'default');
            </script>
            <script src="{{ asset('js/mesas.js') }}" defer></script>
        @endpush
</x-filament-panels::page>
