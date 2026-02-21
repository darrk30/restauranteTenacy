<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Caja</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }

        .restaurant-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .report-title {
            font-size: 14px;
            color: #666;
            margin: 5px 0;
        }

        .filters-info {
            font-size: 10px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            padding: 8px;
            font-weight: bold;
        }

        td {
            border: 1px solid #eee;
            padding: 7px;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .ingreso { color: #16a34a; font-weight: bold; }
        .egreso { color: #dc2626; font-weight: bold; }

        /* 🟢 Mejora del Resumen Final */
        .summary-wrapper {
            margin-top: 20px;
            width: 100%;
        }

        .summary-table {
            margin-left: auto; /* Alinea la tabla a la derecha */
            width: 250px;
            border: none;
        }

        .summary-table td {
            border: none;
            padding: 4px 8px;
            font-size: 12px;
        }

        .border-top {
            border-top: 1px solid #ccc !important;
        }

        .balance-final-box {
            background-color: #f9f9f9;
            border: 1px solid #ddd !important;
            padding: 10px !important;
        }

        .footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            width: 100%;
            font-size: 8px;
            color: #aaa;
            text-align: left;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="restaurant-name">{{ $restaurant }}</div>
        <div class="report-title">REPORTE DE INGRESOS Y EGRESOS</div>
        <div class="filters-info">
            Periodo: {{ $filtros['Desde'] }} hasta {{ $filtros['Hasta'] }} <br>
            Caja: <strong>{{ $filtros['Caja'] }}</strong> | Tipo: {{ $filtros['Tipo'] }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="15%">Fecha/Hora</th>
                <th width="10%">Tipo</th>
                <th width="25%">Beneficiario / Persona</th>
                <th width="35%">Motivo</th>
                <th width="15%" class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movimientos as $mov)
                <tr>
                    <td class="text-center">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-center {{ $mov->tipo_movimiento }}">
                        {{ ucfirst($mov->tipo_movimiento) }}
                    </td>
                    <td>
                        @if ($mov->personal_id)
                            {{ $mov->personal?->name ?? 'Personal' }} (Personal)
                        @else
                            {{ $mov->persona_externa ?? '-' }}
                        @endif
                    </td>
                    <td>{{ $mov->motivo }}</td>
                    <td class="text-right {{ $mov->tipo_movimiento }}">
                        {{ $mov->tipo_movimiento == 'ingreso' ? '+ S/ ' : '- S/ ' }} {{ number_format($mov->monto, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-wrapper">
        <table class="summary-table">
            <tr>
                <td class="text-right">Total Ingresos:</td>
                <td class="text-right ingreso">S/ {{ number_format($ingresos, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right">Total Egresos:</td>
                <td class="text-right egreso">S/ {{ number_format($egresos, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 5px;"></td>
            </tr>
            <tr class="balance-final-box">
                <td class="text-right"><strong>BALANCE NETO:</strong></td>
                <td class="text-right {{ $balance >= 0 ? 'ingreso' : 'egreso' }}">
                    <strong>S/ {{ number_format($balance, 2) }}</strong>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Generado el: {{ $fecha_emision }}
    </div>
</body>
</html>