<x-filament-panels::page>
    @php
        $filters = $this->getAppliedFilters();
        $prodId = $filters['producto_variante']['product_id'] ?? null;
        $varId = $filters['producto_variante']['variant_id'] ?? null;
        $fechaDesde = $filters['fecha']['desde'] ?? null;
        $fechaHasta = $filters['fecha']['hasta'] ?? null;
    @endphp

    <div style="font-family: 'Inter', system-ui, sans-serif; color: #1f2937;">
        @if ($prodId)
            {{-- Contenedor Principal --}}
            <div
                style="background: #ffffff; border: 1px solid #f3f4f6; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">

                {{-- Encabezado Sutil --}}
                <div
                    style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #f9fafb; padding-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="background: #e0f2fe; padding: 6px; border-radius: 8px;">
                            <x-heroicon-o-funnel style="width: 18px; height: 18px; color: #0284c7;" />
                        </div>
                        <h3
                            style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.025em; color: #6b7280; margin: 0;">
                            Resumen de Filtros</h3>
                    </div>
                    <span
                        style="font-size: 11px; background: #f3f4f6; color: #6b7280; padding: 4px 10px; border-radius: 20px; font-weight: 500;">Kardex
                        Activo</span>
                </div>

                {{-- Grid de Datos --}}
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">

                    {{-- Producto --}}
                    <div style="padding: 12px; border-radius: 10px; background: #fcfcfc; border: 1px solid #f3f4f6;">
                        <span
                            style="display: block; font-size: 11px; color: #9ca3af; font-weight: 600; margin-bottom: 6px;">PRODUCTO</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <x-heroicon-s-cube style="width: 16px; height: 16px; color: #0284c7;" />
                            <span style="font-size: 14px; font-weight: 600; color: #111827;">
                                {{ \App\Models\Product::find($prodId)?->name ?? 'No encontrado' }}
                            </span>
                        </div>
                    </div>

                    {{-- Variante --}}
                    @if ($varId)
                        <div
                            style="padding: 12px; border-radius: 10px; background: #fcfcfc; border: 1px solid #f3f4f6;">
                            <span
                                style="display: block; font-size: 11px; color: #9ca3af; font-weight: 600; margin-bottom: 6px;">VARIANTE
                                / ALMACÉN</span>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <x-heroicon-s-tag style="width: 16px; height: 16px; color: #d97706;" />
                                <span style="font-size: 14px; font-weight: 600; color: #111827;">
                                    {{ \App\Models\Variant::find($varId)?->full_name ?? 'Estándar' }}
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Fecha --}}
                    <div style="padding: 12px; border-radius: 10px; background: #fcfcfc; border: 1px solid #f3f4f6;">
                        <span
                            style="display: block; font-size: 11px; color: #9ca3af; font-weight: 600; margin-bottom: 6px;">RANGO
                            DE FECHAS</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <x-heroicon-o-calendar style="width: 16px; height: 16px; color: #4b5563;" />
                            <span style="font-size: 13px; font-weight: 500; color: #111827;">
                                @if ($fechaDesde)
                                    {{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }}
                                    <span style="color: #d1d5db; margin: 0 4px;">—</span>
                                    {{ $fechaHasta ? \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') : 'Hoy' }}
                                @else
                                    Todo el historial
                                @endif
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        @else
            {{-- Estado Vacío Elegante --}}
            <div
                style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; border: 1px solid #f3f4f6; border-radius: 20px; background-color: #ffffff; text-align: center; margin-bottom: 24px;">

                {{-- Icono más minimalista --}}
                <div style="margin-bottom: 16px; opacity: 0.4;">
                    <x-heroicon-o-magnifying-glass style="width: 28px; height: 28px; color: #6b7280;" />
                </div>

                {{-- Texto con tipografía más ligera --}}
                <h3 style="font-size: 15px; font-weight: 500; color: #4b5563; margin: 0 0 6px 0;">
                    Selecciona un producto
                </h3>

                <p style="color: #9ca3af; max-width: 240px; font-size: 12px; line-height: 1.4; margin: 0;">
                    Usa los filtros de búsqueda para visualizar el historial del Kardex.
                </p>
            </div>
        @endif

        {{-- Tabla --}}
        <div style="margin-top: 10px;">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
