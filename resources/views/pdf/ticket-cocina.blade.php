<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0px; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            margin: 5px;
            text-transform: uppercase;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .header-titulo { 
            font-size: 14px; font-weight: bold; border: 2px solid #000; padding: 5px; margin-bottom: 5px; 
        }
        .line { border-bottom: 1px dashed #000; margin: 5px 0; }
        
        /* ESTADOS */
        .cancelado { text-decoration: line-through; }
        .badge-cancel { background: #000; color: #fff; font-size: 9px; padding: 1px 2px; }
        
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
    </style>
</head>
<body>

    <div class="center header-titulo">
        {{ $titulo }}
    </div>

    <div class="center">
        <span style="font-size: 16px; font-weight: bold;">{{ $meta['mesa'] }}</span><br>
        Mozo: {{ $meta['mozo'] }}<br>
        Ref: {{ $meta['codigo'] }}<br>
        {{ $meta['fecha'] }}
    </div>

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th width="15%" align="center">Cant</th>
                <th width="85%" align="left">Producto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td align="center" style="font-size: 13px; font-weight: bold;">
                        {{ $item['cantidad'] }}
                    </td>
                    <td>
                        <span class="{{ $item['estado'] === 'cancelado' ? 'cancelado' : '' }}">
                            {{ $item['producto'] }}
                        </span>

                        @if (!empty($item['nota']))
                            <br><small style="font-weight:bold;">⚠️ {{ $item['nota'] }}</small>
                        @endif
                    </td>
                </tr>
                <tr><td colspan="2" style="border-bottom: 1px dotted #ccc;"></td></tr>
            @endforeach
        </tbody>
    </table>

    <div class="line"></div>
</body>
</html>