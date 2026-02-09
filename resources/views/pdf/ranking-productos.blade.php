<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .restaurant-name { font-size: 16px; font-weight: bold; }
        .area-section { margin-bottom: 30px; page-break-inside: avoid; }
        .area-title { background: #f2f2f2; padding: 8px; font-size: 13px; font-weight: bold; border-left: 5px solid #eab308; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #eee; padding: 8px; border: 1px solid #ccc; text-align: center; }
        td { padding: 8px; border: 1px solid #ccc; text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .clase-a { color: #059669; font-weight: bold; }
        .footer { position: fixed; bottom: 0; width: 100%; font-size: 9px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="restaurant-name">{{ $restaurant->name }}</div>
        <div style="font-size: 14px; margin: 5px 0;">REPORTE: RANKING ESTRATÉGICO DE PRODUCTOS</div>
        <div>Periodo: {{ $desde }} al {{ $hasta }} | Generado: {{ $fecha }}</div>
    </div>

    @foreach ($rankings as $ranking)
        <div class="area-section">
            <div class="area-title">{{ strtoupper($ranking['titulo']) }}</div>
            <table>
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="50%" class="text-left">Producto / Variante</th>
                        <th width="15%">Cantidad</th>
                        <th width="15%" class="text-right">Recaudación</th>
                        <th width="15%">Clase ABC</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ranking['data'] as $index => $item)
                        @php $clase = $index < 3 ? 'A' : ($index < 7 ? 'B' : 'C'); @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="text-left">
                                <strong>{{ $item->product_name }}</strong><br>
                                <small>{{ $item->variant?->full_name }}</small>
                            </td>
                            <td>{{ number_format($item->total_cantidad, 0) }}</td>
                            <td class="text-right">S/ {{ number_format($item->total_dinero, 2) }}</td>
                            <td class="{{ $clase == 'A' ? 'clase-a' : '' }}">Clase {{ $clase }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="footer">Sistema de Gestión de Restaurantes - Página 1</div>
</body>
</html>