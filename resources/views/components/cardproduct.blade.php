@props(['product', 'variantId'])

<div class="detail-card">
    <button type="button" wire:click="cerrarModal" class="btn-cerrar">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            style="width:18px;height:18px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
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
            <div class="flex justify-between items-end w-full">

                {{-- INPUT DE PRECIO EDITABLE --}}
                <div class="price-edit-container">
                    <span class="currency-symbol">S/</span>
                    <input type="number" step="0.01" class="price-input"
                        wire:model.live.debounce.500ms="precioCalculado" min="0">
                </div>

                {{-- BADGE DE STOCK --}}
                @if ($product->control_stock == 1 && $variantId)
                    @php
                        // Nota: Accedemos a la propiedad pública del componente padre
                        $stockModalVisible = $this->stockReservaVariante;
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

        {{-- 3. SELECTOR DE ATRIBUTOS (CON CORRECCIÓN DE NULL) --}}
        {{-- EL ERROR ESTABA AQUÍ: Usamos ?-> para evitar error si attributes es null --}}
        @if ($product->attributes?->count())
            <div class="attributes-wrapper">
                @foreach ($product->attributes as $attribute)
                    @php
                        $rawValues = $attribute->pivot->values;
                        $valores = is_string($rawValues) ? json_decode($rawValues, true) : $rawValues ?? [];
                    @endphp

                    @if (is_array($valores) && count($valores) > 0)
                        <div class="mb-4">
                            <span class="section-label">{{ $attribute->name }}:</span>

                            <div class="variants-grid" wire:loading.class="opacity-50 pointer-events-none cursor-wait"
                                wire:target="seleccionarAtributo">
                                @php
                                    $minExtraInRow = collect($valores)->min('extra') ?? 0;
                                @endphp

                                @foreach ($valores as $valor)
                                    @php
                                        $valId = is_array($valor) ? $valor['id'] : $valor->id;
                                        $valName = is_array($valor) ? $valor['name'] : $valor->name;
                                        $valExtra = is_array($valor) ? $valor['extra'] ?? 0 : $valor->extra ?? 0;

                                        // Acceso seguro al array selectedAttributes
                                        $selectedArr = $this->selectedAttributes ?? [];
                                        $isSelected = ($selectedArr[$attribute->id] ?? null) == $valId;
                                    @endphp

                                    <button type="button"
                                        class="variant-option-btn {{ $isSelected ? 'selected' : '' }}"
                                        wire:click="seleccionarAtributo({{ $attribute->id }}, {{ $valId }})"
                                        wire:loading.attr="disabled">

                                        <span class="variant-name">{{ $valName }}</span>

                                        @if ($valExtra > 0)
                                            <span class="badge-price">
                                                + S/ {{ number_format($valExtra, 2) }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
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

            // Validar Selección (Solo si hay atributos)
            if ($product->attributes?->count() > 0 && is_null($variantId)) {
                $bloquearBoton = true;
                $mensajeBoton = 'SELECCIONA OPCIONES';
            }
            // Validar Stock
            elseif ($product->control_stock == 1) {
                $stockRestante = $this->stockReservaVariante;
                if ($variantId) {
                    $enCarritoBtn = collect($this->carrito)->where('variant_id', $variantId)->sum('quantity');
                    $stockRestante = $this->stockReservaVariante - $enCarritoBtn;
                }
                // Si es promo, usamos el helper visual que ya calculamos en el backend
                if ($product instanceof \App\Models\Promotion) {
                    // El stock se valida al confirmar, aquí visualmente no bloqueamos salvo que sea evidente
                }

                if ($stockRestante <= 0 && $product->venta_sin_stock == 0 && !$bloquearBoton) {
                    $bloquearBoton = true;
                    $mensajeBoton = 'AGOTADO (SIN STOCK)';
                }
            }
        @endphp

        <button type="button" wire:click="confirmarAgregado" wire:loading.attr="disabled" {{-- Se bloquea si la lógica lo dice O si está cargando --}}
            @if ($bloquearBoton) disabled @endif
            class="btn-confirm {{ $bloquearBoton ? 'btn-disabled' : '' }}" wire:loading.class="btn-disabled"
            wire:target="confirmarAgregado">
            {{-- Icono Spinner --}}
            <svg wire:loading wire:target="confirmarAgregado" class="animate-spin h-5 w-5 text-white"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"
                    fill="none"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>

            {{-- Texto del Botón --}}
            <span wire:loading.remove wire:target="confirmarAgregado">
                {{ $mensajeBoton }}
            </span>

            <span wire:loading wire:target="confirmarAgregado">
                AGREGANDO...
            </span>

            {{-- Precio (solo si no está bloqueado por lógica) --}}
            @if (!$bloquearBoton)
                <span wire:loading.remove wire:target="confirmarAgregado" class="ml-2 text-sm opacity-80">
                    S/ {{ number_format((float) $this->precioCalculado, 2) }}
                </span>
            @endif
        </button>
    </div>
</div>