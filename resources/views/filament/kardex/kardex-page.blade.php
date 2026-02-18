<x-filament-panels::page>
    @php
        $filters = $this->getAppliedFilters();
        $prodId = $filters['producto_variante']['product_id'] ?? null;
        $varId = $filters['producto_variante']['variant_id'] ?? null;
        $fechaDesde = $filters['fecha']['desde'] ?? null;
        $fechaHasta = $filters['fecha']['hasta'] ?? null;
    @endphp

    <div style="font-family: sans-serif; color: #374151;">
        @if ($prodId)
            {{-- Contenedor Principal de Filtros --}}
            <div style="background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                
                {{-- Título --}}
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 0.75rem;">
                    <div style="color: #0284c7;">
                        <x-heroicon-o-funnel style="width: 1.5rem; height: 1.5rem;" />
                    </div>
                    <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: #111827;">Filtros Activos del Kardex</h3>
                </div>

                {{-- Grid de Filtros (Simulado con Flexbox) --}}
                <div style="display: flex; flex-wrap: wrap; gap: 1.5rem;">
                    
                    {{-- Bloque Producto --}}
                    <div style="min-width: 200px; flex: 1;">
                        <p style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Producto Seleccionado</p>
                        <div style="display: flex; align-items: center; gap: 0.5rem; background-color: #f0f9ff; border: 1px solid #e0f2fe; padding: 0.5rem 0.75rem; border-radius: 0.5rem;">
                            <x-heroicon-s-cube style="width: 1rem; height: 1rem; color: #0284c7;" />
                            <span style="font-size: 0.875rem; font-weight: 600; color: #0369a1;">
                                {{ \App\Models\Product::find($prodId)?->name }}
                            </span>
                        </div>
                    </div>

                    {{-- Bloque Variante --}}
                    @if($varId)
                    <div style="min-width: 200px; flex: 1;">
                        <p style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Variante / Almacén</p>
                        <div style="display: flex; align-items: center; gap: 0.5rem; background-color: #fffbeb; border: 1px solid #fef3c7; padding: 0.5rem 0.75rem; border-radius: 0.5rem;">
                            <x-heroicon-s-tag style="width: 1rem; height: 1rem; color: #d97706;" />
                            <span style="font-size: 0.875rem; font-weight: 600; color: #92400e;">
                                {{ \App\Models\Variant::find($varId)?->full_name }}
                            </span>
                        </div>
                    </div>
                    @endif

                    {{-- Bloque Periodo --}}
                    @if($fechaDesde)
                    <div style="min-width: 200px; flex: 1;">
                        <p style="font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Periodo de Consulta</p>
                        <div style="display: flex; align-items: center; gap: 0.5rem; background-color: #f9fafb; border: 1px solid #f3f4f6; padding: 0.5rem 0.75rem; border-radius: 0.5rem;">
                            <x-heroicon-o-calendar style="width: 1rem; height: 1rem; color: #4b5563;" />
                            <span style="font-size: 0.875rem; font-weight: 600;">
                                {{ $fechaDesde }} 
                                <span style="color: #9ca3af; margin: 0 0.25rem;">→</span> 
                                {{ $fechaHasta ?? 'Hoy' }}
                            </span>
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        @else
            {{-- Estado Vacío (Dashed Box) --}}
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; border: 2px dashed #d1d5db; border-radius: 1rem; background-color: #f9fafb; text-align: center; margin-bottom: 1.5rem;">
                <div style="color: #9ca3af; margin-bottom: 1rem;">
                    <x-heroicon-o-magnifying-glass-circle style="width: 4rem; height: 4rem;" />
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #4b5563; margin-bottom: 0.5rem;">Consulta de Kardex Valorizado</h3>
                <p style="color: #6b7280; max-width: 320px; font-size: 0.875rem; line-height: 1.25rem; margin: 0;">
                    Por favor, utiliza el botón de <b>filtros</b> arriba a la derecha para seleccionar un producto y visualizar sus movimientos de stock.
                </p>
            </div>
        @endif

        {{-- Tabla de Filament (Esta se renderiza con los estilos propios de Filament) --}}
        <div class="filament-main-table">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>