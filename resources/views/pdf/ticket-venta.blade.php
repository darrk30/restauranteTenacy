<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sale->tipo_comprobante }} - {{ $sale->serie }}-{{ $sale->correlativo }}</title>
    <style>
        /* RESET BÁSICO PARA QUITAR ESPACIOS MUERTOS */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* CONFIGURACIÓN GENERAL DEL TICKET */
        body {
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            /* Letra pequeña para ahorrar espacio */
            line-height: 1.2;
            /* Interlineado compacto */
            color: #000;
            padding: 20px;
        }

        .ticket-container {
            background-color: #fff;
            width: 80mm;
            /* Ancho estándar de ticketera */
            padding: 10px 15px;
            /* Padding interno reducido */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        /* BOTONES DE PANTALLA */
        .actions {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: sans-serif;
            font-weight: bold;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-print {
            background-color: #111827;
            color: white;
        }

        .btn-close {
            background-color: #e5e7eb;
            color: #374151;
        }

        /* UTILIDADES DE TEXTO */
        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        p {
            margin-bottom: 2px;
        }

        /* SEPARADORES */
        .divider {
            border-top: 1px solid #000;
            margin: 6px 0;
        }

        .divider-dashed {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        /* LOGO */
        .logo-container img {
            max-width: 45mm;
            height: auto;
            margin-bottom: 5px;
            filter: grayscale(1);
        }

        /* TÍTULO DEL COMPROBANTE (FACTURA/BOLETA) */
        .doc-title {
            font-size: 15px;
            font-family: sans-serif;
            /* Resalta más con sans-serif */
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin: 8px 0;
        }

        /* CUADRICULA DE INFORMACIÓN DEL CLIENTE */
        .info-grid {
            display: grid;
            grid-template-columns: 85px 1fr;
            gap: 1px 5px;
            margin-bottom: 5px;
        }

        /* TABLA DE PRODUCTOS */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }

        th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            text-align: left;
            font-size: 10px;
        }

        td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 11px;
        }

        /* SECCIÓN DE TOTALES */
        .table-totals {
            width: 100%;
            margin-left: auto;
        }

        /* SECCIÓN QR Y PAGOS (ESTILO IDE SOLUTION) */
        .qr-section {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 10px;
        }

        .qr-code img {
            width: 25mm;
            height: 25mm;
        }

        .qr-text {
            flex: 1;
            font-size: 10px;
            word-break: break-all;
        }

        @if (request()->query('hide_actions') == 1)
            body {
                background-color: transparent !important;
                padding: 0 !important;
            }

            .ticket-container {
                box-shadow: none !important;
                margin: 0 !important;
            }
        @endif

        /* IMPRESIÓN */
        /* IMPRESIÓN */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
                align-items: flex-start;
            }

            .ticket-container {
                box-shadow: none;
                /* 🟢 Al agregar márgenes a la página, cambiamos el ancho a 100% para que no se desborde del papel de 80mm */
                width: 100%;
                max-width: 80mm;
                padding: 0;
                margin: 0;
            }

            .actions {
                display: none;
            }

            /* 🟢 AQUÍ DEFINIMOS LOS MÁRGENES PEQUEÑOS DE LA IMPRESORA */
            @page {
                /* 3mm arriba y abajo, 4mm a la izquierda y derecha */
                margin: 3mm 4mm;
            }
        }
    </style>
</head>

<body>

    @php
        $hideActions = request()->query('hide_actions') == 1;
        $esNotaVenta = str_contains(strtolower($sale->tipo_comprobante), 'nota');

        // Formatear el nombre del comprobante según el tipo
        $nombreComprobante = strtoupper($sale->tipo_comprobante);
        if ($sale->tipo_comprobante === 'Factura') {
            $nombreComprobante = 'FACTURA ELECTRÓNICA';
        }
        if ($sale->tipo_comprobante === 'Boleta') {
            $nombreComprobante = 'BOLETA DE VENTA ELECTRÓNICA';
        }
        $porcentajeIgv = get_tax_percentage($tenant->id);
    @endphp

    @if (!$hideActions)
        <div class="actions">
            <button onclick="window.print()" class="btn btn-print">🖨️ Imprimir Ticket</button>
            <a href="javascript:window.close();" class="btn btn-close">Cerrar</a>
        </div>
    @endif

    <div class="ticket-container">
        <div class="text-center">
            @if ($tenant->logo)
                <div class="logo-container">
                    <img src="{{ asset('storage/' . $tenant->logo) }}" alt="Logo">
                </div>
            @endif

            <p class="bold" style="font-size: 14px;">{{ $tenant->name ?? 'RESTAURANTE' }}</p>
            <p>RUC {{ $tenant->ruc ?? '00000000000' }}</p>
            <p>{{ $tenant->address ?? 'DIRECCIÓN NO REGISTRADA' }}</p>

            @if ($tenant->city || $tenant->phone)
                <p>
                    {{ $tenant->city ?? '' }}
                    @if ($tenant->phone)
                        - Telf: {{ $tenant->phone }}
                    @endif
                </p>
            @endif
        </div>

        <div class="divider"></div>

        <div class="doc-title">
            {{ $nombreComprobante }}<br>
            {{ $sale->serie }}-{{ $sale->correlativo }}
        </div>

        <div class="divider"></div>

        <div class="info-grid">
            <span class="bold">F. Emisión:</span>
            <span>{{ \Carbon\Carbon::parse($sale->fecha_emision)->format('Y-m-d') }}</span>

            <span class="bold">H. Emisión:</span>
            <span>{{ \Carbon\Carbon::parse($sale->fecha_emision)->format('H:i:s') }}</span>

            <span class="bold">Cliente:</span>
            <span>{{ $sale->nombre_cliente ?? 'Clientes - Varios' }}</span>

            <span class="bold">{{ strlen($sale->numero_documento) == 11 ? 'RUC:' : 'DNI/Doc:' }}</span>
            <span>{{ $sale->numero_documento ?? '99999999' }}</span>

            <span class="bold">Dirección:</span>
            <span>{{ $sale->client->direccion ?? ($sale->client->address ?? '-') }}</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">CANT.</th>
                    <th style="width: 45%;">DESCRIPCIÓN</th>
                    <th style="width: 20%; text-align: right;">P.UNIT</th>
                    <th style="width: 20%; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->details as $item)
                    <tr>
                        <td style="white-space: nowrap;">{{ (int) $item->cantidad }} {{ $item->unidad ?? 'NIU' }}</td>
                        <td>
                            {{ $item->product_name }}
                            @if ($item->item_type === 'Promocion')
                                <br><small>(PROMOCIÓN)</small>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($item->precio_unitario ?? ($item->subtotal / max($item->cantidad, 1)), 2) }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider-dashed"></div>

        <table class="table-totals">
            @if (!$esNotaVenta)
                <tr>
                    <td class="bold">OP. GRAVADAS:</td>
                    <td class="text-right">S/ {{ number_format((float) $sale->op_gravada, 2) }}</td>
                </tr>
                <tr>
                    <td class="bold">IGV ({{ $porcentajeIgv }}%):</td>
                    <td class="text-right">S/ {{ number_format((float) $sale->monto_igv, 2) }}</td>
                </tr>
            @endif

            @if (($sale->monto_descuento ?? 0) > 0)
                <tr>
                    <td class="bold">DESCUENTO:</td>
                    <td class="text-right">S/ {{ number_format((float) $sale->monto_descuento, 2) }}</td>
                </tr>
            @endif

            <tr>
                <td class="bold" style="font-size: 13px;">TOTAL A PAGAR:</td>
                <td class="bold text-right" style="font-size: 13px;">S/ {{ number_format((float) $sale->total, 2) }}
                </td>
            </tr>
        </table>

        <p style="margin-top: 10px;">
            <span class="bold">Son:</span> {{ ucfirst(strtolower($sale->total_letras ?? 'Cero con 00/100 Soles')) }}
        </p>

        @if (!$esNotaVenta)
            <div class="qr-section">
                @if ($sale->qr_data)
                    <div class="qr-code">
                        <img src="data:image/svg+xml;base64,{{ base64_encode(SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->margin(0)->size(120)->generate($sale->qr_data)) }}"
                            alt="QR">
                    </div>
                @endif

                <div class="qr-text">
                    @if ($sale->hash)
                        <p class="bold">CÓDIGO HASH:</p>
                        <p style="margin-bottom: 5px;">{{ $sale->hash }}</p>
                    @endif

                    <p class="bold">CONDICIÓN DE PAGO: Contado</p>

                    <p class="bold">PAGOS:</p>
                    @php
                        $pagos = \App\Models\CashRegisterMovement::where('referencia_id', $sale->id)
                            ->where('referencia_type', get_class($sale))
                            ->where('tipo', 'Ingreso')
                            ->with('paymentMethod')
                            ->get();
                    @endphp
                    @foreach ($pagos as $pago)
                        <p>• {{ $pago->paymentMethod->name ?? 'Efectivo' }} - S/ {{ number_format($pago->monto, 2) }}
                        </p>
                    @endforeach
                </div>
            </div>
        @else
            <div class="divider-dashed"></div>
            <p class="bold text-center">PAGOS:</p>
            @php
                $pagos = \App\Models\CashRegisterMovement::where('referencia_id', $sale->id)
                    ->where('referencia_type', get_class($sale))
                    ->where('tipo', 'Ingreso')
                    ->with('paymentMethod')
                    ->get();
            @endphp
            @foreach ($pagos as $pago)
                <p class="text-center">{{ $pago->paymentMethod->name ?? 'Efectivo' }} - S/
                    {{ number_format($pago->monto, 2) }}</p>
            @endforeach
        @endif

        <div class="divider"></div>

        <p><span class="bold">Vendedor:</span><br>{{ $sale->user->name }}</p>

        <br>

        <div class="text-center" style="font-size: 10px; color: #333;">
            @if (!$esNotaVenta)
                <p>Para consultar el comprobante ingresar a<br>
                    <span class="bold">https://tu-dominio.com/buscar</span>
                </p>
                <p>Representación impresa de la {{ $nombreComprobante }}</p>
            @else
                <p class="bold" style="font-size: 11px;">ESTE DOCUMENTO NO ES UN COMPROBANTE DE PAGO FISCAL</p>
            @endif
        </div>

    </div>

</body>

</html>
