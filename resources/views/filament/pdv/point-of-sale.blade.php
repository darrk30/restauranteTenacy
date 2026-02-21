<x-filament-panels::page>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/pdv_mesas.css') }}">
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

        {{-- 1. BOTONES DE CANALES --}}
        @php
            $counts = $this->getChannelCounts();
        @endphp

        <div class="main-channels">
            <button @click="canalActivo = 'salon'" :class="canalActivo === 'salon' ? 'active' : ''" class="channel-btn">
                🏢 SALÓN
                @if ($counts['salon'] > 0)
                    <span class="badge-count badge-salon">{{ $counts['salon'] }}</span>
                @endif
            </button>

            <button @click="canalActivo = 'llevar'" :class="canalActivo === 'llevar' ? 'active' : ''"
                class="channel-btn">
                🛍️ LLEVAR
                @if ($counts['llevar'] > 0)
                    <span class="badge-count badge-llevar">{{ $counts['llevar'] }}</span>
                @endif
            </button>

            <button @click="canalActivo = 'delivery'" :class="canalActivo === 'delivery' ? 'active' : ''"
                class="channel-btn">
                🛵 DELIVERY
                @if ($counts['delivery'] > 0)
                    <span class="badge-count badge-delivery">{{ $counts['delivery'] }}</span>
                @endif
            </button>
        </div>

        {{-- 2. SECCIÓN SALÓN --}}
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

        {{-- 3. SECCIÓN LLEVAR --}}
        <div x-show="canalActivo === 'llevar'" x-cloak style="padding-top: 15px;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 0 10px 15px;">
                <h2 class="section-title">Órdenes: Llevar</h2>
                <button @click="openModal('llevar')" class="channel-btn active" style="background:#10b981;">+ Nueva
                    Orden</button>
            </div>
            <div class="order-grid"
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                @forelse($ordersLlevar as $order)
                    @include('filament.pdv.partials.ticket-card', ['order' => $order, 'canal' => 'llevar'])
                @empty
                    <div style="grid-column:1/-1; text-align:center; padding:40px; opacity:0.5;">No hay órdenes
                        pendientes.</div>
                @endforelse
            </div>
        </div>

        {{-- 4. SECCIÓN DELIVERY --}}
        <div x-show="canalActivo === 'delivery'" x-cloak style="padding-top: 15px;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 0 10px 15px;">
                <h2 class="section-title">Órdenes: Delivery</h2>
                <button @click="openModal('delivery')" class="channel-btn active" style="background:#2563eb;">+ Nuevo
                    Delivery</button>
            </div>
            <div class="order-grid"
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
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

        {{-- MODAL NUEVA ORDEN (LLEVAR/DELIVERY) --}}
        <div x-show="modalOpen" x-cloak class="modal-custom-overlay" x-transition.opacity>
            <div class="modal-custom-box" @click.outside="closeModals()">
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
                            <x-filament::loading-indicator class="h-4 w-4" /> <span>Redirigiendo...</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        {{-- MODAL INTERNO SALÓN --}}
        <div x-show="$store.modalPdv.open" x-cloak class="modal-overlay" x-transition.opacity.duration.200ms
            @keydown.escape.window="$store.modalPdv.open = false">
            <div class="modal-box-modern" @click.outside="$store.modalPdv.open = false">
                <div class="modal-header">
                    <h3>🍽️ Nuevo Pedido</h3><button @click="$store.modalPdv.open=false">✕</button>
                </div>
                <div class="modal-body"><label>Número de personas:</label><input type="number" min="1"
                        value="1" x-model="$store.modalPdv.personas" class="modal-input-modern" autofocus></div>
                <div class="modal-footer">
                    <button @click="$store.modalPdv.open=false">Cancelar</button>
                    <button @click="$wire.iniciarAtencion($store.modalPdv.mesaId, $store.modalPdv.personas)"
                        wire:loading.attr="disabled"><span wire:loading.remove>Continuar →</span><span
                            wire:loading>Cargando...</span></button>
                </div>
            </div>
        </div>

        {{-- MODAL DETALLES DEL PRODUCTO (DISEÑO RENOVADO) --}}
        <div x-data="{ open: false, orderCode: '', init() { Livewire.on('close-detail-modal', () => { this.open = false; }); } }"
            @open-detail-modal.window="open = true; orderCode = $event.detail.code; $wire.cargarDetallesOrden($event.detail.id);"
            x-show="open" x-cloak class="modal-custom-overlay" x-transition.opacity.duration.200ms
            style="z-index: 9999;">
            <div class="modal-custom-box" @click.outside="open = false; $wire.limpiarDetalles()">
                <div class="dm-header">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: #eff6ff; padding: 8px; border-radius: 8px;">
                            <x-heroicon-o-receipt-percent class="w-6 h-6 text-blue-600" />
                        </div>
                        <div><span class="dm-label">Orden de Venta</span>
                            <h3 class="dm-title">#<span x-text="orderCode"></span></h3>
                        </div>
                    </div>
                    <button @click="open = false; $wire.limpiarDetalles()" class="dm-close">&times;</button>
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
                            <div class="dm-grid-info">
                                <div class="dm-card-info">
                                    <span class="dm-label">Cliente</span>
                                    <div class="dm-value"><x-heroicon-m-user
                                            class="w-4 h-4 text-gray-400" />{{ $ordenParaDetalles->nombre_cliente }}
                                    </div>
                                    <div class="dm-value" style="margin-top: 5px; font-size: 13px; font-weight: 400;">
                                        <x-heroicon-m-calendar
                                            class="w-4 h-4 text-gray-400" />{{ $ordenParaDetalles->created_at->format('d/m/Y h:i A') }}
                                    </div>
                                </div>
                                @if ($ordenParaDetalles->canal === 'delivery')
                                    <div class="dm-card-info">
                                        <span class="dm-label">Detalles de Entrega</span>
                                        @if ($ordenParaDetalles->direccion)
                                            <div class="dm-value"><x-heroicon-m-map-pin
                                                    class="w-4 h-4 text-red-500" /><span
                                                    class="truncate">{{ $ordenParaDetalles->direccion }}</span></div>
                                        @endif
                                        @if ($ordenParaDetalles->nombre_delivery)
                                            <div class="dm-value" style="margin-top: 5px;"><x-heroicon-m-truck
                                                    class="w-4 h-4 text-blue-500" /><span>Rep:
                                                    {{ $ordenParaDetalles->nombre_delivery }}</span></div>
                                        @endif
                                    </div>
                                @else
                                    <div class="dm-card-info"
                                        style="display: flex; align-items: center; justify-content: center; background: #fff;">
                                        <span
                                            style="font-weight: bold; color: #10b981; display: flex; align-items: center; gap: 5px;"><x-heroicon-o-shopping-bag
                                                class="w-5 h-5" /> Para Llevar</span>
                                    </div>
                                @endif
                            </div>
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
                                                    <div style="display: flex; justify-content: center;"><span
                                                            class="dm-qty-badge">{{ $det->cantidad }}</span></div>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 600;">{{ $det->product_name }}</div>
                                                    @if ($det->notes)
                                                        <div class="dm-note"><x-heroicon-s-pencil
                                                                class="w-3 h-3 inline" /> {{ $det->notes }}</div>
                                                    @endif
                                                </td>
                                                <td class="dm-price">S/ {{ number_format($det->subTotal, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="dm-footer">
                    <div>
                        @if ($ordenParaDetalles)
                            <div style="font-size: 11px; color: #94a3b8;">Atendido por:
                                {{ $ordenParaDetalles->user->name ?? 'Sistema' }}</div>
                        @endif
                    </div>
                    <div style="text-align: right;"><span class="dm-total-label">TOTAL A PAGAR</span>
                        <div class="dm-total-amount">S/
                            {{ $ordenParaDetalles ? number_format($ordenParaDetalles->total, 2) : '0.00' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- MENÚ CONTEXTUAL --}}
        <div class="global-context-menu" :class="menuOpen ? 'active' : ''"
            :style="`top: ${menuPos.y}px; left: ${menuPos.x}px`" @click.outside="menuOpen = false"
            @contextmenu.prevent>
            <ul class="global-menu-list">
                <template x-if="activeTable.status !== 'free'">
                    <div>
                        <button class="global-menu-item pay"
                            @click="window.location = '/app/pedidos/' + activeTable.orderId + '/pagar'"><x-heroicon-o-credit-card
                                class="icon" /> <span>Pagar cuenta</span></button>
                    </div>
                </template>
                <template x-if="activeTable.status === 'free'">
                    <button class="global-menu-item"
                        @click="$store.modalPdv.open = true; $store.modalPdv.mesaId = activeTable.id; menuOpen = false"><x-heroicon-o-plus
                            class="icon" /> <span>Iniciar Pedido</span></button>
                </template>
            </ul>
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

    <script>
        window.APP_TENANT = @js($tenant->slug ?? 'default');
    </script>
    {{-- @push('scripts')
        <script src="{{ asset('js/mesas.js') }}" defer></script>
    @endpush --}}
</x-filament-panels::page>
