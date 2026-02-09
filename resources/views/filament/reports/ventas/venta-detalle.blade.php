@php
    $record = $getRecord(); 
@endphp

<style>
    .vistazzo-container { font-family: 'Inter', sans-serif; color: #1f2937; line-height: 1.5; }
    
    /* Header Responsivo */
    .header-p { 
        display: flex; 
        flex-direction: row; 
        justify-content: space-between; 
        border-bottom: 2px solid #f3f4f6; 
        padding-bottom: 1rem; 
        margin-bottom: 1.5rem; 
    }
    
    .title-v { font-size: 1.25rem; font-weight: 800; text-transform: uppercase; color: #111827; margin: 0; }
    .subtitle-v { color: #6b7280; font-size: 0.875rem; }

    /* Contenedor de tabla con scroll horizontal en móviles */
    .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 1.5rem; }
    
    .table-v { width: 100%; border-collapse: collapse; min-width: 500px; /* Asegura que no se colapse en móviles */ }
    .table-v th { background: #f9fafb; color: #4b5563; text-transform: uppercase; font-size: 0.7rem; padding: 0.75rem; border-bottom: 1px solid #e5e7eb; }
    .table-v td { padding: 0.75rem; border-bottom: 1px solid #f3f4f6; font-size: 0.875rem; }

    /* Grid Responsivo para Pagos y Totales */
    .grid-v { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 1.5rem; 
    }

    .payment-box { background: #eff6ff; padding: 1rem; border-radius: 0.75rem; border: 1px solid #dbeafe; }
    .payment-title { font-weight: 700; color: #1e40af; margin-bottom: 0.75rem; border-bottom: 1px solid #bfdbfe; padding-bottom: 0.25rem; font-size: 0.85rem; }
    
    .method-tag { background: #ffffff; padding: 0.2rem 0.5rem; border-radius: 0.375rem; border: 1px solid #bfdbfe; color: #1d4ed8; font-weight: 700; font-size: 0.65rem; text-transform: uppercase; }
    
    .summary-item { display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: #4b5563; font-size: 0.875rem; }
    .total-row { display: flex; justify-content: space-between; border-top: 2px solid #374151; padding-top: 0.75rem; margin-top: 0.75rem; font-weight: 900; font-size: 1.1rem; color: #111827; }
    
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-primary { color: #2563eb; }

    /* MEDIA QUERIES PARA MÓVILES */
    @media (max-width: 640px) {
        .header-p { flex-direction: column; text-align: center; gap: 1rem; }
        .header-p .text-right { text-align: center; }
        
        /* Convertimos el grid de 2 columnas en 1 sola columna */
        .grid-v { grid-template-columns: 1fr; }
        
        .title-v { font-size: 1.1rem; }
        .total-row { font-size: 1.25rem; }
    }
</style>

<div class="vistazzo-container">
    <div class="header-p">
        <div>
            <h2 class="title-v">Detalle de Venta</h2>
            <p class="subtitle-v">{{ $record->serie }}-{{ $record->correlativo }}</p>
        </div>
        <div class="text-right">
            <p style="font-weight: 600; margin-bottom: 0; font-size: 0.8rem;">FECHA DE EMISIÓN</p>
            <p class="subtitle-v">{{ $record->fecha_emision->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-v">
            <thead>
                <tr>
                    <th style="text-align: left; width: 40%;">Producto</th>
                    <th class="text-center" style="width: 15%;">Cant.</th>
                    <th class="text-right" style="width: 22%;">Precio</th>
                    <th class="text-right" style="width: 23%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($record->details as $item)
                    <tr style="background-color: {{ $loop->even ? '#fcfcfc' : 'transparent' }};">
                        <td style="font-weight: 500;">{{ $item->product_name }}</td>
                        <td class="text-center">{{ $item->cantidad }}</td>
                        <td class="text-right">S/ {{ number_format($item->precio_unitario, 2) }}</td>
                        <td class="text-right" style="font-weight: 700; color: #111827;">S/ {{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="grid-v">
        <div class="payment-box">
            <div class="payment-title">MÉTODOS DE PAGO</div>
            @foreach ($record->movements as $mov)
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                    <span class="method-tag">{{ $mov->paymentMethod->name }}</span>
                    <span style="font-weight: 700; color: #374151; font-size: 0.9rem;">S/ {{ number_format($mov->monto, 2) }}</span>
                </div>
                @if ($mov->observacion)
                    <p style="font-size: 0.7rem; color: #6b7280; font-style: italic; margin-top: -0.4rem; margin-bottom: 0.6rem; padding-left: 0.2rem;">
                        — {{ $mov->observacion }}
                    </p>
                @endif
            @endforeach
        </div>

        <div style="padding: 0.5rem;">
            <div class="summary-item">
                <span>Op. Gravada:</span>
                <span style="font-weight: 500;">S/ {{ number_format($record->op_gravada, 2) }}</span>
            </div>
            <div class="summary-item">
                <span>IGV (18%):</span>
                <span style="font-weight: 500;">S/ {{ number_format($record->monto_igv, 2) }}</span>
            </div>
            @if ($record->descuento_total > 0)
                <div class="summary-item" style="color: #dc2626;">
                    <span>Descuento:</span>
                    <span style="font-weight: 600;">- S/ {{ number_format($record->descuento_total, 2) }}</span>
                </div>
            @endif
            <div class="total-row">
                <span>TOTAL</span>
                <span class="text-primary">S/ {{ number_format($record->total, 2) }}</span>
            </div>
        </div>
    </div>
</div>