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
                style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; border: 1px dashed #e5e7eb; border-radius: 16px; background-color: #fafafa; text-align: center; margin-bottom: 24px;">
                <div
                    style="background: white; padding: 16px; border-radius: 50%; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); margin-bottom: 20px;">
                    <x-heroicon-o-magnifying-glass style="width: 32px; height: 32px; color: #9ca3af;" />
                </div>
                <h3 style="font-size: 16px; font-weight: 600; color: #374151; margin: 0 0 8px 0;">Esperando selección de
                    producto</h3>
                <p style="color: #9ca3af; max-width: 280px; font-size: 13px; line-height: 1.5; margin: 0;">
                    Usa los <b>filtros de la tabla</b> para buscar el producto que deseas analizar.
                </p>
            </div>
        @endif

        {{-- Tabla --}}
        <div style="margin-top: 10px;">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
