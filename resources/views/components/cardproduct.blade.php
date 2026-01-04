@props(['product', 'variantId'])

<div class="detail-card">

    {{-- 1. IMAGEN Y CABECERA --}}
    <div class="detail-image-container">
        @if ($product->image_path)
            <img src="{{ asset('storage/' . $product->image_path) }}" class="detail-img" alt="{{ $product->name }}">
        @else
            <div class="no-image-placeholder">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                    </path>
                </svg>
            </div>
        @endif

        <div class="detail-title-overlay">
            <h2 class="detail-product-name">{{ $product->name }}</h2>
            <div class="flex justify-between items-end">
                <div class="detail-product-price">
                    {{-- Usamos $this para acceder a la propiedad pública del componente Livewire padre --}}
                    Total: S/ {{ number_format($this->precioCalculado, 2) }}
                </div>

                {{-- BADGE DE STOCK EN EL MODAL --}}
                @if ($product->control_stock == 1 && $variantId)
                    @php
                        // Calcular stock visible restando lo que hay en el carrito
                        $stockBase = $this->stockReservaVariante;
                        $enCarrito = collect($this->carrito)->where('variant_id', $variantId)->sum('quantity');
                        $stockModalVisible = $stockBase - $enCarrito;
                    @endphp

                    <div class="flex flex-col items-end text-xs font-bold text-white drop-shadow-md">
                        <span class="{{ $stockModalVisible > 0 ? 'text-green-400' : 'text-red-400' }}">
                            Disponibles: {{ number_format($stockModalVisible, 0) }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="detail-body">

        {{-- 2. SWITCH DE CORTESÍA --}}
        @if ($product->cortesia == 1)
            <div class="courtesy-row">
                <div class="courtesy-label">
                    <svg class="icon-gift" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7">
                        </path>
                    </svg>
                    <span>Cortesía (Gratis)</span>
                </div>
                <label class="switch">
                    <input type="checkbox" wire:model.live="esCortesia">
                    <span class="slider"></span>
                </label>
            </div>
        @endif

        {{-- 3. SELECTOR DE ATRIBUTOS --}}
        @if ($product->attributes->count() > 0)
            <div class="attributes-wrapper">
                @foreach ($product->attributes as $attribute)
                    @php
                        // === CORRECCIÓN CRÍTICA PARA EL ERROR count() ===
                        // 1. Obtener valor crudo
                        $rawValues = $attribute->pivot->values;

                        // 2. Decodificar JSON a Array de forma segura
                        if (is_string($rawValues)) {
                            $valores = json_decode($rawValues, true); // true = Array asociativo
                        } elseif (is_array($rawValues)) {
                            $valores = $rawValues;
                        } else {
                            $valores = [];
                        }
                    @endphp

                    @if (is_array($valores) && count($valores) > 0)
                        <div class="mb-4">
                            <span class="section-label">{{ $attribute->name }}:</span>

                            <div class="variants-grid">
                                @php
                                    // PASO 1: Calcular el precio MÍNIMO posible para esta fila de atributos
                                    // (Manteniendo las otras selecciones fijas)
                                    $minRowPrice = 999999;

                                    // Pre-calculamos los precios simulados de todas las opciones de esta fila
                                    $simulatedPrices = [];

                                    foreach ($valores as $vInfo) {
                                        $vIdTest = is_array($vInfo) ? $vInfo['id'] : $vInfo->id;

                                        // Simulamos selección
                                        $simulacion = $this->selectedAttributes;
                                        $simulacion[$attribute->id] = $vIdTest;

                                        // Buscamos precio extra de esta simulación
                                        $varSim = $product->variants->first(function ($v) use ($simulacion) {
                                            $vIds = $v->values->pluck('id')->toArray();
                                            return count(array_intersect($simulacion, $vIds)) === count($simulacion);
                                        });

                                        $price = $varSim ? $varSim->extra_price : 0;
                                        $simulatedPrices[$vIdTest] = $price;

                                        if ($price < $minRowPrice) {
                                            $minRowPrice = $price;
                                        }
                                    }
                                @endphp

                                @foreach ($valores as $valor)
                                    @php
                                        $valId = is_array($valor) ? $valor['id'] : $valor->id;
                                        $valName = is_array($valor) ? $valor['name'] : $valor->name;
                                        $isSelected = ($this->selectedAttributes[$attribute->id] ?? null) == $valId;

                                        // PASO 2: Calcular sobrecosto respecto al más barato de la fila
                                        // Esto nos dice "cuánto más caro es esto comparado con la opción base"
                                        $thisOptionPrice = $simulatedPrices[$valId] ?? 0;
                                        $costoExtraVisible = $thisOptionPrice - $minRowPrice;
                                    @endphp

                                    <button type="button"
                                        class="variant-option-btn {{ $isSelected ? 'selected' : '' }}"
                                        wire:click="seleccionarAtributo({{ $attribute->id }}, {{ $valId }})">

                                        <span class="variant-name">{{ $valName }}</span>

                                        {{-- BADGE: Muestra el costo extra relativo a la base --}}
                                        @if ($costoExtraVisible > 0)
                                            <span class="badge-price">
                                                + S/ {{ number_format($costoExtraVisible, 2) }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            {{-- Mensaje para productos simples --}}
            <div class="p-3 bg-blue-50 text-blue-700 rounded-lg text-sm border border-blue-100 flex items-center gap-2">
                <svg style="width:20px;height:20px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Producto estándar (variante única).</span>
            </div>
        @endif

        {{-- 4. TEXTAREA NOTAS --}}
        <div class="notes-section">
            <span class="section-label">Notas de Cocina:</span>
            <textarea rows="3" class="notes-area" placeholder="Ej: Sin cebolla, extra picante..." wire:model="notaPedido"></textarea>
        </div>

    </div>

    {{-- 5. FOOTER CONFIRMAR --}}
    <div class="detail-footer">
        @php
            $bloquearBoton = false;
            $mensajeBoton = 'AGREGAR A LA ORDEN';

            // Validar Selección
            if ($product->attributes->count() > 0 && is_null($variantId)) {
                $bloquearBoton = true;
                $mensajeBoton = 'SELECCIONA OPCIONES';
            }
            // Validar Stock
            elseif ($product->control_stock == 1) {
                // Calcular stock restante considerando carrito
                $stockRestante = $this->stockReservaVariante;
                if ($variantId) {
                    $enCarritoBtn = collect($this->carrito)->where('variant_id', $variantId)->sum('quantity');
                    $stockRestante = $this->stockReservaVariante - $enCarritoBtn;
                }

                if ($stockRestante <= 0 && $product->venta_sin_stock == 0) {
                    $bloquearBoton = true;
                    $mensajeBoton = 'AGOTADO (SIN STOCK)';
                }
            }
        @endphp

        <button class="btn-confirm {{ $bloquearBoton ? 'opacity-50 cursor-not-allowed bg-gray-500' : '' }}"
            wire:click="confirmarAgregado" @if ($bloquearBoton) disabled @endif>
            <span>{{ $mensajeBoton }}</span>
            @if (!$bloquearBoton)
                <span class="ml-2 text-sm opacity-80">S/ {{ number_format($this->precioCalculado, 2) }}</span>
            @endif
        </button>
    </div>
</div>

{{-- ESTILOS CSS --}}
<style>
    /* === COMPONENTE CARD PRODUCT === */
    .detail-card {
        background-color: var(--card-bg, #ffffff);
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        border: 1px solid var(--card-border, #e5e7eb);
        color: var(--pos-text, #1f2937);
    }

    /* Imagen */
    .detail-image-container {
        width: 100%;
        height: 200px;
        background-color: var(--secondary-bg, #f9fafb);
        position: relative;
        overflow: hidden;
    }

    .detail-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .no-image-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        background-color: #f3f4f6;
    }

    .no-image-placeholder svg {
        width: 64px;
        height: 64px;
    }

    .detail-title-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, transparent 100%);
        padding: 30px 20px 15px;
        color: white;
    }

    .detail-product-name {
        font-size: 1.4rem;
        font-weight: 800;
        margin: 0;
        line-height: 1.1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .detail-product-price {
        font-size: 1.1rem;
        font-weight: 600;
        color: #fbbf24;
        margin-top: 4px;
    }

    /* Cuerpo */
    .detail-body {
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .section-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        margin-bottom: 8px;
        display: block;
        letter-spacing: 0.5px;
    }

    /* Switch Cortesía */
    .courtesy-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: var(--secondary-bg, #f9fafb);
        padding: 12px 16px;
        border-radius: 12px;
        border: 1px solid var(--card-border, #e5e7eb);
    }

    .courtesy-label {
        font-weight: 700;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .icon-gift {
        width: 20px;
        height: 20px;
        color: var(--primary-color, #d97706);
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    input:checked+.slider {
        background-color: var(--primary-color, #d97706);
    }

    input:checked+.slider:before {
        transform: translateX(22px);
    }

    /* Variantes */
    .variants-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 10px;
    }

    /* Botón de Opción */
    .variant-option-btn {
        background-color: var(--card-bg, #ffffff);
        border: 1px solid #e5e7eb;
        /* Borde más sutil */
        padding: 10px;
        border-radius: 12px;
        cursor: pointer;
        text-align: center;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        color: var(--pos-text, #1f2937);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        height: 100%;
        /* Para que todos tengan la misma altura en grid */
    }

    /* Hover */
    .variant-option-btn:hover {
        border-color: var(--primary-color, #d97706);
        background-color: #fff7ed;
        /* Naranja muy muy claro */
        transform: translateY(-2px);
        /* Pequeña animación hacia arriba */
    }

    /* Seleccionado */
    .variant-option-btn.selected {
        border: 2px solid var(--primary-color, #d97706);
        /* Borde más grueso */
        background-color: #fff7ed;
        color: var(--primary-color, #d97706);
        font-weight: 700;
    }

    .variant-name {
        font-weight: 700;
        font-size: 0.9rem;
    }

    /* === BADGE DE PRECIO AMIGABLE === */
    /* Badge Normal (No seleccionado) */
    .badge-price {
        font-size: 0.70rem;
        background-color: #f3f4f6;
        /* Gris muy claro */
        color: #374151;
        /* Texto oscuro */
        padding: 2px 6px;
        border-radius: 6px;
        font-weight: 700;
        margin-top: 4px;
        border: 1px solid #e5e7eb;
    }

    /* Badge negativo (Ahorro/Descuento) */
    .badge-price.badge-negative {
        background-color: #10b981;
        /* Verde esmeralda para indicar ahorro */
        color: white;
    }

    /* Badge cuando el botón está SELECCIONADO */
    .variant-option-btn.selected .badge-price {
        background-color: #fff;
        /* Fondo blanco para resaltar sobre el naranja suave */
        color: var(--primary-color, #d97706);
        /* Texto Naranja */
        border-color: var(--primary-color, #d97706);
    }

    /* Textarea */
    .notes-area {
        width: 100%;
        background-color: var(--secondary-bg, #f9fafb);
        border: 1px solid var(--card-border, #e5e7eb);
        border-radius: 12px;
        padding: 12px;
        color: var(--pos-text, #1f2937);
        font-size: 0.95rem;
        resize: none;
        outline: none;
        transition: border-color 0.2s;
        font-family: inherit;
    }

    .notes-area:focus {
        border-color: var(--primary-color, #d97706);
        background-color: var(--card-bg, #ffffff);
    }

    /* Footer */
    .detail-footer {
        padding: 20px;
        border-top: 1px solid var(--card-border, #e5e7eb);
        background-color: var(--card-bg, #ffffff);
    }

    .btn-confirm {
        width: 100%;
        background-color: var(--primary-color, #d97706);
        color: var(--primary-text, #ffffff);
        border: none;
        padding: 16px;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 800;
        cursor: pointer;
        transition: transform 0.1s, opacity 0.2s;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 6px -1px rgba(217, 119, 6, 0.3);
    }

    .btn-confirm:hover {
        opacity: 0.95;
        transform: translateY(-1px);
    }

    .btn-confirm:active {
        transform: translateY(1px);
    }

    /* Dark mode básico manual si no carga variables */
    :is(.dark .detail-card) {
        background-color: #1f2937;
        color: #f3f4f6;
        border-color: #374151;
    }

    :is(.dark .detail-image-container) {
        background-color: #111827;
    }

    :is(.dark .courtesy-row),
    :is(.dark .notes-area) {
        background-color: #111827;
        border-color: #374151;
        color: #f3f4f6;
    }

    :is(.dark .variant-option-btn) {
        background-color: #1f2937;
        color: #f3f4f6;
        border-color: #374151;
    }

    :is(.dark .detail-footer) {
        background-color: #1f2937;
        border-color: #374151;
    }
</style>
