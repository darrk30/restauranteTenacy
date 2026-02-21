<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket Arqueo</title>
    <style>
        @page { margin: 5px; }
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            font-size: 10px; 
            margin: 0; 
            padding: 0; 
            color: #000; 
            text-transform: uppercase; 
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        
        /* Cabecera */
        .header-title { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
        .info-row { margin-bottom: 2px; font-size: 9px; }
        
        /* Secciones */
        .section-title {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            padding: 3px 0;
            margin: 6px 0;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        table { width: 100%; border-collapse: collapse; margin-bottom: 4px;}
        td { padding: 2px 0; vertical-align: top; }
        
        /* Estilo de tabla de auditoría (más compacta) */
        .audit-table th { font-size: 8px; border-bottom: 1px dashed #000; text-align: right; }
        .audit-table th:first-child { text-align: left; }

        /* Filas de totales resaltadas */
        .total-row td { 
            font-weight: bold; 
            font-size: 11px; 
            border-top: 1px dashed #000; 
            padding-top: 4px;
        }

        .footer-info { font-size: 8px; margin-top: 10px; border-top: 1px solid #ccc; padding-top: 5px; }
        
        /* Firma */
        .signature-box { margin-top: 30px; text-align: center; }
        .signature-line { border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 3px; font-weight: bold;}
    </style>
</head>
<body>
    <div class="text-center">
        <div class="header-title">ARQUEO DE CAJA</div>
        <div style="font-size: 9px; margin-bottom: 5px;">{{ $restaurant }}</div>
    </div>
    
    <div class="info-row"><span class="bold">SESIÓN:</span> #{{ $caja->session_code }}</div>
    <div class="info-row"><span class="bold">CAJERO:</span> {{ $caja->user->name ?? 'N/A' }}</div>
    <div class="info-row"><span class="bold">ESTADO:</span> {{ $caja->status == 'open' ? 'ABIERTO' : 'CERRADO' }}</div>
    <div class="info-row"><span class="bold">APERTURA:</span> {{ \Carbon\Carbon::parse($caja->opened_at)->format('d/m/y H:i') }}</div>
    <div class="info-row"><span class="bold">CIERRE:</span> {{ $caja->closed_at ? \Carbon\Carbon::parse($caja->closed_at)->format('d/m/y H:i') : 'EN CURSO' }}</div>

    <div class="section-title">AUDITORÍA DE PAGOS</div>
    <table class="audit-table">
        <thead>
            <tr>
                <th style="width: 40%;">MÉTODO</th>
                <th style="width: 30%;">SIST.</th>
                <th style="width: 30%;">CAJA</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumen_metodos as $nombre => $data)
            <tr>
                <td class="bold">{{ Str::limit($nombre, 10) }}</td>
                <td class="text-right">{{ number_format($data['sistema'], 2) }}</td>
                <td class="text-right">{{ number_format($data['cajero'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td>TOTAL</td>
                <td class="text-right">{{ number_format($total_sistema, 2) }}</td>
                <td class="text-right">{{ number_format($total_cajero, 2) }}</td>
            </tr>
        </tbody>
    </table>
    <div class="text-right bold" style="margin-top: 2px;">
        DIFERENCIA TOTAL: S/ {{ number_format($total_cajero - $total_sistema, 2) }}
    </div>

    <div class="section-title">FLUJO DE EFECTIVO</div>
    <table>
        <tr><td>APERTURA</td><td class="text-right">{{ number_format($efectivo_apertura, 2) }}</td></tr>
        <tr><td>VENTAS EFECT.</td><td class="text-right">+{{ number_format($efectivo_ventas, 2) }}</td></tr>
        <tr><td>INGRESOS EXT.</td><td class="text-right">+{{ number_format($efectivo_entradas, 2) }}</td></tr>
        <tr><td>EGRESOS/GASTOS</td><td class="text-right">-{{ number_format($efectivo_salidas, 2) }}</td></tr>
        <tr class="total-row">
            <td>EFECT. ESPERADO</td>
            <td class="text-right">S/ {{ number_format($efectivo_esperado, 2) }}</td>
        </tr>
    </table>

    @if(count($entradas_efectivo_detalle) > 0 || count($salidas_efectivo_detalle) > 0)
        <div class="section-title">MOVIMIENTOS MANUALES</div>
        <table>
            @foreach($entradas_efectivo_detalle as $motivo => $monto)
            <tr><td>[ING] {{ Str::limit($motivo, 15) }}</td><td class="text-right">{{ number_format($monto, 2) }}</td></tr>
            @endforeach
            @foreach($salidas_efectivo_detalle as $motivo => $monto)
            <tr><td>[EGR] {{ Str::limit($motivo, 15) }}</td><td class="text-right">{{ number_format($monto, 2) }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="section-title">CONTROL Y ANULACIONES</div>
    <table>
        <tr>
            <td>ANULACIONES ({{ $anulaciones_qty }})</td>
            <td class="text-right bold">{{ number_format($anulaciones_total, 2) }}</td>
        </tr>
    </table>

    <div class="footer-info">
        <div>USUARIO IMP: {{ $usuario_impresion }}</div>
        <div>FECHA IMP: {{ $fecha_impresion }}</div>
    </div>

    <div class="signature-box">
        <div class="signature-line">{{ $caja->user->name ?? 'CAJERO' }}</div>
    </div>
    
    <div class="text-center" style="margin-top: 10px; font-size: 8px;">
        *** FIN DEL REPORTE ***
    </div>
</body>
</html>