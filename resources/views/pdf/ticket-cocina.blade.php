<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comanda Cocina</title>
    <style>
        /* CONFIGURACIÓN DE PÁGINA PARA DOMPDF */
        @page {
            margin: 0px; 
        }

        body {
            font-family: 'Courier New', Courier, monospace; /* Fuente tipo máquina de escribir */
            font-size: 13px; /* Tamaño legible */
            line-height: 1.2;
            margin: 5px; /* Pequeño margen interno de seguridad */
            color: #000;
        }

        /* CABECERA */
        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .ticket-number {
            font-size: 14px;
            font-weight: bold;
        }

        /* INFO (Mesa, Mozo, Hora) */
        .info {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .info div {
            margin-bottom: 2px;
        }

        /* TÍTULOS DE SECCIÓN (PEDIDO / CANCELADO) */
        .seccion-titulo {
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            border-bottom: 2px solid #000; /* Línea gruesa para separar */
            margin-top: 15px;
            margin-bottom: 5px;
            padding-bottom: 2px;
        }

        /* TABLA DE ITEMS */
        .items {
            width: 100%;
            border-collapse: collapse;
        }

        .items td {
            vertical-align: top;
            padding-top: 4px;
            padding-bottom: 4px;
        }

        /* COLUMNA CANTIDAD */
        .qty {
            width: 15%; /* Espacio fijo para la cantidad */
            font-weight: bold;
            font-size: 14px;
            text-align: left;
        }

        /* NOTAS */
        .note {
            font-size: 11px;
            font-weight: bold; /* Nota en negrita para que el cocinero no la pierda */
            font-style: italic;
            display: block;
            margin-top: 2px;
        }

        /* ESTILOS ESPECÍFICOS PARA CANCELADOS */
        .seccion-cancelado {
            border-bottom: 2px solid #000;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 14px;
        }

        .item-cancelado {
            text-decoration: line-through; /* Tachado */
            font-weight: bold;
        }

        /* FOOTER */
        .footer {
            text-align: center;
            margin-top: 20px;
            border-top: 1px dashed #000;
            padding-top: 10px;
            font-size: 11px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    {{-- CABECERA --}}
    <div class="header">
        <div class="title">
            @if ($esParcial)
                COMANDA CAMBIOS
            @else
                COMANDA COCINA
            @endif
        </div>
        <div class="ticket-number">#{{ $order->code }}</div>
    </div>

    {{-- INFO --}}
    <div class="info">
        <div>MESA: <strong>{{ $order->table->name ?? 'BARRA' }}</strong></div>
        <div>MOZO: {{ $order->user->name ?? 'Gral' }}</div>
        <div>HORA: {{ date('H:i') }} | {{ date('d/m') }}</div>
    </div>

    {{-- BLOQUE 1: AGREGADOS (NUEVOS) --}}
    @if (!empty($itemsParaImprimir['nuevos']))
        <div class="seccion-titulo">>> PEDIDO</div>
        <table class="items">
            <tbody>
                @foreach ($itemsParaImprimir['nuevos'] as $item)
                    <tr>
                        <td class="qty">+{{ $item['cant'] }}</td>
                        <td>
                            <span style="font-size: 14px; font-weight: bold;">
                                {{ $item['nombre'] }}
                            </span>
                            @if (!empty($item['nota']))
                                <span class="note">** NOTA: {{ $item['nota'] }} **</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- BLOQUE 2: CANCELADOS / REDUCIDOS --}}
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

    <div class="footer">*** FIN ORDEN ***</div>
</body>
</html>
