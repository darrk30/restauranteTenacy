<!DOCTYPE html>
<html>
<head>
    <title>Reporte de Rendimiento</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #333; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .restaurant-name { font-size: 20px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
        .report-title { font-size: 16px; margin-top: 5px; color: #666; }
        .date-range { margin-top: 10px; font-style: italic; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #1e3a8a; color: white; padding: 10px; text-transform: uppercase; font-size: 10px; }
        td { padding: 10px; border-bottom: 1px solid #eee; text-align: center; }
        .text-left { text-align: left; }
        
        .winner-box { 
            background-color: #f0f4ff; 
            border: 1px solid #1e3a8a; 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 8px;
        }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: right; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <div class="restaurant-name">{{ $restaurant }}</div>
        <div class="report-title">Reporte de Rendimiento de Mozos</div>
        <div class="date-range">Periodo: {{ $desde }} al {{ $hasta }}</div>
    </div>

    @if($stats->isNotEmpty())
        <div class="winner-box">
            <strong>Colaborador Destacado:</strong> {{ $stats->first()->waiter_name }} <br>
            <strong>Venta Total:</strong> S/ {{ number_format($stats->first()->total_ventas, 2) }}
        </div>

        <table>
            <thead>
                <tr>
                    <th class="text-left">Mozo / Colaborador</th>
                    <th>Pedidos</th>
                    <th>Ticket Promedio</th>
                    <th>Total Recaudado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stats as $row)
                    <tr>
                        <td class="text-left" style="font-weight: bold;">{{ $row->waiter_name }}</td>
                        <td>{{ $row->total_pedidos }}</td>
                        <td>S/ {{ number_format($row->ticket_promedio, 2) }}</td>
                        <td style="color: #166534; font-weight: bold;">S/ {{ number_format($row->total_ventas, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center; margin-top: 50px;">No se encontraron datos para el periodo seleccionado.</p>
    @endif

    <div class="footer">
        Generado el: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>