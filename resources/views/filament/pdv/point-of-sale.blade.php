<x-filament-panels::page>

    <!-- ======================= ALPINE STORE ======================= -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('modalPdv', {
                open: false,
                mesaId: null,
                personas: 1,
                tenant: '{{ $tenant->slug ?? ($tenant->id ?? 'default') }}',
            });

        });
    </script>

    <div x-data>

        <!-- =======================  ESTILOS  ======================= -->
        <style>
            .summary-row {
                display: flex;
                gap: 1rem;
                margin-bottom: 1rem;
            }

            .summary-badge {
                padding: 6px 12px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: bold;
                color: white;
            }

            .free-bg {
                background: #16a34a;
            }

            .occ-bg {
                background: #dc2626;
            }

            .pay-bg {
                background: #eab308;
            }

            .pdv-tabs {
                display: flex;
                gap: 1rem;
                border-bottom: 1px solid #444;
                padding-bottom: .5rem;
                margin-bottom: 1rem;
            }

            .pdv-tab {
                padding-bottom: .4rem;
                font-weight: 600;
                background: transparent;
                border: 0;
                cursor: pointer;
                color: #bbb;
            }

            .pdv-tab.active {
                color: #fbbf24;
                border-bottom: 2px solid #fbbf24;
            }

            /* GRID DE MESAS */
            .pdv-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 1.2rem;
            }

            @media (max-width: 640px) {
                .pdv-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            /* CARD MESA */
            .pdv-card {
                position: relative;
                border-radius: 18px;
                padding: 1rem;
                text-align: center;
                cursor: pointer;
                min-height: 150px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                display: flex;
                flex-direction: column;
                justify-content: center;
                transition: transform .15s;
            }

            .pdv-card:hover {
                transform: scale(1.04);
            }

            .pdv-occupied {
                background: #ffe0e0;
                border: 1px solid #ffb3b3;
                color: #7f1d1d;
            }

            .pdv-free {
                background: #e6fff5;
                border: 1px solid #bbf7d0;
                color: #064e3b;
            }

            .pdv-paying {
                background: #fff8cc;
                border: 1px solid #fde047;
                color: #7c5800;
            }

            .pdv-icon {
                width: 60px;
                height: 60px;
                margin: 5px auto 2px auto;
                object-fit: contain;
            }

            .badge {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                padding: 4px 0;
                border-radius: 18px 18px 0 0;
                font-size: 12px;
                font-weight: 700;
                text-align: center;
                color: white;
            }

            .pdv-free .badge {
                background: #16a34a;
            }

            .pdv-occupied .badge {
                background: #dc2626;
            }

            .pdv-paying .badge {
                background: #eab308;
            }

            .people {
                position: absolute;
                bottom: 6px;
                left: 8px;
                font-size: 13px;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .timer {
                position: absolute;
                bottom: 6px;
                right: 8px;
                font-size: 13px;
                font-weight: 700;
            }

            /* MODAL */
            .modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                backdrop-filter: blur(4px);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 999;
            }

            .modal-box {
                width: 320px;
                background: #ffffff;
                padding: 25px 20px;
                border-radius: 14px;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
                text-align: center;
            }

            [x-cloak] {
                display: none !important;
            }

            .modal-title {
                font-size: 20px;
                font-weight: 700;
                margin-bottom: 15px;
            }

            .modal-input {
                width: 100%;
                padding: 10px 12px;
                border-radius: 8px;
                border: 1px solid #ccc;
                font-size: 16px;
                margin-bottom: 20px;
            }

            .modal-buttons {
                display: flex;
                gap: 12px;
            }

            .btn-cancel {
                flex: 1;
                background: #dddddd;
                padding: 10px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
            }

            .btn-cancel:hover {
                background: #c7c7c7;
            }

            .btn-accept {
                flex: 1;
                background: #16a34a;
                color: white;
                padding: 10px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
            }

            .btn-accept:hover {
                background: #13833c;
            }

            /* Fondo difuminado */
            .modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.55);
                backdrop-filter: blur(4px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                padding: 20px;
            }

            /* Caja del modal */
            .modal-box-modern {
                width: 350px;
                max-width: 95%;
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(10px);
                border-radius: 18px;
                padding: 0;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
                overflow: hidden;
                animation: fadeUp 0.25s ease-out;
            }

            /* Header */
            .modal-header {
                background: #16a34a;
                color: white;
                padding: 14px 18px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .modal-title-modern {
                font-size: 20px;
                font-weight: 700;
            }

            .modal-close-btn {
                background: transparent;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
            }

            /* Body */
            .modal-body {
                padding: 20px;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .modal-label {
                font-weight: 600;
                font-size: 15px;
                color: #374151;
            }

            .modal-input-modern {
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px solid #ccc;
                font-size: 16px;
                outline: none;
                transition: border 0.2s ease;
            }

            .modal-input-modern:focus {
                border-color: #16a34a;
            }

            /* Footer */
            .modal-footer {
                padding: 14px 20px;
                background: #f9fafb;
                display: flex;
                gap: 12px;
            }

            .modal-btn-cancel {
                flex: 1;
                background: #e5e7eb;
                padding: 10px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s;
            }

            .modal-btn-cancel:hover {
                background: #d1d5db;
            }

            .modal-btn-accept {
                flex: 1;
                background: #16a34a;
                color: white;
                padding: 10px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s;
            }

            .modal-btn-accept:hover {
                background: #13833c;
            }

            .modal-input-modern {
                color: #111 !important;
                background-color: white !important;
            }

            .modal-input-modern::placeholder {
                color: #777 !important;
            }

            /* Botones del input number (Chrome) */
            .modal-input-modern::-webkit-inner-spin-button,
            .modal-input-modern::-webkit-outer-spin-button {
                opacity: 0.7;
                filter: invert(0);
                /* iconos oscuros */
            }

            /* Firefox */
            input[type=number] {
                -moz-appearance: textfield;
            }

            @keyframes fadeUp {
                from {
                    opacity: 0;
                    transform: translateY(15px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>

        <!-- ======================= TIMER SCRIPT ======================= -->
        @push('scripts')
            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    document.querySelectorAll("[data-start]").forEach(el => {
                        let start = parseInt(el.dataset.start);

                        setInterval(() => {
                            let diff = Math.floor((Date.now() / 1000) - start);
                            let h = String(Math.floor(diff / 3600)).padStart(2, '0');
                            let m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
                            let s = String(diff % 60).padStart(2, '0');
                            el.innerText = `${h}:${m}:${s}`;
                        }, 1000);
                    });
                });
            </script>
        @endpush

        <!-- ======================= CONTENIDO ======================= -->
        <div x-data="{ tenant: '{{ $tenant->slug ?? ($tenant->id ?? 'default') }}', tab: 1 }" style="width:100%;">
            {{-- <div x-data="{ tab: 1 }" style="width:100%;"> --}}

            @php
                $totalFree = 0;
                $totalOccupied = 0;
                $totalPaying = 0;
                foreach ($floors as $floor) {
                    foreach ($floor->tables as $t) {
                        $s = strtolower($t->estado_mesa ?? ($t->status ?? 'libre'));
                        if ($s === 'libre') {
                            $totalFree++;
                        } elseif ($s === 'ocupada') {
                            $totalOccupied++;
                        } elseif ($s === 'pagando') {
                            $totalPaying++;
                        }
                    }
                }
            @endphp

            <div class="summary-row">
                <div class="summary-badge free-bg">Libres: {{ $totalFree }}</div>
                <div class="summary-badge occ-bg">Ocupadas: {{ $totalOccupied }}</div>
                <div class="summary-badge pay-bg">Pagando: {{ $totalPaying }}</div>
            </div>

            <div class="pdv-tabs">
                @foreach ($floors as $index => $floor)
                    <button @click="tab = {{ $index + 1 }}"
                        x-bind:class="tab === {{ $index + 1 }} ? 'pdv-tab active' : 'pdv-tab'">
                        {{ $floor->name }}
                    </button>
                @endforeach
            </div>

            @foreach ($floors as $index => $floor)
                <div x-show="tab === {{ $index + 1 }}">

                    <div class="pdv-grid">
                        @foreach ($floor->tables as $table)
                            @php
                                $raw = strtolower($table->estado_mesa ?? ($table->status ?? 'libre'));
                                $key =
                                    ['ocupada' => 'occupied', 'pagando' => 'paying', 'libre' => 'free'][$raw] ?? 'free';

                                $classes = [
                                    'free' => 'pdv-card pdv-free',
                                    'occupied' => 'pdv-card pdv-occupied',
                                    'paying' => 'pdv-card pdv-paying',
                                ];

                                $labels = [
                                    'free' => 'Libre',
                                    'occupied' => 'Ocupada',
                                    'paying' => 'Pagando',
                                ];

                                $icons = [
                                    'free' => 'mesalibre.png',
                                    'occupied' => 'mesaocupada.png',
                                    'paying' => 'mesaocupada.png',
                                ];
                            @endphp

                            <div class="{{ $classes[$key] }}"
                                @click="
                                    $store.modalPdv.open = true;
                                    $store.modalPdv.mesaId = {{ $table->id }};
                                ">

                                <div class="badge">{{ $labels[$key] }}</div>
                                <img class="pdv-icon" src="{{ asset('img/' . $icons[$key]) }}">

                                <div style="font-weight:700; margin-top:4px; font-size:16px;">
                                    {{ $table->name }}
                                </div>

                                <div class="people">
                                    <span>👥</span> {{ $table->asientos }}
                                </div>

                                @if (in_array($key, ['occupied', 'paying']) && $table->ocupada_desde)
                                    <div class="timer" data-start="{{ strtotime($table->ocupada_desde) }}">00:00:00
                                    </div>
                                @endif

                            </div>
                        @endforeach
                    </div>

                </div>
            @endforeach

        </div>
    </div>


    <!-- ======================= MODAL ======================= -->
    <!-- ======================= MODAL ======================= -->
    <div x-show="$store.modalPdv.open" @keydown.escape.window="$store.modalPdv.open = false" x-transition.opacity
        x-cloak class="modal-overlay">

        <div class="modal-box-modern" x-transition.scale.80 x-transition.opacity>

            <!-- Header -->
            <div class="modal-header">
                <h3 class="modal-title-modern">🍽️ Iniciar pedido</h3>
                <button class="modal-close-btn" @click="$store.modalPdv.open = false">✕</button>
            </div>

            <!-- Body -->
            <div class="modal-body">

                <label class="modal-label">Cantidad de personas</label>

                <input type="number" min="1" x-model="$store.modalPdv.personas" class="modal-input-modern">
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button class="modal-btn-cancel" @click="$store.modalPdv.open = false">
                    Cancelar
                </button>

                <button class="modal-btn-accept"
                    @click="window.location = '{{ url('/restaurants') }}/' 
                    + $store.modalPdv.tenant 
                    + '/orden-mesa/' 
                    + $store.modalPdv.mesaId;
                ">
                    Continuar →
                </button>
            </div>

        </div>
    </div>


</x-filament-panels::page>
