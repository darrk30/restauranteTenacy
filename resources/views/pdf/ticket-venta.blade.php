<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sale->tipo_comprobante }} - {{ $sale->serie }}-{{ $sale->correlativo }}</title>
    <style>
        /* CONFIGURACIÓN PARA PANTALLA (WEB) */
        body {
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: 'Courier New', Courier, monospace;
        }

        .ticket-container {
            background-color: #fff;
            width: 72mm;
            padding: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            position: relative;
        }

        /* BOTONES DE ACCIÓN (SOLO PANTALLA) */
        .actions {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: sans-serif;
            font-weight: bold;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }

        .btn-print {
            background-color: #111827;
            color: white;
        }

        .btn-close {
            background-color: #e5e7eb;
            color: #374151;
        }

        /* ESTILOS DEL TICKET */
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
            margin: 10px 0;
        }

        .header-info h1 {
            font-size: 20px;
            margin: 5px 0;
            text-transform: uppercase;
        }

        .logo-container img {
            max-width: 50mm;
            height: auto;
            margin-bottom: 10px;
            filter: grayscale(1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 13px;
        }

        th {
            border-bottom: 1px solid #000;
            padding: 5px 0;
            text-align: left;
        }

        td {
            padding: 5px 0;
            vertical-align: top;
        }

        .total-row {
            font-size: 16px;
        }

        .payment-info {
            font-size: 12px;
            margin-top: 5px;
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

        /* CONFIGURACIÓN PARA IMPRESIÓN */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }

            .ticket-container {
                box-shadow: none;
                width: 72mm;
                padding: 0;
                margin: 0;
            }

            .actions {
                display: none;
            }

            @page {
                margin: 0;
            }
        }
    </style>
</head>

<body>

    @php
        // 🟢 Detectamos si debemos ocultar los botones (útil para el iframe del modal)
        $hideActions = request()->query('hide_actions') == 1;
    @endphp

    @if (!$hideActions)
        <div class="actions">
            <button onclick="window.print()" class="btn btn-print">
                🖨️ Imprimir Ticket
            </button>
            <a href="javascript:window.close();" class="btn btn-close">
                Cerrar
            </a>
        </div>
    @endif

    <div class="ticket-container">
        <div class="header-info text-center">
            @if ($tenant->logo)
                <div class="logo-container">
                    <img src="{{ asset('storage/' . $tenant->logo) }}" alt="Logo">
                </div>
            @endif

            <h1>{{ $tenant->name ?? 'RESTAURANTE' }}</h1>
            <p class="bold">RUC: {{ $tenant->ruc ?? '00000000000' }}</p>
            <p>{{ $tenant->address ?? 'DIRECCIÓN NO REGISTRADA' }}</p>

            @if ($tenant->phone || $tenant->city)
                <p>
                    @if ($tenant->phone)
                        Telf: {{ $tenant->phone }}
                    @endif
                    @if ($tenant->city)
                        | {{ $tenant->city }}
                    @endif
                </p>
            @endif

            <div class="divider"></div>
            <p class="bold" style="font-size: 15px;">{{ $sale->tipo_comprobante }}</p>
            <p class="bold" style="font-size: 16px;">{{ $sale->serie }}-{{ $sale->correlativo }}</p>
        </div>

        <div class="info-sec" style="font-size: 12px;">
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
                    <th style="width: 15%;">CANT</th>
                    <th style="width: 60%;">DESCRIPCIÓN</th>
                    <th style="width: 25%; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->details as $item)
                    <tr>
                        <td>{{ (int) $item->cantidad }}</td>
                        <td>
                            {{ $item->product_name }}
                            @if ($item->item_type === 'Promocion')
                                <br><small style="color: #666;">(PROMOCIÓN)</small>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <table style="margin-top: 0;">
            @php
                $esNotaVenta = str_contains(strtolower($sale->tipo_comprobante), 'nota');
            @endphp

            @if (!$esNotaVenta)
                <tr>
                    <td class="text-right">OP. GRAVADA:</td>
                    <td class="text-right" style="width: 35%;">S/ {{ number_format((float) $sale->op_gravada, 2) }}
                    </td>
                </tr>
                <tr>
                    <td class="text-right">IGV (18%):</td>
                    <td class="text-right">S/ {{ number_format((float) $sale->monto_igv, 2) }}</td>
                </tr>
            @endif

            {{-- 🟢 CAMPO DE DESCUENTO --}}
            <tr>
                <td class="text-right">DESCUENTO:</td>
                <td class="text-right">S/ {{ number_format((float) ($sale->monto_descuento ?? 0), 2) }}</td>
            </tr>

            <tr class="total-row bold">
                <td class="text-right">TOTAL:</td>
                <td class="text-right">S/ {{ number_format((float) $sale->total, 2) }}</td>
            </tr>
        </table>

        <div class="payment-info">
            <p class="bold" style="margin-bottom: 5px;">MÉTODO DE PAGO:</p>
            @php
                $pagos = \App\Models\CashRegisterMovement::where('referencia_id', $sale->id)
                    ->where('referencia_type', get_class($sale))
                    ->where('tipo', 'Ingreso')
                    ->with('paymentMethod')
                    ->get();
            @endphp

            @forelse ($pagos as $pago)
                <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                    <span>- {{ $pago->paymentMethod->name ?? 'MÉTODO DESCONOCIDO' }}</span>
                    <span class="bold">S/ {{ number_format($pago->monto, 2) }}</span>
                </div>
            @empty
                <p>Pago procesado.</p>
            @endforelse
        </div>

        <div class="footer text-center">
            <div class="divider"></div>
            @if ($esNotaVenta)
                <p class="bold" style="font-size: 10px;">ESTE DOCUMENTO NO ES UN COMPROBANTE DE PAGO</p>
            @else
                <p>Representación impresa del comprobante electrónico.</p>
            @endif
            <p class="bold">¡GRACIAS POR SU COMPRA!</p>
            <p style="font-size: 10px; margin-top: 10px; color: #666;">
                {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}
            </p>
        </div>
    </div>

</body>

</html>
