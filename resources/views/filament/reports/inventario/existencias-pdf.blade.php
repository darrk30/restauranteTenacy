<!DOCTYPE html>
<html>

<head>
    <title>Reporte de Almacén</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f3f4f6;
            padding: 6px;
            border: 1px solid #d1d5db;
            text-align: left;
        }

        td {
            padding: 6px;
            border: 1px solid #e5e7eb;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Reporte de Existencias - {{ $tenant }}</h1>
        {{-- Usamos la variable $prodType que pasamos desde el controlador --}}
        @if ($prodType)
            <p>Filtrado por: <strong>{{ $prodType }}</strong></p>
        @endif
        <p>Fecha: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $col)
                    <th>{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $variant)
                <tr>
                    @foreach ($columns as $col)
                        <td
                            class="{{ str_contains($col['name'], 'stock') || str_contains($col['name'], 'valor') ? 'text-right' : '' }}">
                            {{ data_get($variant, $col['name']) }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
