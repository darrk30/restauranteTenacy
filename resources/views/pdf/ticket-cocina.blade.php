<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket Cocina</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace; /* Fuente tipo ticket */
            font-size: 12px;
            margin: 0;
            padding: 5px;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .info {
            margin-bottom: 5px;
        }
        .mesa {
            font-size: 18px;
            font-weight: bold;
            display: block;
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            border-bottom: 1px solid #000;
        }
        td {
            padding: 5px 0;
            vertical-align: top;
        }
        .qty {
            font-weight: bold;
            font-size: 14px;
            width: 30px;
        }
        .nota {
            display: block;
            font-weight: bold;
            font-style: italic;
            margin-top: 2px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 5px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $orden['tipo'] }}</div>
        <span class="mesa">MESA: {{ $orden['mesa'] }}</span>
        <div class="info">Mozo: {{ $orden['mozo'] }}</div>
        <div class="info">Fecha: {{ $orden['fecha'] }}</div>
        <div class="info">Pedido: #{{ $orden['pedido'] }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%">Cant</th>
                <th>Producto / Notas</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orden['items'] as $item)
                <tr>
                    <td class="qty">{{ $item['cantidad'] }}</td>
                    <td>
                        {{ $item['producto'] }}
                        @if(!empty($item['nota']))
                            <span class="nota">⚠️ {{ $item['nota'] }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        --- FIN DEL TICKET ---
    </div>
</body>
</html>