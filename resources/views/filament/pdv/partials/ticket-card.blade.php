{{-- INICIO DISEÑO TICKET CARD --}}
@php
    use App\Enums\statusPedido;

    $stLogistico = $order->status_llevar_delivery ?? 'preparando';

    // 1. OBTENCIÓN SEGURA DEL ESTADO DE PAGO
    $statusEnum = $order->status;
    $stPagoValue = $statusEnum instanceof statusPedido ? $statusEnum->value : $statusEnum;

    // Variables por defecto
    $colorBarra = '#eab308'; // Amarillo
    $textoBtn = '...';
    $claseBtn = 'btn-primary';
    $badgeLogistico = 'badge-yellow';

    // Rutas y Acciones
    $accionBoton = "avanzarEstado({$order->id})";
    $redireccionPago = "window.location='/app/pedidos/{$order->id}/pagar'";

    // 2. CONFIGURACIÓN VISUAL (LOGÍSTICA)
    if ($stLogistico === 'preparando') {
        $colorBarra = '#eab308';
        $badgeLogistico = 'badge-yellow';

        if ($canal === 'delivery') {
            $textoBtn = 'Enviar Moto 🛵';
            $claseBtn = 'btn-info';
        } else {
            $textoBtn = 'Entregar ✅';
            $claseBtn = 'btn-success';
        }
    } elseif ($stLogistico === 'enviado') {
        $colorBarra = '#3b82f6';
        $badgeLogistico = 'badge-blue';
        $textoBtn = 'Confirmar Entrega 🏁';
        $claseBtn = 'btn-success';
    } elseif ($stLogistico === 'entregado') {
        $colorBarra = '#10b981';
        $badgeLogistico = 'badge-green';

        // 3. LÓGICA DE PAGO
        if ($stPagoValue !== statusPedido::Pagado->value) {
            $textoBtn = 'Cobrar 💵';
            $claseBtn = 'btn-primary';
            $accionBoton = $redireccionPago;
        } else {
            $textoBtn = 'Finalizado';
            $claseBtn = 'btn-disabled';
        }
    }

    // 4. COLOR DEL BADGE DE PAGO
    $claseBadgePago = match ($stPagoValue) {
        statusPedido::Pagado->value => 'badge-green',
        statusPedido::Cancelado->value => 'badge-red',
        default => 'badge-gray',
    };

    // 5. LÓGICA INTELIGENTE DE DATOS DEL CLIENTE
    $nombreMostrar = $order->nombre_cliente ?? ($order->client->nombres ?? 'Cliente General');
    $telefonoMostrar = $order->telefono ?? ($order->client->telefono ?? null);
    $direccionMostrar = $order->direccion ?? ($order->client->direccion ?? null);
@endphp

<style>
    .badge-red { background: #fee2e2; color: #991b1b; }
    /* ESTILOS CON FUENTES MÁS GRANDES */
    .compact-client-box { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; }
    .compact-client-name { font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
    .compact-link { font-size: 13px; color: #10b981; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 6px; margin-bottom: 4px; padding: 2px 0; }
    .compact-link-map { color: #475569; }
    .compact-link:hover { opacity: 0.8; }
    .compact-rep { font-size: 13px; color: #3b82f6; font-weight: 600; display: flex; align-items: center; gap: 6px; border-top: 1px dashed #e2e8f0; margin-top: 6px; padding-top: 8px; }
    .compact-item { font-size: 14px; display: flex; gap: 10px; margin-bottom: 6px; color: #334155; font-weight: 500; }
</style>

<div class="ticket-card" wire:key="card-{{ $order->id }}" style="display: flex; flex-direction: column; overflow: hidden; position: relative;">
    {{-- Barra Superior de Color --}}
    <div class="ticket-status-bar" style="background-color: {{ $colorBarra }}; position: absolute; top:0; left:0; width:100%; height:5px;"></div>

    {{-- Cabecera --}}
    <div class="ticket-header" @click="window.location='/app/orden-mesa/nuevo/{{ $order->id }}'" style="cursor: pointer; padding: 16px 16px 10px; border-bottom: 1px dashed #e2e8f0; display: flex; justify-content: space-between; align-items: flex-start; margin-top: 4px;">
        <div style="line-height: 1.2;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <span style="font-size: 18px; font-weight: 900; color: #0f172a;">#{{ $order->code }}</span>
                @if($order->web)
                    <span style="background: #ef4444; color: white; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 800; letter-spacing: 0.5px;">WEB</span>
                @endif
            </div>
            <span style="font-size: 12px; color: #64748b; font-weight: 600;">
                {{ $order->created_at->format('h:i A') }} 
                <span style="color: #ef4444; margin-left:4px;">({{ $order->created_at->diffForHumans(null, true, true) }})</span>
            </span>
        </div>
        <div style="text-align: right; line-height: 1.2;">
            <span style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase;">Total</span>
            <div style="font-size: 18px; font-weight: 900; color: #0f172a;">S/ {{ number_format($order->total, 2) }}</div>
        </div>
    </div>

    {{-- Cuerpo --}}
    <div class="ticket-body" @click="window.location='/app/orden-mesa/nuevo/{{ $order->id }}'" style="cursor: pointer; padding: 12px 16px; flex: 1; display: flex; flex-direction: column;">

        {{-- 🟢 CAJA GRIS DEL CLIENTE --}}
        <div class="compact-client-box">
            <div class="compact-client-name">
                <x-heroicon-m-user style="width: 15px; height: 14px; color: #94a3b8;" />
                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">{{ $nombreMostrar }}</span>
            </div>

            @if($telefonoMostrar)
                <a @click.stop href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $telefonoMostrar) }}" target="_blank" class="compact-link">
                    <svg style="width: 14px; height: 15px;" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.305-.885-.653-1.48-1.459-1.653-1.756-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
                    {{ $telefonoMostrar }}
                </a>
            @endif

            @if($canal === 'delivery' && $direccionMostrar)
                <a @click.stop href="https://maps.google.com/?q={{ urlencode($direccionMostrar) }}" target="_blank" class="compact-link compact-link-map">
                    <x-heroicon-m-map-pin style="width: 15px; height: 14px; color: #ef4444; flex-shrink: 0;" /> 
                    <span style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3;">{{ $direccionMostrar }}</span>
                </a>
            @endif

            @if ($canal === 'delivery' && $order->nombre_delivery)
                <div class="compact-rep">
                    <x-heroicon-m-truck style="width: 14px; height: 14px; flex-shrink: 0;" />
                    Rep: {{ $order->nombre_delivery }}
                </div>
            @endif
        </div>
        {{-- Badges de Estado --}}
        <div style="display: flex; gap: 8px;">
            <span class="badge-pill {{ $claseBadgePago }}" style="font-size: 12px; padding: 4px 8px; border-radius: 6px;">
                {{ $statusEnum instanceof statusPedido ? $statusEnum->getLabel() : ucfirst($stPagoValue) }}
            </span>
            <span class="badge-pill {{ $badgeLogistico }}" style="font-size: 10px; padding: 4px 8px; border-radius: 6px;">
                {{ strtoupper($stLogistico) }}
            </span>
        </div>
    </div>

    {{-- Footer --}}
    <div class="ticket-footer" style="padding: 12px 16px; border-top: 1px solid #f1f5f9; display: flex; gap: 12px; background: #f8fafc;">
        <button @click.stop="$dispatch('open-detail-modal', { id: {{ $order->id }}, code: '{{ $order->code }}' })"
    class="btn-action btn-eye" title="Ver Detalles" style="padding: 5px 5px; flex-shrink: 0; background: #ffffff; border: 1px solid #e2e8f0; color: #475569; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
    
    {{-- 🟢 Agregamos w-6 y h-6 para darle un buen tamaño --}}
    <x-heroicon-o-eye class="w-6 h-6" />
    
</button>

        @if ($stLogistico === 'entregado' && $stPagoValue !== statusPedido::Pagado->value)
            <button onclick="{{ $accionBoton }}" class="btn-action {{ $claseBtn }}" style="flex: 1; padding: 8px; font-size: 13px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                {{ $textoBtn }}
            </button>
        @else
            <button wire:click.stop="avanzarEstado({{ $order->id }})" class="btn-action {{ $claseBtn }}" style="flex: 1; padding: 8px; font-size: 13px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                <span wire:loading.remove wire:target="avanzarEstado({{ $order->id }})">{{ $textoBtn }}</span>
                <x-filament::loading-indicator wire:loading wire:target="avanzarEstado({{ $order->id }})" class="h-5 w-5 text-white" />
            </button>
        @endif
    </div>
</div>