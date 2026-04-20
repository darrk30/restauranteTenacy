<x-filament-panels::page>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/kardex-page-filter.css') }}">
    @endpush
    @php
        $filters = $this->getAppliedFilters();
        $prodId = $filters['producto_variante']['product_id'] ?? null;
        $varId = $filters['producto_variante']['variant_id'] ?? null;
        $fechaDesde = $filters['fecha']['desde'] ?? null;
        $fechaHasta = $filters['fecha']['hasta'] ?? null;
    @endphp

    <div class="kdx-wrap">
        @if ($prodId)
            <div class="kdx-box">

                <div class="kdx-header">
                    <div class="kdx-header-left">
                        <div class="kdx-icon-wrap">
                            <x-heroicon-o-funnel class="kdx-icon-funnel" />
                        </div>
                        <h3 class="kdx-header-title">Resumen de filtros</h3>
                    </div>
                    <span class="kdx-badge">Kardex activo</span>
                </div>

                <div class="kdx-grid">

                    {{-- Producto --}}
                    <div class="kdx-item">
                        <span class="kdx-item-label">PRODUCTO</span>
                        <div class="kdx-item-row">
                            <x-heroicon-s-cube class="kdx-icon-blue" />
                            <span class="kdx-item-value">
                                {{ \App\Models\Product::find($prodId)?->name ?? 'No encontrado' }}
                            </span>
                        </div>
                    </div>

                    {{-- Variante --}}
                    @if ($varId)
                        <div class="kdx-item">
                            <span class="kdx-item-label">VARIANTE / ALMACÉN</span>
                            <div class="kdx-item-row">
                                <x-heroicon-s-tag class="kdx-icon-amber" />
                                <span class="kdx-item-value">
                                    {{ \App\Models\Variant::find($varId)?->full_name ?? 'Estándar' }}
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Fechas --}}
                    <div class="kdx-item">
                        <span class="kdx-item-label">RANGO DE FECHAS</span>
                        <div class="kdx-item-row">
                            <x-heroicon-o-calendar class="kdx-icon-muted" />
                            <span class="kdx-item-value-sm">
                                @if ($fechaDesde)
                                    {{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }}
                                    <span class="kdx-date-sep">—</span>
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
            <div class="kdx-empty">
                <x-heroicon-o-magnifying-glass class="kdx-empty-icon" />
                <h3 class="kdx-empty-title">Selecciona un producto</h3>
                <p class="kdx-empty-desc">
                    Usa los filtros superiores para cargar el historial de movimientos de inventario.
                </p>
            </div>
        @endif

        <div class="kdx-table">
            {{ $this->table }}
        </div>
    </div>

</x-filament-panels::page>
