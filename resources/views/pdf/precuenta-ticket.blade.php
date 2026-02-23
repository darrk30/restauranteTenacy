<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            width: 80mm;
            margin: 0;
            padding: 10px;
        }

        .text-center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="text-center">
        <h2 style="margin:0;">{{ $order->restaurant->name ?? 'Restaurante' }}</h2>
        <p style="margin:0;">RUC: {{ $order->restaurant->ruc ?? '-----------' }}</p>
        <h3 style="text-decoration: underline; margin: 10px 0;">PRE-CUENTA</h3>
    </div>

    <div>
        <p style="margin:0;">ORDEN: #{{ $order->id }}</p>

        {{-- 🟢 MOSTRAR EL TIPO DE CANAL --}}
        <p style="margin:0;">TIPO: {{ strtoupper($order->canal ?? 'SALÓN') }}</p>

        {{-- 🟢 MOSTRAR MESA Y PISO SOLO SI ES SALÓN --}}
        @if (strtolower($order->canal ?? 'salon') === 'salon')
            {{-- Cambiamos ->nombre por ->name --}}
            <p style="margin:0;">MESA: {{ $order->table->name ?? '---' }}</p>

            @if ($order->table && $order->table->floor)
                {{-- Cambiamos ->nombre por ->name --}}
                <p style="margin:0;">PISO: {{ $order->table->floor->name ?? '---' }}</p>
            @endif
        @endif

        <p style="margin:0;">MOZO: {{ $order->user->name ?? '---' }}</p>
        <p style="margin:0;">FECHA: {{ now()->format('d/m/Y H:i') }}</p>
        <div class="divider"></div>
        <p style="margin:0;">CLIENTE: PUBLICO VARIOS</p>
        <p style="margin:0;">DNI: 00000000</p>
    </div>

    <div class="divider"></div>
    <table>
        <thead>
            <tr>
                <th align="left">CANT</th>
                <th align="left">DESCRIPCIÓN</th>
                <th align="right">P.U.</th>
                <th align="right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->details as $item)
                @if ($item->status !== \App\Enums\statusPedido::Cancelado)
                    <tr>
                        <td>{{ (int) $item->cantidad }}</td>
                        <td>{{ substr($item->product_name, 0, 15) }}</td>
                        <td align="right">{{ number_format($item->price, 2) }}</td>
                        <td align="right">{{ number_format($item->subTotal, 2) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    <div class="divider"></div>
    <div class="text-right">
        <p style="font-size: 16px;">TOTAL: <span class="bold">S/
                {{ number_format($order->details->where('status', '!=', \App\Enums\statusPedido::Cancelado)->sum('subTotal'), 2) }}</span>
        </p>
    </div>
    <p class="text-center" style="margin-top: 10px;">*** Esto no es un comprobante de pago ***</p>
</body>

</html>
