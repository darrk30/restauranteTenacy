<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        /* 🟢 Estilos Globales: Fuente monospace uniforme */
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            width: 80mm;
            margin: 0;
            padding: 10px;
            color: #000;
        }

        /* 🟢 Clases Utilitarias */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        
        .no-margin { margin: 0; }
        .mt-10 { margin-top: 10px; }
        .mb-5 { margin-bottom: 5px; }

        /* 🟢 Separadores */
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        /* 🟢 Encabezado del Local */
        .header-title {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
        }
        .header-subtitle {
            text-decoration: underline;
            margin: 10px 0;
            font-size: 14px;
        }

        /* 🟢 Estilos de la Tabla de Productos */
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .ticket-table th {
            border-bottom: 1px dashed #000;
            padding-bottom: 5px;
            font-weight: bold;
        }
        .ticket-table td {
            vertical-align: top;
            padding-top: 6px;
        }
        .col-qty { width: 12%; text-align: center; font-weight: bold;}
        .col-desc { width: 48%; text-align: left; padding-right: 5px; }
        .col-price { width: 20%; text-align: right; }
        .col-total { width: 20%; text-align: right; font-weight: bold; }

        /* 🟢 Notas del producto */
        .item-note {
            font-size: 10px;
            color: #444;
            display: block;
            margin-top: 2px;
        }

        /* 🟢 Totales y Pie de página */
        .total-section {
            font-size: 16px;
            margin-top: 5px;
            margin-bottom: 5px;
        }
        .footer-note {
            font-size: 11px;
            margin-top: 15px;
        }
    </style>
</head>

<body>
    {{-- CABECERA DEL RESTAURANTE --}}
    <div class="text-center">
        <h2 class="header-title">{{ $order->restaurant->name ?? 'Restaurante' }}</h2>
        <p class="no-margin">RUC: {{ $order->restaurant->ruc ?? '-----------' }}</p>
        <h3 class="header-subtitle">PRE-CUENTA</h3>
    </div>

    {{-- DATOS DE LA ORDEN --}}
    <div>
        <p class="no-margin">ORDEN: #{{ $order->code ?? $order->id }}</p>
        <p class="no-margin">TIPO: <span class="uppercase">{{ $order->canal ?? 'SALÓN' }}</span></p>

        @if (strtolower($order->canal ?? 'salon') === 'salon')
            <p class="no-margin">MESA: {{ $order->table->name ?? '---' }}</p>
            @if ($order->table && $order->table->floor)
                <p class="no-margin">PISO: {{ $order->table->floor->name ?? '---' }}</p>
            @endif
        @endif

        <p class="no-margin">MOZO: {{ $order->user->name ?? '---' }}</p>
        <p class="no-margin mb-5">FECHA: {{ now()->format('d/m/Y H:i') }}</p>
        
        <div class="divider"></div>
        
        <p class="no-margin">CLIENTE: PUBLICO VARIOS</p>
        <p class="no-margin">DNI: 00000000</p>
    </div>

    <div class="divider"></div>

    {{-- TABLA DE DETALLES --}}
    <table class="ticket-table">
        <thead>
            <tr>
                <th class="col-qty">CANT</th>
                <th class="col-desc">DESCRIPCIÓN</th>
                <th class="col-price">P.U.</th>
                <th class="col-total">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->details as $item)
                <tr>
                    <td class="col-qty">{{ $item->cantidad }}</td>
                    
                    <td class="col-desc">
                        {{ $item->product_name }}
                        @if($item->notes)
                            <span class="item-note">* {{ $item->notes }}</span>
                        @endif
                    </td>
                    
                    <td class="col-price">{{ number_format($item->price, 2) }}</td>
                    <td class="col-total">{{ number_format($item->subTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="divider"></div>
    
    {{-- SECCIÓN DE TOTAL --}}
    <div class="text-right total-section">
        TOTAL: <span class="bold">S/ {{ number_format($order->total, 2) }}</span>
    </div>
    
    {{-- MENSAJE FINAL --}}
    <p class="text-center footer-note">*** Esto no es un comprobante de pago ***</p>
</body>

</html>