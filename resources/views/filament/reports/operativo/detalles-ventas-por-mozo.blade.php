<x-filament-panels::page>
    <style>
        :root {
            --bg-card: #ffffff;
            --bg-table-header: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --row-hover: #f1f5f9;
        }

        /* Soporte para Modo Oscuro de Filament */
        .fi-main-ctn:has(.dark) .report-container,
        .dark .report-container {
            --bg-card: #18181b;
            --bg-table-header: #27272a;
            --text-main: #f4f4f5;
            --text-muted: #a1a1aa;
            --border-color: #3f3f46;
            --row-hover: #27272a;
        }

        .report-container {
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--text-main);
        }

        .header-card {
            background: #1e3a8a;
            /* Color corporativo fijo */
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header-info h2 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
        }

        .header-info p {
            margin: 0.25rem 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .table-wrapper {
            background: var(--bg-card);
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .custom-table thead {
            background: var(--bg-table-header);
            border-bottom: 2px solid var(--border-color);
        }

        .custom-table th {
            padding: 1rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .custom-table tr:hover {
            background-color: var(--row-hover);
        }

        .custom-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .badge-status {
            background: #dcfce7;
            color: #166534;
            padding: 0.3rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-block;
        }

        .dark .badge-status {
            background: #064e3b;
            color: #34d399;
        }

        .text-bold {
            font-weight: 600;
            color: var(--text-main);
        }

        .text-muted {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .price-total {
            font-weight: 800;
            color: #3b82f6;
        }

        .table-footer {
            background: var(--bg-table-header);
            padding: 1.25rem;
            text-align: right;
            border-top: 2px solid var(--border-color);
        }

        /* --- RESPONSIVE DESIGN --- */
        @media (max-width: 768px) {
            .custom-table thead {
                display: none;
            }

            .custom-table tr {
                display: block;
                padding: 1rem;
                border-bottom: 4px solid var(--border-color);
            }

            .custom-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.6rem 0;
                border: none;
                text-align: right;
                font-size: 0.95rem;
            }

            .custom-table td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--text-muted);
                text-transform: uppercase;
                font-size: 0.75rem;
                text-align: left;
                margin-right: 1rem;
            }

            .header-card {
                text-align: center;
                justify-content: center;
            }

            .header-info {
                width: 100%;
            }
        }
    </style>

    <div class="report-container">
        <div class="header-card">
            <div class="header-info">
                <h2>{{ $mozo->name }}</h2>
                <p>Periodo: {{ date('d/m/Y', strtotime($fecha_desde)) }} - {{ date('d/m/Y', strtotime($fecha_hasta)) }}
                </p>
            </div>
            <x-filament::button tag="a"
                href="{{ \App\Filament\Restaurants\Pages\Reports\VentasPorMozo::getUrl() }}" color="gray"
                icon="heroicon-m-arrow-left">
                Volver al Listado
            </x-filament::button>
        </div>

        <div class="table-wrapper">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Cliente / Documento</th>
                        <th>Comprobante / Fecha</th>
                        <th style="text-align: center;">Estado</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ventas as $venta)
                        <tr>
                            <td data-label="Cliente">
                                <div class="text-bold">{{ $venta->nombre_cliente ?? 'PÚBLICO GENERAL' }}</div>
                                <div class="text-muted">{{ $venta->tipo_documento ?? 'DOC' }}:
                                    {{ $venta->numero_documento ?? '-------' }}</div>
                            </td>

                            <td data-label="Comprobante">
                                <div class="text-bold">{{ $venta->tipo_comprobante }}
                                    {{ $venta->serie }}-{{ $venta->correlativo }}</div>
                                <div class="text-muted">
                                    {{ \Carbon\Carbon::parse($venta->fecha_emision)->format('d/m/Y H:i') }}</div>
                            </td>

                            <td data-label="Estado" style="text-align: center;">
                                <span class="badge-status">{{ strtoupper($venta->status) }}</span>
                            </td>

                            <td data-label="Total" style="text-align: right;">
                                <span class="price-total">S/ {{ number_format($venta->total, 2) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 4rem; color: var(--text-muted);">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">📂</div>
                                No se encontraron ventas para este mozo en el rango seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($ventas->count() > 0)
                <div class="table-footer">
                    <span style="color: var(--text-muted); font-weight: 600;">TOTAL ACUMULADO:</span>
                    <span class="price-total" style="font-size: 1.5rem; margin-left: 15px;">
                        S/ {{ number_format($ventas->sum('total'), 2) }}
                    </span>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
