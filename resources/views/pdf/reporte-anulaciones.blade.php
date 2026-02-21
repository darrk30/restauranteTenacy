<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $nombre_reporte }}</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 10px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #dc2626;
            text-transform: uppercase;
        }

        .header h2 {
            margin: 5px 0;
            font-size: 14px;
            color: #374151;
        }

        .filters {
            margin-bottom: 20px;
            background-color: #f9fafb;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            font-size: 8px;
        }

        /* Encabezados más pequeños para que entren más columnas */
        td {
            padding: 4px;
        }

        /* Menos padding para filas */
        th,
        td {
            border: 1px solid #e5e7eb;
            padding: 6px;
            text-align: left;
        }

        th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-danger {
            color: #dc2626;
            font-weight: bold;
        }

        .totals {
            margin-top: 20px;
            width: 45%;
            float: right;
        }

        .totals table th {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge {
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .badge-promo {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-cortesia {
            background-color: #dcfce7;
            color: #166534;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>{{ $restaurant }}</h1>
        <h2>{{ $nombre_reporte }} ({{ $tipo_reporte == 'ordenes' ? 'Órdenes Completas' : 'Detalle de Productos' }})</h2>
        <p>Fecha de Generación: {{ $fecha_exportacion }}</p>
    </div>

    <div class="filters">
        <strong>Filtros aplicados:</strong><br>
        @foreach ($filtros as $key => $value)
            @if ($value)
                <strong>{{ $key }}:</strong> {{ $value }} |
            @endif
        @endforeach
    </div>

    <table>
        <thead>
            @if ($tipo_reporte == 'ordenes')
                {{-- CABECERA PARA ÓRDENES --}}
                <tr>
                    <th>Nro Pedido</th>
                    <th>Fecha / Hora</th>
                    <th>Canal</th>
                    <th>Atendido por</th>
                    <th>Anulado por</th>
                    <th class="text-right">Monto Total</th>
                </tr>
            @else
                {{-- CABECERA PARA PRODUCTOS --}}
                <tr>
                    <th>Orden</th>
                    <th>Fecha</th>
                    <th>Canal</th>
                    <th>Producto</th>
                    <th>Atendido por</th>
                    <th>Anulado por</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-right">Monto</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @forelse($anulaciones as $item)
                <tr>
                    @if ($tipo_reporte == 'ordenes')
                        {{-- FILA PARA ÓRDENES --}}
                        <td>#{{ $item->code }}</td>
                        <td>{{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y h:i A') }}</td>
                        <td style="text-transform: capitalize;">{{ $item->canal }}</td>
                        <td>{{ $item->user->name ?? 'N/A' }}</td>
                        <td>{{ $item->userActualiza->name ?? 'El mismo' }}</td>
                        <td class="text-right text-danger">S/ {{ number_format($item->total, 2) }}</td>
                    @else
                        <td>#{{ $item->order->code }}</td>
                        <td>{{ $item->created_at->format('d/m/H i:A') }}</td>
                        <td style="text-transform: capitalize;">{{ $item->order->canal }}</td>
                        <td>
                            {{ $item->product_name }}
                            @if ($item->cortesia)
                                <small>(Cortesia)</small>
                            @endif
                        </td>
                        <td>{{ $item->user->name ?? 'Sistema' }}</td>
                        <td class="text-danger">{{ $item->userActualiza->name ?? 'N/A' }}</td>
                        <td class="text-center">{{ number_format($item->cantidad, 0) }}</td>
                        <td class="text-right text-danger">S/ {{ number_format($item->subTotal, 2) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $tipo_reporte == 'ordenes' ? 6 : 6 }}" class="text-center">
                        No se encontraron anulaciones en este periodo.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="clearfix">
        <div class="totals">
            <table>
                <tr>
                    <th>Total {{ $tipo_reporte == 'ordenes' ? 'Pedidos' : 'Unidades' }}</th>
                    <td class="text-center"><strong>{{ $totales['cantidad'] }}</strong></td>
                </tr>
                <tr>
                    <th>Total Dinero Anulado</th>
                    <td class="text-right text-danger"><strong>S/ {{ number_format($totales['monto'], 2) }}</strong>
                    </td>
                </tr>
            </table>
        </div>
    </div>

</body>

</html>
