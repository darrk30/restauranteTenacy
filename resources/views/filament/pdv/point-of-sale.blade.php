<x-filament-panels::page>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/pdv_mesas.css') }}">
    @endpush

    <div x-data="{
        ...mesasLogic(),
        canalActivo: '{{ auth()->user()->can('ver_salon_rest') ? 'salon' : (auth()->user()->can('ver_llevar_rest') ? 'llevar' : (auth()->user()->can('ver_delivery_rest') ? 'delivery' : '')) }}',modalOpen: false,
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

        {{-- 1. BOTONES DE CANALES --}}
        @php
            $counts = $this->getChannelCounts();
        @endphp

        <div class="main-channels">
            @can('ver_salon_rest')
                <button @click="canalActivo = 'salon'" :class="canalActivo === 'salon' ? 'active' : ''" class="channel-btn">
                    🏢 SALÓN
                    @if ($counts['salon'] > 0)
                        <span class="badge-count badge-salon">{{ $counts['salon'] }}</span>
                    @endif
                </button>
            @endcan
            @can('ver_llevar_rest')
                <button @click="canalActivo = 'llevar'" :class="canalActivo === 'llevar' ? 'active' : ''" class="channel-btn">
                    🛍️ LLEVAR
                    @if ($counts['llevar'] > 0)
                        <span class="badge-count badge-llevar">{{ $counts['llevar'] }}</span>
                    @endif
                </button>
            @endcan
            @can('ver_delivery_rest')
                <button @click="canalActivo = 'delivery'" :class="canalActivo === 'delivery' ? 'active' : ''" class="channel-btn">
                    🛵 DELIVERY
                    @if ($counts['delivery'] > 0)
                        <span class="badge-count badge-delivery">{{ $counts['delivery'] }}</span>
                    @endif
                </button>
            @endcan
        </div>

        {{-- 2. SECCIÓN SALÓN --}}
        @can('ver_salon_rest')
            <div x-show="canalActivo === 'salon'" x-transition:enter.duration.200ms>
                <div x-data="{ tab: 1 }" style="width:100%;">
                    @php
                        $stats = $this->getTableStats();
                    @endphp

                    <div class="summary-row">
                        <div class="summary-badge free-bg">
                            <span>Libres</span>
                            <span class="count-pill">{{ $stats['libres'] }}</span>
                        </div>
                        <div class="summary-badge occ-bg">
                            <span>Ocupadas</span>
                            <span class="count-pill">{{ $stats['ocupadas'] }}</span>
                        </div>
                        <div class="summary-badge pay-bg">
                            <span>Pagando</span>
                            <span class="count-pill">{{ $stats['pagando'] }}</span>
                        </div>
                    </div>
                    <div class="pdv-tabs">
                        @foreach ($floors as $i => $floor)
                            <button @click="tab = {{ $i + 1 }}" :class="tab === {{ $i + 1 }} ? 'pdv-tab active' : 'pdv-tab'">{{ $floor->name }}</button>
                        @endforeach
                    </div>
                    @foreach ($floors as $i => $floor)
                        <div x-show="tab === {{ $i + 1 }}" x-transition.opacity.duration.200ms>
                            <div class="pdv-grid">
                                @foreach ($floor->tables as $table)
                                    @php
                                        $raw = strtolower($table->estado_mesa ?? ($table->status ?? 'libre'));
                                        $key = ['ocupada' => 'occupied', 'pagando' => 'paying', 'libre' => 'free'][$raw] ?? 'free';
                                        $jsData = "{ id: {$table->id}, orderId: " . ($table->order_id ?? 'null') . ", status: '$key' }";
                                    @endphp
                                    <div class="pdv-card pdv-{{ $key }}" @click="handleCardClick({{ $jsData }})" @contextmenu.prevent.stop="openMenu($event.clientX, $event.clientY, {{ $jsData }})">
                                        <div class="badge">
                                            {{ ['free' => 'Libre', 'occupied' => 'Ocupada', 'paying' => 'Pagando'][$key] }}
                                            @if($table->order && $table->order->web)
                                                <span style="background: #ef4444; color: white; font-size: 9px; padding: 2px 4px; border-radius: 4px; margin-left: 5px;">WEB</span>
                                            @endif
                                        </div>
                                        <img class="pdv-icon" src="{{ asset('img/' . ($key === 'free' ? 'mesalibre.png' : 'mesaocupada.png')) }}">
                                        <div class="mesa-name">{{ $table->name }}</div>
                                        <div class="people">👥 {{ $table->asientos }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endcan
        {{-- 3. SECCIÓN LLEVAR (ORDENADO POR ANTIGÜEDAD) --}}
        @can('ver_llevar_rest')
            <div x-show="canalActivo === 'llevar'" x-cloak style="padding-top: 15px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding: 0 10px 15px;">
                    <h2 class="section-title">Órdenes: Llevar</h2>
                    @can('crear_orden_llevar_rest')
                        <button @click="openModal('llevar')" class="channel-btn active" style="background:#10b981;">+ Nueva Orden</button>
                    @endcan
                    </div>
                <div class="order-grid grid grid-cols-1 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
                    @forelse($ordersLlevar->sortBy('created_at') as $order)
                        @include('filament.pdv.partials.ticket-card', ['order' => $order, 'canal' => 'llevar'])
                    @empty
                        <div style="grid-column:1/-1; text-align:center; padding:40px; opacity:0.5;">No hay órdenes pendientes.</div>
                    @endforelse
                </div>
            </div>
        @endcan

        {{-- 4. SECCIÓN DELIVERY (ORDENADO POR ANTIGÜEDAD) --}}
        @can('ver_delivery_rest')
            <div x-show="canalActivo === 'delivery'" x-cloak style="padding-top: 15px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding: 0 10px 15px;">
                    <h2 class="section-title">Órdenes: Delivery</h2>
                    @can('crear_orden_delivery_rest')
                        <button @click="openModal('delivery')" class="channel-btn active" style="background:#2563eb;">+ Nuevo Delivery</button>
                    @endcan
                </div>
                <div class="order-grid grid grid-cols-1 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
                    @forelse($ordersDelivery->sortBy('created_at') as $order)
                        @include('filament.pdv.partials.ticket-card', ['order' => $order, 'canal' => 'delivery'])
                    @empty
                        <div style="grid-column:1/-1; text-align:center; padding:40px; opacity:0.5;">No hay envíos pendientes.</div>
                    @endforelse
                </div>
            </div>
        @endcan

        {{-- MODAL NUEVA ORDEN (LLEVAR/DELIVERY) --}}
        @canany(['crear_orden_llevar_rest', 'crear_orden_delivery_rest'])
            <div x-show="modalOpen" x-cloak class="modal-custom-overlay" x-transition.opacity>
                <div class="modal-custom-box" @click.outside="closeModals()">
                    <div wire:loading wire:target="consultarDocumento, prepararClienteYRedirigir" class="subtle-loading-bar"></div>
                    <div class="modal-custom-header">
                        <h3 class="flex items-center gap-2">
                            <span x-text="modalType === 'llevar' ? '🛍️ Orden Llevar' : '🛵 Nuevo Delivery'"></span>
                        </h3>
                        <span @click="closeModals()" style="cursor:pointer; font-size:28px;">&times;</span>
                    </div>
                    <div class="modal-custom-body" wire:loading.class="loading-blur" wire:target="consultarDocumento, prepararClienteYRedirigir">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="field-group">
                                <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Tipo Doc:</label>
                                <select wire:model.live="tipoDoc" class="modal-custom-input">
                                    <option value="DNI">DNI</option>
                                    <option value="RUC">RUC</option>
                                </select>
                            </div>
                            <div class="field-group sm:col-span-2">
                                <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Nro Documento:</label>
                                <div class="input-group-search">
                                    <input type="text" wire:model.defer="numDoc" class="modal-custom-input @error('numDoc') input-error @enderror" placeholder="Buscar nro...">
                                    <button wire:click="consultarDocumento" class="btn-search-doc" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="consultarDocumento">🔍</span>
                                        <x-filament::loading-indicator wire:loading wire:target="consultarDocumento" class="h-4 w-4" />
                                    </button>
                                </div>
                                @error('numDoc') <span class="error-text">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="field-group">
                            <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">
                                {{ $tipoDoc === 'RUC' ? 'Razón Social:' : 'Nombres:' }}
                            </label>
                            <input type="text" wire:model="nombresCliente" class="modal-custom-input @error('nombresCliente') input-error @enderror" placeholder="Ingrese información...">
                            @error('nombresCliente') <span class="error-text">{{ $message }}</span> @enderror
                        </div>
                        @if ($tipoDoc === 'DNI')
                            <div class="field-group" x-transition>
                                <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Apellidos:</label>
                                <input type="text" wire:model="apellidosCliente" class="modal-custom-input @error('apellidosCliente') input-error @enderror" placeholder="Apellido paterno y materno">
                                @error('apellidosCliente') <span class="error-text">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="field-group">
                                <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Teléfono:</label>
                                <input type="tel" wire:model="telefonoCliente" class="modal-custom-input @error('telefonoCliente') input-error @enderror" placeholder="999...">
                                @error('telefonoCliente') <span class="error-text">{{ $message }}</span> @enderror
                            </div>
                            <div class="field-group">
                                <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Dirección:</label>
                                <input type="text" wire:model="direccionCliente" class="modal-custom-input @error('direccionCliente') input-error @enderror" placeholder="Calle, Mz, Lt...">
                                @error('direccionCliente') <span class="error-text">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="field-group" x-show="modalType === 'delivery'" x-transition>
                            <label class="text-xs uppercase tracking-wider text-gray-500 font-bold">Repartidor:</label>
                            <select wire:model="repartidorId" class="modal-custom-input @error('repartidorId') input-error @enderror">
                                <option value="">-- Seleccionar (Opcional) --</option>
                                @foreach (filament()->getTenant()->users as $repa)
                                    <option value="{{ $repa->id }}">{{ $repa->name }}</option>
                                @endforeach
                            </select>
                            @error('repartidorId') <span class="error-text">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="modal-custom-footer">
                        <button @click="closeModals()" class="btn-modal btn-cancel" wire:loading.attr="disabled">Cancelar</button>
                        <button wire:click="prepararClienteYRedirigir(modalType)" class="btn-modal btn-confirm-order" :style="modalType === 'delivery' ? 'background:#2563eb' : ''" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="prepararClienteYRedirigir">Ordenar →</span>
                            <div wire:loading wire:target="prepararClienteYRedirigir" class="flex items-center justify-center gap-2">
                                <x-filament::loading-indicator class="h-4 w-4" /> <span>Redirigiendo...</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        @endcanany

        {{-- MODAL INTERNO SALÓN --}}
        @can('ver_salon_rest')
            <div x-show="$store.modalPdv.open" x-cloak class="modal-overlay" x-transition.opacity.duration.200ms @keydown.escape.window="$store.modalPdv.open = false">
                <div class="modal-box-modern" @click.outside="$store.modalPdv.open = false">
                    <div class="modal-header">
                        <h3>🍽️ Nuevo Pedido</h3><button @click="$store.modalPdv.open=false">✕</button>
                    </div>
                    <div class="modal-body"><label>Número de personas:</label><input type="number" min="1" value="1" x-model="$store.modalPdv.personas" class="modal-input-modern" autofocus></div>
                    <div class="modal-footer">
                        <button @click="$store.modalPdv.open=false">Cancelar</button>
                        <button @click="$wire.iniciarAtencion($store.modalPdv.mesaId, $store.modalPdv.personas)" wire:loading.attr="disabled"><span wire:loading.remove>Continuar →</span><span wire:loading>Cargando...</span></button>
                    </div>
                </div>
            </div>
        @endcan

        {{-- MODAL DETALLES DEL PRODUCTO (ENRIQUECIDO) --}}
        {{-- MODAL DETALLES DEL PRODUCTO (ENRIQUECIDO) --}}
        <div x-data="{ open: false, orderCode: '', init() { Livewire.on('close-detail-modal', () => { this.open = false; }); } }"
            @open-detail-modal.window="open = true; orderCode = $event.detail.code; $wire.cargarDetallesOrden($event.detail.id);"
            x-show="open" x-cloak class="modal-custom-overlay" x-transition.opacity.duration.200ms style="z-index: 9999;">
            <div class="modal-custom-box" @click.outside="open = false; $wire.limpiarDetalles()">
                <div class="dm-header">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: rgba(37, 99, 235, 0.1); padding: 8px; border-radius: 8px;">
                            <x-heroicon-o-receipt-percent class="w-6 h-6" style="color: #3b82f6;" />
                        </div>
                        <div>
                            <span class="dm-label" style="display: flex; align-items: center; gap: 5px;">
                                Orden de Venta
                                @if ($ordenParaDetalles && $ordenParaDetalles->web)
                                    <span style="background: #ef4444; color: white; font-size: 10px; padding: 2px 6px; border-radius: 12px; font-weight: bold;">🌐 WEB</span>
                                @endif
                            </span>
                            <h3 class="dm-title">#<span x-text="orderCode"></span></h3>
                        </div>
                    </div>
                    <button @click="open = false; $wire.limpiarDetalles()" class="dm-close">×</button>
                </div>

                <div class="dm-body">
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
                    </div>

                    <div wire:loading.remove wire:target="cargarDetallesOrden">
                        @if ($ordenParaDetalles)
                            {{-- 🟢 1. CSS PURO PARA GRID 50/50 PERFECTO --}}
                            {{-- 🟢 1. CSS PURO PARA GRID 50/50 ESTRICTO Y RESTRINGIDO --}}
                            <div class="dm-grid-info" style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 15px; align-items: stretch; margin-bottom: 20px; width: 100%;">
                                
                                {{-- TARJETA CLIENTE --}}
                                <div class="dm-card-info" style="display: flex; flex-direction: column; gap: 10px; min-width: 0;">
                                    <span class="dm-label">Cliente</span>
                                    
                                    <div class="dm-value" style="display: flex; align-items: center; gap: 6px; margin: 0;">
                                        <x-heroicon-m-user style="width: 16px; height: 16px; flex-shrink: 0; opacity: 0.6;" />
                                        <span style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $ordenParaDetalles->nombre_cliente ?? 'Anónimo' }}</span>
                                    </div>

                                    @if ($ordenParaDetalles->telefono)
                                        <div class="dm-value" style="margin: 0;">
                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $ordenParaDetalles->telefono) }}" target="_blank" style="color: #10b981; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; font-weight: 600;">
                                                <svg style="width: 14px; height: 14px; flex-shrink: 0;" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.305-.885-.653-1.48-1.459-1.653-1.756-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
                                                <span>{{ $ordenParaDetalles->telefono }}</span>
                                            </a>
                                        </div>
                                    @endif

                                    <div class="dm-value" style="display: flex; align-items: flex-start; gap: 6px; margin: 0;">
                                        <x-heroicon-m-calendar style="width: 16px; height: 16px; flex-shrink: 0; opacity: 0.6; margin-top: 2px;" />
                                        <div style="display: flex; flex-direction: column; line-height: 1.3;">
                                            <span>{{ $ordenParaDetalles->created_at->format('h:i A') }}</span>
                                            <span style="color: #ef4444; font-size: 11px; font-weight: 600;">({{ $ordenParaDetalles->created_at->diffForHumans() }})</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- TARJETA LOGÍSTICA --}}
                                @if ($ordenParaDetalles->canal === 'delivery')
                                    <div class="dm-card-info" style="display: flex; flex-direction: column; min-width: 0;">
                                        <span class="dm-label" style="margin-bottom: 10px;">Detalles de Entrega</span>
                                        
                                        @if ($ordenParaDetalles->direccion)
                                            <div class="dm-value" style="margin-bottom: 15px; width: 100%;">
                                                {{-- 🟢 2. ENLACE CON FLEX Y SPAN ADAPTATIVO PARA SALTO DE LÍNEA --}}
                                                <a href="http://maps.google.com/?q={{ urlencode($ordenParaDetalles->direccion) }}" target="_blank" style="color: inherit; text-decoration: underline; display: flex; align-items: flex-start; gap: 6px; width: 100%; word-break: break-word;">
                                                    <x-heroicon-m-map-pin style="width: 16px; height: 16px; color: #ef4444; flex-shrink: 0; margin-top: 2px;" />
                                                    <span style="line-height: 1.3; opacity: 0.8; flex: 1; min-width: 0; white-space: normal;">{{ $ordenParaDetalles->direccion }}</span>
                                                </a>
                                            </div>
                                        @endif
                                        
                                        @can('asignar_repartidor_rest')
                                            <div style="margin-top: auto; padding-top: 12px; border-top: 1px dashed rgba(156, 163, 175, 0.3);">
                                                <label style="font-size: 10px; text-transform: uppercase; font-weight: bold; opacity: 0.6; margin-bottom: 6px; display: block;">Asignar Repartidor</label>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <x-heroicon-m-truck style="width: 20px; height: 20px; color: #3b82f6; flex-shrink: 0;" />
                                                    <select wire:model.live="repartidorAsignadoRapido" wire:change="asignarRepartidorRapido({{ $ordenParaDetalles->id }})" class="modal-custom-input" style="flex: 1; padding: 6px 8px; font-size: 12px; border-radius: 6px; min-width: 0;">
                                                        <option value="">-- Sin Repartidor --</option>
                                                        @foreach (filament()->getTenant()->users as $repa)
                                                            <option value="{{ $repa->id }}">{{ $repa->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-filament::loading-indicator wire:loading wire:target="asignarRepartidorRapido" style="width: 16px; height: 16px; color: #3b82f6; flex-shrink: 0;" />
                                                </div>
                                            </div>
                                        @endcan
                                    </div>
                                @else
                                    <div class="dm-card-info" style="display: flex; align-items: center; justify-content: center; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.1); min-width: 0;">
                                        <span style="font-weight: bold; color: #10b981; display: flex; align-items: center; gap: 5px; font-size: 16px;">
                                            <x-heroicon-o-shopping-bag class="w-6 h-6" /> Para Llevar
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div style="width: 100%;">
                                <span class="dm-label">Productos</span>
                                <div style="overflow-x: auto; border-radius: 8px; border: 1px solid rgba(156, 163, 175, 0.2);">
                                    <table class="dm-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                                        <thead style="background: rgba(156, 163, 175, 0.1);">
                                            <tr>
                                                <th style="width: 50px; text-align: center; padding: 8px; font-size: 12px; opacity: 0.7;">Cant</th>
                                                <th style="padding: 8px; font-size: 12px; opacity: 0.7;">Descripción</th>
                                                <th style="text-align: right; padding: 8px; font-size: 12px; width: 80px; opacity: 0.7;">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($ordenParaDetalles->details->where('status', '!=', \App\Enums\StatusPedido::Cancelado) as $det)
                                                <tr style="border-top: 1px solid rgba(156, 163, 175, 0.2);">
                                                    <td style="text-align: center; padding: 8px;">
                                                        <span class="dm-qty-badge" style="background: rgba(99, 102, 241, 0.1); padding: 2px 8px; border-radius: 6px; font-weight: bold; font-size: 12px; display: inline-block;">{{ $det->cantidad }}</span>
                                                    </td>
                                                    <td style="padding: 10px 8px; font-size: 14px;">
                                                        <div style="font-weight: 600;">{{ $det->product_name }}</div>
                                                        @if ($det->notes)
                                                            <div class="dm-note" style="font-size: 12px; color: #d97706; margin-top: 4px; display: flex; gap: 4px; align-items: flex-start;">
                                                                <x-heroicon-s-pencil style="width: 12px; height: 12px; flex-shrink: 0; margin-top: 2px;" /> 
                                                                <span style="line-height: 1.2;">{{ $det->notes }}</span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="dm-price" style="text-align: right; padding: 8px; font-weight: bold; font-size: 14px; white-space: nowrap;">S/ {{ number_format($det->subTotal, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if($ordenParaDetalles->notas)
                                    <div style="margin-top: 15px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); padding: 12px; border-radius: 8px;">
                                        <span style="font-size: 11px; font-weight: bold; color: #d97706; text-transform: uppercase;">Notas Generales (Cocina)</span>
                                        <p style="margin: 4px 0 0 0; font-size: 13px; color: #92400e; opacity: 0.9; line-height: 1.4;">
                                            {{ $ordenParaDetalles->notas }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                <div class="dm-footer">
                    <div>
                        @if ($ordenParaDetalles)
                            <div style="font-size: 11px; opacity: 0.6;">Atendido por: {{ $ordenParaDetalles->user->name ?? 'Carta Web' }}</div>
                        @endif
                    </div>
                    <div style="text-align: right;">
                        <span class="dm-total-label">TOTAL A PAGAR</span>
                        <div class="dm-total-amount">S/ {{ $ordenParaDetalles ? number_format($ordenParaDetalles->total, 2) : '0.00' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- MENÚ CONTEXTUAL --}}
        @can('ver_salon_rest')
            <div class="global-context-menu" :class="menuOpen ? 'active' : ''" :style="`top: ${menuPos.y}px; left: ${menuPos.x}px`" @click.outside="menuOpen = false" @contextmenu.prevent>
                <ul class="global-menu-list">
                    <template x-if="activeTable.status !== 'free'">
                        <div>
                            @can('cobrar_pedido_rest')
                                <button class="global-menu-item pay" @click="window.location = '/app/pedidos/' + activeTable.orderId + '/pagar'"><x-heroicon-o-credit-card class="icon" /> <span>Pagar cuenta</span></button>
                            @endcan
                            <button class="global-menu-item" style="color: #6366f1;" @click="$wire.abrirModalCambioMesa(activeTable.id); menuOpen = false"><x-heroicon-o-arrows-right-left class="icon" /> <span>Cambiar de Mesa</span></button>
                        </div>
                    </template>
                    <template x-if="activeTable.status === 'free'">
                        <button class="global-menu-item" @click="$store.modalPdv.open = true; $store.modalPdv.mesaId = activeTable.id; menuOpen = false"><x-heroicon-o-plus class="icon" /> <span>Iniciar Pedido</span></button>
                    </template>
                </ul>
            </div>
        @endcan
    </div>

    {{-- MODAL CAMBIO DE MESA --}}
    @if ($mostrarModalCambioMesa)
        <div class="modal-custom-overlay" style="z-index: 9999;">
            <div class="modal-custom-box" style="max-width: 400px;">
                <div class="modal-custom-header">
                    <h3 class="flex items-center gap-2">
                        <x-heroicon-o-arrows-right-left class="w-6 h-6 text-indigo-500" />
                        <span>Transferir Orden</span>
                    </h3>
                    <button wire:click="cerrarModalCambioMesa" class="dm-close">&times;</button>
                </div>
                <div class="modal-custom-body" style="padding: 20px;">
                    <p style="margin-bottom: 15px; color: #64748b; font-size: 14px;">Seleccione la mesa libre a la cual desea mover la orden actual.</p>
                    <div class="field-group">
                        <label class="text-xs uppercase tracking-wider text-gray-500 font-bold mb-2 block">Mesa Destino:</label>
                        <select wire:model="mesaDestinoId" class="modal-custom-input w-full" style="padding: 10px; font-size: 16px;">
                            <option value="">-- Seleccione una mesa libre --</option>
                            @foreach ($floors as $floor)
                                @php
                                    $mesasLibres = $floor->tables->filter(function ($t) {
                                        return strtolower($t->estado_mesa ?? ($t->status ?? 'libre')) === 'libre';
                                    });
                                @endphp
                                @if ($mesasLibres->count() > 0)
                                    <optgroup label="{{ $floor->name }}">
                                        @foreach ($mesasLibres as $mesa)
                                            <option value="{{ $mesa->id }}">{{ $mesa->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-custom-footer" style="padding: 15px 20px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; justify-content: flex-end;">
                    <button wire:click="cerrarModalCambioMesa" class="btn-modal btn-cancel flex-1" style="background: #f1f5f9; color: #475569;" wire:loading.attr="disabled" wire:target="cerrarModalCambioMesa" wire:loading.class="opacity-50 cursor-not-allowed">
                        <span wire:loading.remove wire:target="cerrarModalCambioMesa">Cancelar</span>
                        <span wire:loading wire:target="cerrarModalCambioMesa" style="color: #9ca3af;">Cancelando...</span>
                    </button>
                    <button wire:click="cambiarMesa" class="btn-modal flex-1" style="background: #6366f1; color: white;" wire:loading.attr="disabled" wire:target="cambiarMesa" wire:loading.class="opacity-75 cursor-not-allowed">
                        <span wire:loading.remove wire:target="cambiarMesa">Confirmar Traslado</span>
                        <span wire:loading wire:target="cambiarMesa">Moviendo...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- LÓGICA DE TICKET DE COMANDA --}}
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

    <script>
        window.APP_TENANT = @js($tenant->slug ?? 'default');
    </script>
</x-filament-panels::page>