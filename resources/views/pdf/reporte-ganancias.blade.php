<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $nombre_reporte }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .info-table td { font-size: 11px; padding: 4px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 6px; }
        .data-table th { background-color: #f3f4f6; font-weight: bold; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totales { font-weight: bold; background-color: #e5e7eb; }
    </style>
</head>
<body>
    
    {{-- CABECERA DEL REPORTE --}}
    <div class="header">
        <div class="title">{{ $nombre_reporte }}</div>
        <div><strong>Restaurante:</strong> {{ $restaurant }}</div>
    </div>

    {{-- METADATA: FECHA Y FILTROS APLICADOS --}}
    <table class="info-table">
        <tr>
            <td><strong>Fecha de Exportación:</strong> {{ $fecha_exportacion }}</td>
            <td><strong>Filtro Desde:</strong> {{ $filtros['Desde'] }}</td>
            <td><strong>Filtro Hasta:</strong> {{ $filtros['Hasta'] }}</td>
            <td><strong>Tipo Comprobante:</strong> {{ $filtros['Comprobante'] }}</td>
        </tr>
    </table>

    {{-- DATOS --}}
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Comprobante</th>
                <th class="text-right">Ingreso (Venta)</th>
                <th class="text-right">Costo Real</th>
                <th class="text-right">Ganancia Neta</th>
                <th class="text-center">Margen %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ventas as $venta)
                @php
                    $ganancia_neta = $venta->total - $venta->costo_total;
                    $margen = $venta->total > 0 ? ($ganancia_neta / $venta->total) * 100 : 0;
                @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($venta->fecha_emision)->format('d/m/Y H:i') }}</td>
                    <td>{{ $venta->tipo_comprobante }} {{ $venta->serie }}-{{ $venta->correlativo }}</td>
                    <td class="text-right">S/ {{ number_format($venta->total, 2) }}</td>
                    <td class="text-right">S/ {{ number_format($venta->costo_total, 2) }}</td>
                    <td class="text-right">S/ {{ number_format($ganancia_neta, 2) }}</td>
                    <td class="text-center">{{ number_format($margen, 1) }}%</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totales">
                <td colspan="2" class="text-right">TOTALES ACUMULADOS:</td>
                <td class="text-right">S/ {{ number_format($totales['ingresos'], 2) }}</td>
                <td class="text-right">S/ {{ number_format($totales['costos'], 2) }}</td>
                <td class="text-right">S/ {{ number_format($totales['ganancia'], 2) }}</td>
                <td class="text-center">
                    {{ $totales['ingresos'] > 0 ? number_format(($totales['ganancia'] / $totales['ingresos']) * 100, 1) : '0.0' }}%
                </td>
            </tr>
        </tfoot>
    </table>

</body>
</html>