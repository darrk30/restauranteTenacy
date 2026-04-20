@php
    use App\Enums\StatusPedido;

    $stLogistico = $order->status_llevar_delivery ?? 'preparando';
    $statusEnum = $order->status;
    $stPagoValue = $statusEnum instanceof StatusPedido ? $statusEnum->value : $statusEnum;

    $colorBarra = '#eab308';
    $textoBtn = '...';
    $claseBtnCustom = 'btn-custom-primary';
    $badgeLogisticoClass = 'badge-custom-yellow';

    $accionBoton = "avanzarEstado({$order->id})";
    $redireccionPago = "window.location='/pdv/pedidos/{$order->id}/pagar'";

    if ($stLogistico === 'preparando') {
        $colorBarra = '#eab308';
        $badgeLogisticoClass = 'badge-custom-yellow';

        if ($canal === 'delivery') {
            $textoBtn = 'Enviar Moto 🛵';
            $claseBtnCustom = 'btn-custom-info';
        } else {
            $textoBtn = 'Entregar ✅';
            $claseBtnCustom = 'btn-custom-success';
        }
    } elseif ($stLogistico === 'enviado') {
        $colorBarra = '#3b82f6';
        $badgeLogisticoClass = 'badge-custom-blue';
        $textoBtn = 'Confirmar Entrega 🏁';
        $claseBtnCustom = 'btn-custom-success';
    } elseif ($stLogistico === 'entregado') {
        $colorBarra = '#10b981';
        $badgeLogisticoClass = 'badge-custom-green';

        if ($stPagoValue !== StatusPedido::Pagado->value) {
            $textoBtn = 'Cobrar 💵';
            $claseBtnCustom = 'btn-custom-primary';
            $accionBoton = $redireccionPago;
        } else {
            $textoBtn = 'Finalizado';
            $claseBtnCustom = 'btn-custom-disabled';
        }
    }

    $claseBadgePago = match ($stPagoValue) {
        StatusPedido::Pagado->value => 'badge-custom-green',
        StatusPedido::Cancelado->value => 'badge-custom-red',
        default => 'badge-custom-gray',
    };

    $nombreMostrar = $order->nombre_cliente ?? ($order->client->nombres ?? 'Cliente General');
    $telefonoMostrar = $order->telefono ?? ($order->client->telefono ?? null);
    $direccionMostrar = $order->direccion ?? ($order->client->direccion ?? null);
@endphp

<style>
    /* VARIABLES GLOBALES PARA LIGHT/DARK MODE */
    :root {
        --tc-bg: #ffffff;
        --tc-border: #e2e8f0;
        --tc-text-main: #0f172a;
        --tc-text-muted: #64748b;
        --tc-box-bg: #f8fafc;
        --tc-box-border: #f1f5f9;
        --tc-footer-bg: #f8fafc;
        --tc-btn-view-bg: #ffffff;
        --tc-btn-view-text: #475569;
    }

    .dark {
        --tc-bg: #1e293b;
        --tc-border: #334155;
        --tc-text-main: #f8fafc;
        --tc-text-muted: #94a3b8;
        --tc-box-bg: #0f172a;
        --tc-box-border: #1e293b;
        --tc-footer-bg: #0f172a;
        --tc-btn-view-bg: #334155;
        --tc-btn-view-text: #e2e8f0;
    }

    /* ESTILOS DEL TICKET */
    .ticket-custom-card {
        background: var(--tc-bg);
        border: 1px solid var(--tc-border);
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .ticket-custom-header {
        padding: 16px 16px 12px;
        border-bottom: 1px dashed var(--tc-border);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        cursor: pointer;
    }

    .tc-code {
        font-size: 18px;
        font-weight: 900;
        color: var(--tc-text-main);
    }

    .tc-web-badge {
        background: #ef4444;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 800;
        letter-spacing: 0.5px;
        margin-left: 8px;
        vertical-align: middle;
    }

    .tc-time {
        font-size: 12px;
        color: var(--tc-text-muted);
        font-weight: 600;
        display: block;
        margin-top: 4px;
    }

    .tc-time-diff {
        color: #ef4444;
        margin-left: 4px;
    }

    .tc-total-label {
        font-size: 11px;
        color: var(--tc-text-muted);
        font-weight: 700;
        text-transform: uppercase;
        display: block;
        text-align: right;
    }

    .tc-total-val {
        font-size: 18px;
        font-weight: 900;
        color: var(--tc-text-main);
        text-align: right;
    }

    /* ESTILOS DEL CLIENTE (CAJA GRIS/OSCURA) */
    .ticket-custom-body {
        padding: 12px 16px;
        flex: 1;
        cursor: pointer;
    }

    .tc-client-box {
        background: var(--tc-box-bg);
        border: 1px solid var(--tc-box-border);
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 12px;
    }

    .tc-client-name {
        font-size: 15px;
        font-weight: 700;
        color: var(--tc-text-main);
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
    }

    /* 🟢 CORRECCIÓN CLICKEABLE: display: inline-flex forza el ancho exacto del contenido */
    .tc-link {
        font-size: 13px;
        color: #10b981;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 6px;
        padding: 2px 0;
    }

    .tc-link-map {
        color: #ef4444;
        margin-bottom: 0;
        align-items: flex-start;
    }

    .tc-link-map span {
        color: var(--tc-text-muted);
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .tc-link:hover {
        opacity: 0.8;
    }

    .tc-rep {
        font-size: 13px;
        color: #3b82f6;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
        border-top: 1px dashed var(--tc-border);
        margin-top: 8px;
        padding-top: 8px;
    }

    /* BADGES */
    .tc-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .badge-custom {
        font-size: 11px;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 6px;
        text-transform: uppercase;
    }

    .badge-custom-green {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-custom-red {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-custom-yellow {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-custom-blue {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge-custom-gray {
        background: #f1f5f9;
        color: #475569;
    }

    .dark .badge-custom-green {
        background: rgba(16, 185, 129, 0.2);
        color: #34d399;
    }

    .dark .badge-custom-red {
        background: rgba(239, 68, 68, 0.2);
        color: #f87171;
    }

    .dark .badge-custom-yellow {
        background: rgba(245, 158, 11, 0.2);
        color: #fbbf24;
    }

    .dark .badge-custom-blue {
        background: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
    }

    .dark .badge-custom-gray {
        background: rgba(100, 116, 139, 0.2);
        color: #94a3b8;
    }

    /* FOOTER & BOTONES */
    .ticket-custom-footer {
        padding: 12px 16px;
        border-top: 1px solid var(--tc-border);
        background: var(--tc-footer-bg);
        display: flex;
        gap: 12px;
    }

    .btn-custom-view {
        flex-shrink: 0;
        padding: 8px 10px;
        background: var(--tc-btn-view-bg);
        border: 1px solid var(--tc-border);
        color: var(--tc-btn-view-text);
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
    }

    .btn-custom-view:hover {
        opacity: 0.8;
    }

    .btn-custom-main {
        flex: 1;
        padding: 10px;
        font-size: 13px;
        font-weight: 700;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: white;
        transition: 0.2s;
    }

    .btn-custom-primary {
        background: #4f46e5;
    }

    .btn-custom-success {
        background: #10b981;
    }

    .btn-custom-info {
        background: #0ea5e9;
    }

    .btn-custom-disabled {
        background: #cbd5e1;
        color: #64748b;
        cursor: not-allowed;
    }

    .dark .btn-custom-disabled {
        background: #334155;
        color: #94a3b8;
    }

    .btn-custom-main:hover:not(.btn-custom-disabled) {
        filter: brightness(1.1);
    }
</style>

<div class="ticket-custom-card" wire:key="card-{{ $order->id }}">
    <div style="background-color: {{ $colorBarra }}; position: absolute; top:0; left:0; width:100%; height:5px;"></div>

    <div class="ticket-custom-header" @click="window.location='/pdv/orden-mesa/nuevo/{{ $order->id }}'">
        <div>
            <div>
                <span class="tc-code">#{{ $order->code }}</span>
                @if ($order->web)
                    <span class="tc-web-badge">WEB</span>
                @endif
            </div>
            <span class="tc-time">{{ $order->created_at->format('h:i A') }} <span
                    class="tc-time-diff">({{ $order->created_at->diffForHumans(null, true, true) }})</span></span>
        </div>
        <div>
            <span class="tc-total-label">Total</span>
            <div class="tc-total-val">S/ {{ number_format($order->total, 2) }}</div>
        </div>
    </div>

    <div class="ticket-custom-body" @click="window.location='/pdv/orden-mesa/nuevo/{{ $order->id }}'">
        <div class="tc-client-box">
            <div class="tc-client-name">
                <x-heroicon-m-user style="width: 16px; height: 16px; color: var(--tc-text-muted);" />
                <span
                    style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;">{{ $nombreMostrar }}</span>
            </div>

            @if ($telefonoMostrar)
                <div>
                    <a @click.stop href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $telefonoMostrar) }}"
                        target="_blank" class="tc-link">
                        <svg style="width: 14px; height: 14px;" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.305-.885-.653-1.48-1.459-1.653-1.756-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z" />
                        </svg>
                        {{ $telefonoMostrar }}
                    </a>
                </div>
            @endif

            @if ($canal === 'delivery' && $direccionMostrar)
                <div>
                    @if ($direccionMostrar)
                        <a @click.stop href="{{ \App\Helpers\DireccionHelper::urlMapa($direccionMostrar) }}"
                            target="_blank" class="tc-link tc-link-map">
                            <x-heroicon-m-map-pin style="width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px;" />
                            <span>{{ \App\Helpers\DireccionHelper::texto($direccionMostrar) }}</span>
                        </a>
                    @endif
                </div>
            @endif

            @if ($canal === 'delivery' && $order->nombre_delivery)
                <div class="tc-rep">
                    <x-heroicon-m-truck style="width: 16px; height: 16px; flex-shrink: 0;" />
                    Rep: {{ $order->nombre_delivery }}
                </div>
            @endif
        </div>

        <div class="tc-badges">
            <span
                class="badge-custom {{ $claseBadgePago }}">{{ $statusEnum instanceof StatusPedido ? $statusEnum->getLabel() : ucfirst($stPagoValue) }}</span>
            <span class="badge-custom {{ $badgeLogisticoClass }}">{{ strtoupper($stLogistico) }}</span>
        </div>
    </div>

    <div class="ticket-custom-footer">
        <button @click.stop="$dispatch('open-detail-modal', { id: {{ $order->id }}, code: '{{ $order->code }}' })"
            class="btn-custom-view" title="Ver Detalles">
            <x-heroicon-o-eye style="width: 24px; height: 24px;" />
        </button>

        @if ($stLogistico === 'entregado' && $stPagoValue !== StatusPedido::Pagado->value)
            <button onclick="{{ $accionBoton }}" class="btn-custom-main {{ $claseBtnCustom }}">
                {{ $textoBtn }}
            </button>
        @else
            <button wire:click.stop="avanzarEstado({{ $order->id }})"
                class="btn-custom-main {{ $claseBtnCustom }}">
                <span wire:loading.remove wire:target="avanzarEstado({{ $order->id }})">{{ $textoBtn }}</span>
                <x-filament::loading-indicator wire:loading wire:target="avanzarEstado({{ $order->id }})"
                    style="width: 20px; height: 20px;" />
            </button>
        @endif
    </div>
</div>
