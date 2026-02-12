<x-filament-panels::page>
    <style>
        :root {
            --bg-card: white;
            --border-color: #e5e7eb;
            --text-main: #111827;
            --primary-blue: #1e3a8a;
            /* Azul oscuro principal */
            --secondary-blue: #3b82f6;
            --accent-blue: #eff6ff;
        }

        .dark {
            --bg-card: #1f2937;
            --border-color: #374151;
            --text-main: #f9fafb;
            --primary-blue: #3b82f6;
            --accent-blue: #1e293b;
        }

        .report-wrapper {
            display: flex;
            flex-direction: column;
            gap: 20px;
            font-family: sans-serif;
        }

        /* Contenedor de Filtros */
        .filter-section {
            background-color: var(--bg-card);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filter-form {
            display: flex;
            align-items: flex-end;
            gap: 15px;
            width: 100%;
        }

        /* Card de Líder (Azul Oscuro) */
        .top-performer-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            border-radius: 16px;
            padding: 24px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 10px 15px -3px rgba(30, 58, 138, 0.2);
        }

        .trophy-container {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .leader-info h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .leader-info p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        /* Tabla Estilizada */
        .table-container {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .mozo-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .mozo-table th {
            background-color: var(--accent-blue);
            color: var(--primary-blue);
            padding: 12px 15px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
            text-align: center;
        }

        .mozo-table td {
            padding: 16px 15px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .mozo-name-cell {
            text-align: left !important;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .avatar-circle {
            width: 35px;
            height: 35px;
            background-color: var(--primary-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .amount-badge {
            background-color: #dcfce7;
            color: #166534;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            display: inline-block;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            background-color: var(--bg-card);
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            color: #6b7280;
        }

        .download-pdf-btn {
            background: transparent;
            border: 1px solid #fee2e2;
            padding: 6px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
        }

        .download-pdf-btn:hover {
            background: #fee2e2;
            border-color: #fca5a5;
        }

        .dark .download-pdf-btn:hover {
            background: #450a0a;
        }

        /* Animación de spin para el cargando */
        .animate-spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* Responsividad */
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .top-performer-card {
                flex-direction: column;
                text-align: center;
            }

            .mozo-table-wrapper {
                overflow-x: auto;
            }
        }
    </style>
    @section('header-actions')
        @foreach ($this->getCachedHeaderActions() as $action)
            {{ $action }}
        @endforeach
    @endsection
    <div class="report-wrapper">
        <div class="filter-section">
            <form wire:submit.prevent="aplicarFiltros" class="filter-form">
                <div style="flex-grow: 1;">
                    {{ $this->form }}
                </div>
                <div style="margin-bottom: 5px;">
                    <x-filament::button type="submit" icon="heroicon-m-funnel" size="lg"
                        style="background-color: var(--primary-blue)">
                        Filtrar Datos
                    </x-filament::button>
                </div>
            </form>
        </div>

        @php $stats = $this->getStats(); @endphp

        @if ($stats->isNotEmpty())
            <div class="top-performer-card">
                <div class="trophy-container">
                    <x-heroicon-s-star class="w-10 h-10 text-white" />
                </div>
                <div class="leader-info">
                    <p style="text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px;">Mejor Rendimiento</p>
                    <h2>{{ $stats->first()->waiter_name }}</h2>
                    <p>Total: <strong>S/ {{ number_format($stats->first()->total_ventas, 2) }}</strong> en ventas
                        logradas.</p>
                </div>
            </div>

            <div class="table-container">
                <div class="mozo-table-wrapper">
                    <table class="mozo-table">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Colaborador</th>
                                <th>Servicios</th>
                                <th>Ticket Prom.</th>
                                <th>Total</th>
                                <th style="text-align: center;">PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stats as $row)
                                @php
                                    $desde = $this->form->getState()['fecha_desde'] ?? $this->fecha_desde;
                                    $hasta = $this->form->getState()['fecha_hasta'] ?? $this->fecha_hasta;
                                @endphp
                                <tr>
                                    <td class="mozo-name-cell">
                                        {{-- Tu avatar y nombre actual --}}
                                        <a href="{{ \App\Filament\Restaurants\Pages\Reports\DetallesVentasPorMozo::getUrl(['record' => $row->waiter_id, 'desde' => $desde, 'hasta' => $hasta]) }}"
                                            class="text-primary-600 font-bold underline">
                                            {{ $row->waiter_name }}
                                        </a>
                                    </td>
                                    <td>{{ $row->total_pedidos }}</td>
                                    <td>S/ {{ number_format($row->ticket_promedio, 2) }}</td>
                                    <td>S/ {{ number_format($row->total_ventas, 2) }}</td>

                                    <td style="text-align: center;">
                                        <button
                                            wire:click="descargarPdf({{ $row->waiter_id }}, '{{ $desde }}', '{{ $hasta }}')"
                                            wire:loading.attr="disabled" class="download-pdf-btn" type="button">
                                            <x-heroicon-o-document-arrow-down
                                                style="width: 20px; height: 20px; color: #dc2626;" wire:loading.remove
                                                wire:target="descargarPdf({{ $row->waiter_id }}, '{{ $desde }}', '{{ $hasta }}')" />
                                            <svg wire:loading
                                                wire:target="descargarPdf({{ $row->waiter_id }}, '{{ $desde }}', '{{ $hasta }}')"
                                                class="animate-spin h-5 w-5 text-red-600"
                                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="empty-state">
                <x-heroicon-o-user-group style="width: 40px; height: 40px; margin: 0 auto 15px; opacity: 0.4;" />
                <p>No se encontraron registros de mozos para las fechas seleccionadas.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
