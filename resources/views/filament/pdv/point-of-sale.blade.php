<x-filament-panels::page>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/pdv_mesas.css') }}">
    @endpush

    {{-- LÓGICA PRINCIPAL DE MESAS (ALPES.JS) --}}
    <div x-data="mesasLogic" class="w-full h-full" @scroll.window="menuOpen = false">
        
        {{-- PESTAÑAS DE PISOS --}}
        <div x-data="{ tab: 1 }" style="width:100%;">
            <div class="summary-row">
                <div class="summary-badge free-bg">Libres</div>
                <div class="summary-badge occ-bg">Ocupadas</div>
                <div class="summary-badge pay-bg">Pagando</div>
            </div>
            
            <div class="pdv-tabs">
                @foreach ($floors as $i => $floor)
                    <button @click="tab = {{ $i + 1 }}"
                        :class="tab === {{ $i + 1 }} ? 'pdv-tab active' : 'pdv-tab'">
                        {{ $floor->name }}
                    </button>
                @endforeach
            </div>

            {{-- GRILLA DE MESAS --}}
            @foreach ($floors as $i => $floor)
                <div x-show="tab === {{ $i + 1 }}" x-transition.opacity.duration.200ms>
                    <div class="pdv-grid">
                        @foreach ($floor->tables as $table)
                            @php
                                $raw = strtolower($table->estado_mesa ?? ($table->status ?? 'libre'));
                                $key = ['ocupada' => 'occupied', 'pagando' => 'paying', 'libre' => 'free'][$raw] ?? 'free';
                                // Preparamos datos JSON para JS
                                $jsData = "{ id: {$table->id}, orderId: " . ($table->order_id ?? 'null') . ", status: '$key' }";
                            @endphp

                            <div class="pdv-card pdv-{{ $key }}" 
                                 @click="handleCardClick({{ $jsData }})"
                                 @contextmenu.prevent.stop="openMenu($event.clientX, $event.clientY, {{ $jsData }})"
                                 @touchstart="startPress($event, {{ $jsData }})" 
                                 @touchend="cancelPress()"
                                 @touchmove="cancelPress()">
                                
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

        {{-- MENÚ CONTEXTUAL (CLICK DERECHO / LONG PRESS) --}}
        <div class="global-context-menu" :class="menuOpen ? 'active' : ''"
            :style="`top: ${menuPos.y}px; left: ${menuPos.x}px`" @click.outside="menuOpen = false" @contextmenu.prevent>

            <ul class="global-menu-list">
                {{-- MESA OCUPADA --}}
                <template x-if="activeTable.status !== 'free'">
                    <div>
                        <button class="global-menu-item pay"
                            @click="window.location = '/app/' + window.APP_TENANT + '/pagar/' + activeTable.orderId">
                            <x-heroicon-o-credit-card class="icon" /> <span>Pagar cuenta</span>
                        </button>
                        <div style="height:1px; background:#f3f4f6; margin:4px 0;"></div>
                        <button class="global-menu-item cancel"
                            @click="if(confirm('¿Seguro que deseas cancelar este pedido?')) window.location = '/app/' + window.APP_TENANT + '/cancelar/' + activeTable.orderId">
                            <x-heroicon-o-x-circle class="icon" /> <span>Cancelar Pedido</span>
                        </button>
                    </div>
                </template>

                {{-- MESA LIBRE --}}
                <template x-if="activeTable.status === 'free'">
                    <div>
                        <div class="global-menu-item" style="opacity:0.5; cursor:default;">
                            <x-heroicon-o-check-circle class="icon" /> <span>Mesa disponible</span>
                        </div>
                        <div style="height:1px; background:#f3f4f6; margin:4px 0;"></div>
                        <button class="global-menu-item"
                            @click="$store.modalPdv.open = true; $store.modalPdv.mesaId = activeTable.id; menuOpen = false">
                            <x-heroicon-o-plus class="icon" /> <span>Iniciar Pedido</span>
                        </button>
                    </div>
                </template>
            </ul>
        </div>

        {{-- SPINNER VISUAL PARA LONG PRESS EN MÓVIL --}}
        <div x-show="pressing" x-cloak class="press-spinner" :style="`top: ${touchPos.y}px; left: ${touchPos.x}px`">
            <svg viewBox="0 0 36 36">
                <path class="spinner-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                <path class="spinner-path" :class="pressing ? 'animate-spinner' : ''"
                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            </svg>
        </div>

    </div>

    {{-- MODAL INTERNO: SELECCIÓN DE PERSONAS PARA NUEVO PEDIDO --}}
    <div x-show="$store.modalPdv.open" x-cloak class="modal-overlay" x-transition.opacity.duration.200ms
        @keydown.escape.window="$store.modalPdv.open = false">

        <div class="modal-box-modern" @click.outside="$store.modalPdv.open = false">
            <div class="modal-header">
                <h3>🍽️ Nuevo Pedido</h3>
                <button @click="$store.modalPdv.open=false">✕</button>
            </div>
            <div class="modal-body">
                <label>Número de personas:</label>
                <input type="number" min="1" value="1" inputmode="numeric" pattern="[0-9]*"
                    x-model="$store.modalPdv.personas" class="modal-input-modern" autofocus>
            </div>
            <div class="modal-footer">
                <button @click="$store.modalPdv.open=false">Cancelar</button>
                <button @click="$wire.iniciarAtencion($store.modalPdv.mesaId, $store.modalPdv.personas)"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove>Continuar →</span>
                    <span wire:loading>Cargando...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL DE TICKET: SE ACTIVA SI SE REDIRIGIÓ TRAS ANULAR O CERRAR MESA --}}
    {{-- ========================================================================= --}}
    
    @php
        $jobId = session('print_job_id'); 
        $areasCollection = collect();

        // 1. Intentamos obtener datos del Cache (Para anulaciones recientes)
        $datosCache = $jobId ? \Illuminate\Support\Facades\Cache::get($jobId) : null;

        if ($datosCache) {
            $items = array_merge($datosCache['nuevos'] ?? [], $datosCache['cancelados'] ?? []);
            foreach ($items as $item) {
                $areasCollection->push([
                    'id' => $item['area_id'] ?? 'general',
                    'name' => $item['area_nombre'] ?? 'GENERAL'
                ]);
            }
        } 
        // 2. Fallback a DB (Por si se recarga o es reimpresión)
        elseif ($ordenGenerada) {
            foreach ($ordenGenerada->details as $det) {
                $prod = $det->product->production ?? null;
                // Usamos la misma lógica flexible que en OrdenMesa
                if ($prod && $prod->status) {
                    $areasCollection->push(['id' => $prod->id, 'name' => $prod->name]);
                } else {
                    $areasCollection->push(['id' => 'general', 'name' => 'GENERAL']);
                }
            }
        }

        $areasUnicas = $areasCollection->unique('id');
    @endphp

    {{-- Si hay orden y bandera activa, mostramos el componente --}}
    @if ($mostrarModalComanda && $ordenGenerada)
        <x-modal-ticket 
            :orderId="$ordenGenerada->id" 
            :jobId="$jobId" 
            :areas="$areasUnicas" 
        />
    @endif

    @push('scripts')
        <script>
            window.APP_TENANT = @js($tenant->slug ?? ($tenant->id ?? 'default'));
        </script>
        <script src="{{ asset('js/mesas.js') }}" defer></script>
    @endpush

</x-filament-panels::page>