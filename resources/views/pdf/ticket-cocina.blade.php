<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda - {{ $order->code }}</title>
    <style>
        /* === RESET PARA PANTALLA Y PAPEL === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f3f4f6;
            /* Fondo gris para resaltar el ticket en el modal */
            display: flex;
            justify-content: center;
            padding: 20px;
        }

        /* Contenedor del Ticket (Simula el papel de 80mm) */
        .ticket-container {
            width: 80mm;
            background-color: white;
            padding: 10mm 5mm;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            /* Sombra para el modal */
            color: #000;
        }

        /* === ESTILOS DEL CONTENIDO === */
        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .ticket-number {
            font-size: 16px;
            font-weight: bold;
            margin-top: 5px;
        }

        .area-name {
            display: inline-block;
            background: #000;
            color: #fff;
            padding: 2px 8px;
            margin-top: 5px;
            font-weight: bold;
        }

        .info {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 8px 0;
            margin: 10px 0;
            font-size: 13px;
        }

        .seccion-titulo {
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            border-bottom: 2px solid #000;
            margin-top: 15px;
            margin-bottom: 5px;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
        }

        .items td {
            vertical-align: top;
            padding: 6px 0;
        }

        .qty {
            width: 18%;
            font-weight: bold;
            font-size: 15px;
        }

        .product-name {
            font-size: 14px;
            font-weight: bold;
        }

        .note {
            font-size: 11px;
            font-weight: bold;
            font-style: italic;
            display: block;
            margin-top: 2px;
        }

        .item-cancelado {
            text-decoration: line-through;
            font-weight: bold;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            border-top: 1px dashed #000;
            padding-top: 10px;
            font-size: 11px;
            font-weight: bold;
        }

        /* === AJUSTES DE IMPRESIÓN === */
        @media print {
            body {
                background: none;
                padding: 0;
                display: block;
            }

            .ticket-container {
                width: 100%;
                /* La ticketera define el ancho */
                box-shadow: none;
                margin: 0;
                padding: 2mm;
            }

            @page {
                margin: 0;
                size: 80mm auto;
                /* Altura dinámica */
            }
        }
    </style>
</head>

<body>

    <div class="ticket-container">
        {{-- CABECERA --}}
        <div class="header">
            <div class="title">
                {{ $esParcial ? 'COMANDA CAMBIOS' : 'COMANDA COCINA' }}
            </div>
            <div class="area-name">{{ $areaNombre }}</div>
            <div class="ticket-number">ORDEN #{{ $order->code }}</div>
        </div>

        {{-- INFO --}}
        <div class="info">
            <div>MESA: <strong>{{ $order->table->name ?? 'BARRA' }}</strong></div>
            <div>MOZO: {{ $order->user->name ?? 'Gral' }}</div>
            <div>HORA: {{ date('H:i') }} | {{ date('d/m') }}</div>
        </div>

        {{-- BLOQUE 1: AGREGADOS --}}
        @if (!empty($itemsParaImprimir['nuevos']))
            <div class="seccion-titulo">>> PEDIDO</div>
            <table class="items">
                <tbody>
                    @foreach ($itemsParaImprimir['nuevos'] as $item)
                        <tr>
                            <td class="qty">+{{ $item['cant'] }}</td>
                            <td>
                                <span class="product-name">{{ $item['nombre'] }}</span>
                                @if (!empty($item['note'] ?? $item['nota']))
                                    <span class="note">** NOTA: {{ $item['note'] ?? $item['nota'] }} **</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- BLOQUE 2: CANCELADOS --}}
        @if (!empty($itemsParaImprimir['cancelados']))
            <div class="seccion-titulo">XX CANCELAR</div>
            <table class="items">
                <tbody>
                    @foreach ($itemsParaImprimir['cancelados'] as $item)
                        <tr>
                            <td class="qty">-{{ $item['cant'] }}</td>
                            <td class="item-cancelado">
                                {{ $item['nombre'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="footer">*** FIN DE COMANDA ***</div>
    </div>

    <script>
        // Asegura que el iframe esté listo para recibir el foco de impresión
        window.onload = function() {
            window.focus();
        };
    </script>
</body>

</html>
