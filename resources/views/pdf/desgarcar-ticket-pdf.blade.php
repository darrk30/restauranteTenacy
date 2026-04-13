<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $sale->tipo_comprobante }} - {{ $sale->serie }}-{{ $sale->correlativo }}</title>
    <style>
        /* RESET PARA PDF (DomPDF) */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @page {
            margin: 0;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            width: 72mm; /* Ancho ajustado para ticketera */
            margin: 0;
            padding: 5mm;
            background-color: #fff;
            color: #000;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        
        .divider {
            border-top: 1px solid #000;
            margin: 5px 0;
        }

        .divider-dashed {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }

        /* CABECERA */
        .logo {
            max-width: 40mm;
            height: auto;
            margin-bottom: 4px;
        }

        .doc-title {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            margin: 8px 0;
            text-transform: uppercase;
        }

        /* TABLAS DE DATOS */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }

        td {
            vertical-align: top;
            padding: 2px 0;
        }

        th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            text-align: left;
            font-size: 9px;
        }

        /* TOTALES */
        .table-totals td {
            padding: 1px 0;
        }

        /* SECCIÓN FINAL (QR + HASH) */
        .qr-container {
            width: 100%;
            margin-top: 10px;
        }

        .qr-image {
            width: 25mm;
            height: 25mm;
        }

        .footer-text {
            font-size: 9px;
            color: #333;
            margin-top: 10px;
        }
    </style>
</head>

<body>

    @php
        $esNotaVenta = str_contains(strtolower($sale->tipo_comprobante), 'nota');
        $nombreComprobante = strtoupper($sale->tipo_comprobante);
        if ($sale->tipo_comprobante === 'Factura') $nombreComprobante = 'FACTURA ELECTRÓNICA';
        if ($sale->tipo_comprobante === 'Boleta') $nombreComprobante = 'BOLETA DE VENTA ELECTRÓNICA';
        $porcentajeIgv = get_tax_percentage($tenant->id);
    @endphp

    <div class="text-center">
        @if ($tenant->logo)
            {{-- DomPDF requiere public_path para encontrar la imagen localmente --}}
            <img src="{{ public_path('storage/' . $tenant->logo) }}" class="logo">
        @endif
        <p class="bold" style="font-size: 12px;">{{ strtoupper($tenant->name) }}</p>
        <p>RUC {{ $tenant->ruc }}</p>
        <p>{{ $tenant->address }}</p>
        <p>{{ $tenant->city }} {{ $tenant->phone ? '- Telf: '.$tenant->phone : '' }}</p>
    </div>

    <div class="divider"></div>

    <div class="doc-title">
        {{ $nombreComprobante }}<br>
        {{ $sale->serie }}-{{ $sale->correlativo }}
    </div>

    <div class="divider"></div>

    <table>
        <tr>
            <td width="35%" class="bold">F. Emisión:</td>
            <td>{{ \Carbon\Carbon::parse($sale->fecha_emision)->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td class="bold">H. Emisión:</td>
            <td>{{ \Carbon\Carbon::parse($sale->fecha_emision)->format('H:i:s') }}</td>
        </tr>
        <tr>
            <td class="bold">Cliente:</td>
            <td>{{ $sale->nombre_cliente }}</td>
        </tr>
        <tr>
            <td class="bold">{{ strlen($sale->numero_documento) == 11 ? 'RUC:' : 'Doc:' }}</td>
            <td>{{ $sale->numero_documento }}</td>
        </tr>
        <tr>
            <td class="bold">Dirección:</td>
            <td>{{ $sale->client->direccion ?? ($sale->client->address ?? '-') }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th width="15%">CANT.</th>
                <th width="45%">DESCRIPCIÓN</th>
                <th width="20%" class="text-right">P.UNIT</th>
                <th width="20%" class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->details as $item)
                <tr>
                    <td>{{ (int) $item->cantidad }} NIU</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="text-right">{{ number_format($item->precio_unitario, 2) }}</td>
                    <td class="text-right">{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider-dashed"></div>

    <table class="table-totals" style="width: 85%; margin-left: auto;">
        @if (!$esNotaVenta)
            <tr>
                <td class="bold">OP. GRAVADAS:</td>
                <td class="text-right">S/ {{ number_format($sale->op_gravada, 2) }}</td>
            </tr>
            <tr>
                <td class="bold">IGV ({{ $porcentajeIgv }}%):</td>
                <td class="text-right">S/ {{ number_format($sale->monto_igv, 2) }}</td>
            </tr>
        @endif
        @if(($sale->monto_descuento ?? 0) > 0)
            <tr>
                <td class="bold">DESCUENTO:</td>
                <td class="text-right">S/ {{ number_format($sale->monto_descuento, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td class="bold" style="font-size: 11px;">TOTAL A PAGAR:</td>
            <td class="bold text-right" style="font-size: 11px;">S/ {{ number_format($sale->total, 2) }}</td>
        </tr>
    </table>

    <p style="margin-top: 6px;"><span class="bold">Son:</span> {{ $sale->total_letras }}</p>

    @if (!$esNotaVenta)
        <div class="divider"></div>
        <table class="qr-container">
            <tr>
                <td width="35%">
                    @if ($sale->qr_data)
                        <img src="data:image/svg+xml;base64,{{ base64_encode(SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->margin(0)->size(120)->generate($sale->qr_data)) }}" class="qr-image">
                    @endif
                </td>
                <td width="65%" style="padding-left: 10px; font-size: 8px;">
                    <p class="bold">CÓDIGO HASH:</p>
                    <p style="margin-bottom: 4px;">{{ $sale->hash }}</p>
                    <p class="bold">CONDICIÓN DE PAGO: Contado</p>
                    <p class="bold">PAGOS:</p>
                    @php
                        $pagos = \App\Models\CashRegisterMovement::where('referencia_id', $sale->id)
                            ->where('referencia_type', get_class($sale))
                            ->where('tipo', 'Ingreso')
                            ->with('paymentMethod')->get();
                    @endphp
                    @foreach ($pagos as $pago)
                        <p>• {{ $pago->paymentMethod->name }} - S/ {{ number_format($pago->monto, 2) }}</p>
                    @endforeach
                </td>
            </tr>
        </table>
    @endif

    <div class="divider"></div>
    <p><span class="bold">Vendedor:</span> {{ $sale->user->name }}</p>

    <div class="text-center footer-text">
        @if (!$esNotaVenta)
            <p>Representación impresa de la {{ $nombreComprobante }}</p>
            <p>Consulte su documento en: <strong>https://tu-sistema.com/buscar</strong></p>
        @else
            <p class="bold">ESTE DOCUMENTO NO TIENE VALIDEZ FISCAL</p>
        @endif
        <p class="bold" style="margin-top: 5px;">¡GRACIAS POR SU COMPRA!</p>
    </div>

</body>
</html>