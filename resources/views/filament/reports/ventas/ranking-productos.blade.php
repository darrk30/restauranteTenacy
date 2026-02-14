<x-filament-panels::page>
    <style>
        :root {
            --bg-card: white;
            --border-color: #e5e7eb;
            --text-main: #111827;
            --text-muted: #6b7280;
        }

        .dark {
            --bg-card: #1f2937;
            --border-color: #374151;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
        }

        /* Contenedor de Filtros Responsivo */
        .custom-filter-card {
            background-color: var(--bg-card);
            padding: 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
        }

        .filters-row {
            display: flex;
            flex-direction: column;
            /* Por defecto vertical (móvil) */
            gap: 1rem;
            width: 100%;
        }

        .form-container {
            flex-grow: 1;
        }

        /* Ajuste de botón para que no se estire en escritorio */
        .btn-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 4px;
        }

        /* Grupos de Ranking */
        .ranking-group {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1rem;
        }

        .ranking-header {
            display: flex;
            flex-direction: column;
            /* Vertical en móvil */
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #fbbf24;
        }

        .ranking-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Tabla Responsiva */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            /* Scroll suave en iOS */
        }

        .ranking-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
            /* Asegura que la tabla no se colapse demasiado */
        }

        .ranking-table th {
            padding: 0.75rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-color);
            text-align: center;
        }

        .ranking-table td {
            padding: 0.85rem 0.5rem;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
            color: var(--text-main);
            font-size: 0.9rem;
        }

        .qty-badge {
            background-color: #fef3c7;
            color: #92400e;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge-abc {
            padding: 0.25rem 0.5rem;
            border-radius: 0.4rem;
            font-size: 0.65rem;
            font-weight: bold;
            white-space: nowrap;
        }

        .clase-a {
            background-color: #dcfce7;
            color: #166534;
        }

        .clase-b {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .clase-c {
            background-color: #f3f4f6;
            color: #374151;
        }

        /* Media Queries para Tablets y Desktop */
        @media (min-width: 768px) {
            .filters-row {
                flex-direction: row;
                align-items: flex-end;
            }

            .ranking-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .ranking-group {
                padding: 1.5rem;
            }

            .ranking-title {
                font-size: 1.2rem;
            }
        }
    </style>

    <div class="custom-filter-card">
        <form wire:submit.prevent="aplicarFiltros" class="filters-row">
            <div class="form-container">
                {{ $this->form }}
            </div>
            <div class="btn-container">
                <x-filament::button type="submit" size="md" class="w-full md:w-auto">
                    Filtrar Resultados
                </x-filament::button>
            </div>
        </form>
    </div>

    @forelse ($this->getRankings() as $ranking)
        <div class="ranking-group">
            <div class="ranking-header">
                <div class="ranking-title">
                    <x-heroicon-o-fire style="width: 1.5rem; height: 1.5rem; color: #f59e0b;" />
                    {{ $ranking['titulo'] }}
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">
                    TOP 10 PRODUCTOS
                </div>
            </div>

            <div class="table-responsive">
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Pos</th>
                            <th style="text-align: left;">Producto</th>
                            <th>Vendidos</th>
                            <th style="text-align: right;">Total S/</th>
                            <th>Análisis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ranking['data'] as $index => $item)
                            @php $clase = $index < 3 ? 'a' : ($index < 7 ? 'b' : 'c'); @endphp
                            <tr>
                                <td style="font-weight: bold; color: var(--text-muted);">{{ $index + 1 }}</td>
                                <td style="text-align: left;">
                                    <div style="font-weight: 600; line-height: 1.2;">{{ $item->product_name }}</div>

                                    @if ($item->variant_id)
                                        <div
                                            style="font-size: 0.75rem; color: #6b7280; margin-top: 2px; font-weight: 500;">
                                            @php
                                                // Intentamos obtener la variante. Si ya usaste eager loading es instantáneo.
                                                $variant = \App\Models\Variant::find($item->variant_id);
                                            @endphp

                                            <span class="text-primary-600 dark:text-primary-400">
                                                {{ $variant ? $variant->fullName : 'Variante #' . $item->variant_id }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="qty-badge">{{ number_format($item->total_cantidad, 0) }}</span>
                                </td>
                                <td style="text-align: right; font-weight: 700;">
                                    {{ number_format($item->total_dinero, 2) }}
                                </td>
                                <td>
                                    <span class="badge-abc clase-{{ $clase }}">
                                        {{ strtoupper($clase) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div
            style="text-align: center; padding: 4rem 1rem; color: var(--text-muted); background: var(--bg-card); border-radius: 0.75rem; border: 1px dashed var(--border-color);">
            <x-heroicon-o-circle-stack style="width: 3rem; height: 3rem; margin: 0 auto 1rem; opacity: 0.5;" />
            <p style="font-weight: 500;">No hay datos de ventas para este periodo.</p>
        </div>
    @endforelse
</x-filament-panels::page>
