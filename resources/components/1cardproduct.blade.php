@props(['product', 'variantId'])
<div class="detail-card">
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/cardproduct.css') }}">
    @endpush
    
    {{-- 1. IMAGEN Y CABECERA --}}
    <div class="detail-image-container">
        @if($product->image_path)
            <img src="{{ asset('storage/' . $product->image_path) }}" class="detail-img" alt="{{ $product->name }}">
        @else
            <div class="no-image-placeholder">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
        @endif
        
        <div class="detail-title-overlay">
            <h2 class="detail-product-name">{{ $product->name }}</h2>
            <div class="detail-product-price">
                Base: S/ {{ number_format($product->price, 2) }}
            </div>
        </div>
    </div>

    <div class="detail-body">

        {{-- 2. SWITCH DE CORTESÍA --}}
        <div class="courtesy-row">
            <div class="courtesy-label">
                <svg class="icon-gift" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>
                <span>Cortesía (Gratis)</span>
            </div>
            <label class="switch">
                <input type="checkbox" wire:model.live="esCortesia">
                <span class="slider"></span>
            </label>
        </div>

        {{-- 3. SELECTOR DE VARIANTES --}}
        @if($product->variants->count() > 0)
            <div class="variants-section">
                <span class="section-label">Opciones Disponibles:</span>
                <div class="variants-grid">
                    @foreach($product->variants as $variant)
                        <button 
                            type="button"
                            class="variant-option-btn {{ $variantId == $variant->id ? 'selected' : '' }}"
                            wire:click="$set('variantSeleccionadaId', {{ $variant->id }})"
                        >
                            <span class="variant-name">
                                {{-- Muestra los valores (Ej: Grande, Rojo) --}}
                                @foreach($variant->values as $val) 
                                    {{ $val->name }} 
                                @endforeach
                            </span>

                            @if($variant->extra_price > 0)
                                <span class="extra-price-badge">
                                    +S/ {{ number_format($variant->extra_price, 2) }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 4. TEXTAREA NOTAS --}}
        <div class="notes-section">
            <span class="section-label">Notas de Cocina:</span>
            <textarea 
                rows="3" 
                class="notes-area" 
                placeholder="Ej: Sin cebolla, extra picante..."
                wire:model="notaPedido"
            ></textarea>
        </div>

    </div>

    {{-- 5. BOTÓN CONFIRMAR --}}
    <div class="detail-footer">
        <button class="btn-confirm" wire:click="confirmarAgregado">
            <span>AGREGAR A LA ORDEN</span>
            <svg style="width:24px;height:24px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </button>
    </div>

</div>