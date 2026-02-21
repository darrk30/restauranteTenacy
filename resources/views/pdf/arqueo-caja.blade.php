<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Arqueo de Caja A4</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 0;}
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #1f2937; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; }
        .info-box { background-color: #f3f4f6; padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; margin-bottom: 20px; }
        .section-title { font-size: 12px; font-weight: bold; margin-bottom: 8px; border-bottom: 1px solid #000; padding-bottom: 2px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background-color: #f3f4f6; font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .text-danger { color: #dc2626; }
        .text-success { color: #16a34a; }
        .bg-gray { background-color: #f9fafb; }
        .bg-total { background-color: #1f2937; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $restaurant }}</h1>
        <h2>INFORME DE ARQUEO Y AUDITORÍA DE CAJA</h2>
    </div>

    <div class="info-box">
        <table style="width: 100%; border: none; margin: 0;">
            <tr style="border: none;">
                <td style="border: none;"><strong>SESIÓN:</strong> {{ $caja->session_code }}</td>
                <td style="border: none;"><strong>CAJERO:</strong> {{ $caja->user->name }}</td>
                <td style="border: none;"><strong>ESTADO:</strong> {{ strtoupper($caja->status) }}</td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><strong>APERTURA:</strong> {{ $caja->opened_at->format('d/m/Y H:i') }}</td>
                <td style="border: none;"><strong>CIERRE:</strong> {{ $caja->closed_at ? $caja->closed_at->format('d/m/Y H:i') : 'EN CURSO' }}</td>
                <td style="border: none;"><strong>CAJA:</strong> {{ $caja->cashRegister->name }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">1. AUDITORÍA DE VENTAS Y COBROS POR MÉTODO</div>
    <table>
        <thead>
            <tr>
                <th>MÉTODO DE PAGO</th>
                <th class="text-center">OPER.</th>
                <th class="text-right">SISTEMA (S/)</th>
                <th class="text-right">CAJERO (S/)</th>
                <th class="text-right">DIFERENCIA (S/)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumen_metodos as $nombre => $data)
            <tr>
                <td class="font-bold">{{ $nombre }}</td>
                <td class="text-center">{{ $data['qty'] }}</td>
                <td class="text-right">{{ number_format($data['sistema'], 2) }}</td>
                <td class="text-right">{{ number_format($data['cajero'], 2) }}</td>
                <td class="text-right font-bold {{ $data['diferencia'] < 0 ? 'text-danger' : ($data['diferencia'] > 0 ? 'text-success' : '') }}">
                    {{ number_format($data['diferencia'], 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-total">
                <td colspan="2" class="font-bold">TOTALES CONSOLIDADOS</td>
                <td class="text-right font-bold">{{ number_format($total_sistema, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_cajero, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($total_cajero - $total_sistema, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">2. OPERACIONES DE CONTROL Y ANULACIONES</div>
    <table style="width: 100%;">
        <thead>
            <tr>
                <th width="70%">TIPO DE OPERACIÓN</th>
                <th width="10%" class="text-center">CANT.</th>
                <th width="20%" class="text-right">IMPORTE (S/)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>ANULACIONES DE VENTAS / DEVOLUCIONES</td>
                <td class="text-center">{{ $anulaciones_qty }}</td>
                <td class="text-right text-danger font-bold">{{ number_format($anulaciones_total, 2) }}</td>
            </tr>
            </tbody>
    </table>

    <div class="section-title">3. DESGLOSE TÉCNICO DE EFECTIVO FÍSICO</div>
    <table style="width: 50%;">
        <tr>
            <td>Apertura Inicial</td>
            <td class="text-right">{{ number_format($efectivo_apertura, 2) }}</td>
        </tr>
        <tr>
            <td>Ventas Efectivo</td>
            <td class="text-right">+ {{ number_format($efectivo_ventas, 2) }}</td>
        </tr>
        <tr>
            <td>Entradas Extra</td>
            <td class="text-right">+ {{ number_format($efectivo_entradas, 2) }}</td>
        </tr>
        <tr>
            <td>Salidas/Gastos</td>
            <td class="text-right">- {{ number_format($efectivo_salidas, 2) }}</td>
        </tr>
        <tr class="bg-gray font-bold">
            <td>EFECTIVO ESPERADO (SISTEMA)</td>
            <td class="text-right">S/ {{ number_format($efectivo_esperado, 2) }}</td>
        </tr>
    </table>

    <div style="width: 100%; margin-top: 10px;">
        <div style="width: 48%; display: inline-block; vertical-align: top;">
            <div class="section-title" style="font-size: 10px; border-bottom: 1px dashed #000;">DETALLE ENTRADAS (EFECTIVO)</div>
            <table style="font-size: 10px;">
                @forelse($entradas_efectivo_detalle as $motivo => $monto)
                <tr><td>{{ $motivo }}</td><td class="text-right">{{ number_format($monto, 2) }}</td></tr>
                @empty
                <tr><td colspan="2" class="text-center" style="color: #999;">Sin entradas</td></tr>
                @endforelse
            </table>
        </div>
        <div style="width: 48%; display: inline-block; float: right; vertical-align: top;">
            <div class="section-title" style="font-size: 10px; border-bottom: 1px dashed #000;">DETALLE SALIDAS (EFECTIVO)</div>
            <table style="font-size: 10px;">
                @forelse($salidas_efectivo_detalle as $motivo => $monto)
                <tr><td>{{ $motivo }}</td><td class="text-right">{{ number_format($monto, 2) }}</td></tr>
                @empty
                <tr><td colspan="2" class="text-center" style="color: #999;">Sin salidas</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    <div style="margin-top: 50px;">
        <table style="width: 100%; border: none;">
            <tr style="border: none;">
                <td style="border: none; text-align: center; width: 45%;">
                    <div style="border-top: 1px solid #000; padding-top: 5px;">
                        ENTREGADO POR: {{ strtoupper($caja->user->name) }}<br>DNI: ___________
                    </div>
                </td>
                <td style="border: none; width: 10%;"></td>
                <td style="border: none; text-align: center; width: 45%;">
                    <div style="border-top: 1px solid #000; padding-top: 5px;">
                        RECIBIDO POR: ADMINISTRACIÓN<br>FECHA: {{ now()->format('d/m/Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div style="position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8px; color: #999;">
        Generado por {{ $usuario_impresion }} - ID Sesión: {{ $caja->session_code }} - Página 1 de 1
    </div>
</body>
</html>