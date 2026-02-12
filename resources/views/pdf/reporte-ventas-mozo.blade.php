<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; font-size: 11px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .header h1 { color: #1e3a8a; margin: 0; text-transform: uppercase; font-size: 18px; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; }
        .main-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .main-table th { background-color: #f1f5f9; color: #1e3a8a; padding: 10px; border: 1px solid #e2e8f0; text-align: left; font-size: 10px; }
        .main-table td { padding: 8px; border: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .footer { margin-top: 30px; text-align: right; font-size: 14px; }
        .total-box { display: inline-block; background: #1e3a8a; color: white; padding: 10px 20px; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte Detallado de Ventas</h1>
        <p>Generado el: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Colaborador:</strong> {{ $mozo->name }}</td>
            <td class="text-right"><strong>Rango:</strong> {{ date('d/m/Y', strtotime($desde)) }} al {{ date('d/m/Y', strtotime($hasta)) }}</td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th>Fecha / Hora</th>
                <th>Comprobante</th>
                <th>Cliente / Documento</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ventas as $v)
            <tr>
                <td>{{ \Carbon\Carbon::parse($v->fecha_emision)->format('d/m/Y H:i') }}</td>
                <td>{{ $v->tipo_comprobante }} {{ $v->serie }}-{{ $v->correlativo }}</td>
                <td>
                    {{ $v->nombre_cliente ?? 'PÚBLICO GENERAL' }} <br>
                    <small style="color: #666">{{ $v->tipo_documento }}: {{ $v->numero_documento }}</small>
                </td>
                <td class="text-right">S/ {{ number_format($v->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="total-box">
            TOTAL RECAUDADO: S/ {{ number_format($total, 2) }}
        </div>
    </div>
</body>
</html>