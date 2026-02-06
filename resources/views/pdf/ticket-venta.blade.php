<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'Ticketing', 'Courier New', Courier, monospace;
            font-size: 13px;
            width: 72mm;
            /* Estándar térmico */
            margin: 0 auto;
            padding: 10px;
            color: #000;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .header-info {
            margin-bottom: 10px;
            line-height: 1.2;
        }

        .header-info h1 {
            font-size: 18px;
            margin: 0;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }

        th {
            border-bottom: 1px solid #000;
            padding: 4px 0;
        }

        td {
            padding: 4px 0;
            vertical-align: top;
        }

        .totals-table td {
            padding: 2px 0;
        }

        .total-row {
            font-size: 16px;
        }

        .footer {
            margin-top: 15px;
            font-size: 11px;
        }
    </style>
</head>

<body>
    <div class="header-info text-center">
        <h1>{{ $tenant->name ?? 'RESTAURANTE' }}</h1>
        <p>{{ $tenant->address ?? 'DIRECCIÓN DEL LOCAL' }}</p>
        <p>RUC: {{ $tenant->ruc ?? '00000000000' }}</p>
        <div class="divider"></div>
        <p class="bold">{{ $sale->tipo_comprobante }}: {{ $sale->serie }}-{{ $sale->correlativo }}</p>
    </div>

    <div class="info-sec">
        <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($sale->fecha_emision)->format('d/m/Y H:i') }}</p>
        <p><strong>Cajero:</strong> {{ $sale->user->name }}</p>
        <div class="divider"></div>
        <p><strong>Cliente:</strong> {{ $sale->nombre_cliente ?? 'PÚBLICO EN GENERAL' }}</p>
        @if ($sale->numero_documento)
            <p><strong>{{ strlen($sale->numero_documento) == 11 ? 'RUC' : 'DNI' }}:</strong>
                {{ $sale->numero_documento }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-left">CANT</th>
                <th class="text-left">DESCRIPCIÓN</th>
                <th class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->details as $item)
                <tr>
                    <td style="width: 15%;">{{ (int) $item->cantidad }}</td>
                    <td style="width: 60%;">{{ $item->product_name }}</td>
                    <td class="text-right" style="width: 25%;">{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table class="totals-table">
        <tr>
            <td class="text-right">OP. GRAVADA:</td>
            <td class="text-right">S/ {{ number_format((float) $sale->op_gravada, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right">IGV (18%):</td>
            <td class="text-right">S/ {{ number_format((float) $sale->monto_igv, 2) }}</td>
        </tr>
        <tr class="total-row bold">
            <td class="text-right">TOTAL:</td>
            <td class="text-right">S/ {{ number_format((float) $sale->total, 2) }}</td>
        </tr>
    </table>

    <div class="footer text-center">
        <p>Representación impresa de la {{ $sale->tipo_comprobante }}</p>
        <p class="bold">¡GRACIAS POR SU PREFERENCIA!</p>
    </div>
</body>

</html>
