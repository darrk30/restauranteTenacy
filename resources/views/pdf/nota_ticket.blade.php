<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $nota->tipo_nota === '07' ? 'NC' : 'ND' }} - {{ $nota->serie }}-{{ str_pad($nota->correlativo, 8, '0', STR_PAD_LEFT) }}</title>
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
        $nombreComprobante = $nota->tipo_nota === '07' ? 'NOTA DE CRÉDITO' : 'NOTA DE DÉBITO';
        // Obtenemos el porcentaje dinámico usando tu helper
        $porcentajeIgv = get_tax_percentage($tenant->id);
    @endphp

    <div class="text-center">
        @if ($tenant->logo)
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
        {{ $nota->serie }}-{{ str_pad($nota->correlativo, 8, '0', STR_PAD_LEFT) }}
    </div>

    <div class="divider"></div>

    <table>
        <tr>
            <td width="35%" class="bold">F. Emisión:</td>
            <td>{{ \Carbon\Carbon::parse($nota->fecha_emision)->format('Y-m-d H:i:s') }}</td>
        </tr>
        <tr>
            <td class="bold">Cliente:</td>
            <td>{{ $sale->nombre_cliente ?? 'CLIENTES VARIOS' }}</td>
        </tr>
        <tr>
            <td class="bold">{{ strlen($sale->numero_documento) == 11 ? 'RUC:' : 'Doc:' }}</td>
            <td>{{ $sale->numero_documento ?? '99999999' }}</td>
        </tr>
        
        <tr>
            <td colspan="2"><div class="divider-dashed"></div></td>
        </tr>
        <tr>
            <td class="bold">Doc. Afectado:</td>
            <td>{{ $sale->serie }}-{{ $sale->correlativo }}</td>
        </tr>
        <tr>
            <td class="bold">Motivo:</td>
            <td>{{ $nota->des_motivo }}</td>
        </tr>
        <tr>
            <td colspan="2"><div class="divider-dashed"></div></td>
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
            @foreach ($nota->details as $item)
                @php
                    // Al ser casteado como array en el modelo, lo leemos como array asociativo
                    $cantidad = $item['cantidad'] ?? 1;
                    $nombre = $item['product_name'] ?? $item['descripcion'] ?? 'Producto';
                    $precioUnitario = $item['precio_unitario'] ?? $item['precio'] ?? 0;
                    $subtotal = $cantidad * $precioUnitario;
                @endphp
                <tr>
                    <td>{{ (int) $cantidad }} NIU</td>
                    <td>{{ $nombre }}</td>
                    <td class="text-right">{{ number_format($precioUnitario, 2) }}</td>
                    <td class="text-right">{{ number_format($subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider-dashed"></div>

    <table class="table-totals" style="width: 85%; margin-left: auto;">
        <tr>
            <td class="bold">OP. GRAVADAS:</td>
            <td class="text-right">S/ {{ number_format($nota->op_gravada, 2) }}</td>
        </tr>
        <tr>
            <td class="bold">IGV ({{ $porcentajeIgv }}%):</td>
            <td class="text-right">S/ {{ number_format($nota->monto_igv, 2) }}</td>
        </tr>
        <tr>
            <td class="bold" style="font-size: 11px;">TOTAL A PAGAR:</td>
            <td class="bold text-right" style="font-size: 11px;">S/ {{ number_format($nota->total, 2) }}</td>
        </tr>
    </table>

    <p style="margin-top: 6px;"><span class="bold">Son:</span> {{ $nota->total_letras ?? 'CERO CON 00/100 SOLES' }}</p>

    <div class="divider"></div>
    
    <table class="qr-container">
        <tr>
            <td width="35%">
                @if ($nota->qr_data)
                    <img src="data:image/svg+xml;base64,{{ base64_encode(SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->margin(0)->size(120)->generate($nota->qr_data)) }}" class="qr-image">
                @endif
            </td>
            <td width="65%" style="padding-left: 10px; font-size: 8px;">
                <p class="bold">CÓDIGO HASH:</p>
                <p style="margin-bottom: 4px; word-break: break-all;">{{ $nota->hash }}</p>
                <p class="bold">CAJERO/EMISOR:</p>
                <p>{{ $nota->user->name ?? 'Sistema' }}</p>
            </td>
        </tr>
    </table>

    <div class="text-center footer-text">
        <p>Representación impresa de la {{ $nombreComprobante }}</p>
        <p>Consulte su documento en: <strong>https://tu-sistema.com/buscar</strong></p>
    </div>

</body>
</html>