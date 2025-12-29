<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <style>
        @page {
            margin: 4px;
        }

        body {
            font-family: monospace;
            font-size: 11px;
            margin: 0;
            padding: 0;
            line-height: 1.25;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .line {
            border-bottom: 1px dashed #000;
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 2px 0;
            vertical-align: top;
        }

        small {
            font-size: 10px;
        }

        /* 🚫 Evitar saltos de página */
        tr, td {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

    <div class="center bold">
        COMANDA
    </div>

    <div class="center">
        Mesa {{ $order->table->name ?? $order->table_id }}<br>
        Pedido {{ $order->code }}<br>
        {{ now('America/Lima')->format('d/m/Y H:i') }}
    </div>

    <div class="line"></div>

    <table>
        @foreach ($order->details as $item)
            <tr>
                <td width="18%">
                    x{{ $item->cantidad }}
                </td>
                <td width="82%">
                    {{ $item->product->name }}

                    @if ($item->variant && $item->variant->values->isNotEmpty())
                        <br>
                        <small>
                            {{ $item->variant->values
                                ->map(fn($v) => $v->attribute->name . ': ' . $v->name)
                                ->implode(' / ') }}
                        </small>
                    @endif

                    @if ($item->notes)
                        <br>
                        <small><strong>NOTA:</strong> {{ $item->notes }}</small>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <div class="line"></div>

    <div class="center">
        {{ $order->user->name ?? '' }}
    </div>

</body>
</html>
