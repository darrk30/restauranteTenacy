{{-- INICIO DISEÑO TICKET CARD --}}
@php
    use App\Enums\statusPedido;

    $stLogistico = $order->status_llevar_delivery ?? 'preparando';

    // 1. OBTENCIÓN SEGURA DEL ESTADO DE PAGO
    // Si hiciste el paso 1, $order->status es el Enum. Si no, es un string.
    $statusEnum = $order->status;
    $stPagoValue = $statusEnum instanceof statusPedido ? $statusEnum->value : $statusEnum;

    // Variables por defecto
    $colorBarra = '#eab308'; // Amarillo
    $textoBtn = '...';
    $claseBtn = 'btn-primary';
    $badgeLogistico = 'badge-yellow';

    // Rutas y Acciones
    $accionBoton = "avanzarEstado({$order->id})";
    $redireccionPago = "window.location='/app/{$tenant->slug}/pedidos/{$order->id}/pagar'";

    // 2. CONFIGURACIÓN VISUAL (LOGÍSTICA)
    if ($stLogistico === 'preparando') {
        $colorBarra = '#eab308';
        $badgeLogistico = 'badge-yellow';

        if ($canal === 'delivery') {
            $textoBtn = 'Enviar Moto 🛵';
            $claseBtn = 'btn-info';
        } else {
            $textoBtn = 'Entregar Pedido ✅';
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
        // Usamos statusPedido::Pagado->value para comparar seguramente
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
@endphp

{{-- ESTILO CSS ADICIONAL PARA BADGE ROJO --}}
<style>
    .badge-red {
        background: #fee2e2;
        color: #991b1b;
    }
</style>

<div class="ticket-card" wire:key="card-{{ $order->id }}">
    {{-- Barra Superior de Color --}}
    <div class="ticket-status-bar" style="background-color: {{ $colorBarra }}"></div>

    {{-- Cabecera --}}
    <div class="ticket-header" @click="window.location='/app/{{ $tenant->slug }}/orden-mesa/nuevo/{{ $order->id }}'"
        style="cursor: pointer;">
        <div>
            <span class="text-[10px] text-gray-400 font-bold block uppercase tracking-wide">Orden</span>
            <span class="text-xl font-black text-gray-800 dark:text-white">#{{ $order->code }}</span>
        </div>
        <div class="text-right">
            <span class="text-[10px] text-gray-400 font-bold block uppercase tracking-wide">Total</span>
            <span class="text-lg font-bold text-gray-800 dark:text-white">S/
                {{ number_format($order->total, 2) }}</span>
        </div>
    </div>

    {{-- Cuerpo --}}
    <div class="ticket-body" @click="window.location='/app/{{ $tenant->slug }}/orden-mesa/nuevo/{{ $order->id }}'"
        style="cursor: pointer;">

        <div>
            <div class="flex items-center gap-1 mb-1">
                <x-heroicon-m-user class="w-3 h-3 text-gray-400" />
                <span class="text-[10px] font-bold text-gray-500 uppercase">Cliente</span>
            </div>
            <p class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate">
                {{ $order->nombre_cliente ?? 'Cliente General' }}
            </p>
            @if ($canal === 'delivery' && $order->nombre_delivery)
                <p class="text-[10px] text-gray-400 mt-1">Rep: {{ $order->nombre_delivery }}</p>
            @endif
        </div>

        {{-- Badges de Estado --}}
        <div class="flex flex-wrap gap-2 mt-auto">
            {{-- Badge Pago: Usa el Label del Enum (ej: "Pagado", "Pendiente") --}}
            <span class="badge-pill {{ $claseBadgePago }}">
                {{ $statusEnum instanceof statusPedido ? $statusEnum->getLabel() : ucfirst($stPagoValue) }}
            </span>

            {{-- Badge Logístico --}}
            <span class="badge-pill {{ $badgeLogistico }}">
                {{ strtoupper($stLogistico) }}
            </span>
        </div>
    </div>

    {{-- Footer --}}
    <div class="ticket-footer">
        {{-- Botón Ojo: DISPARA EVENTO ALPINE INSTANTÁNEO --}}
        <button @click="$dispatch('open-detail-modal', { id: {{ $order->id }}, code: '{{ $order->code }}' })"
            class="btn-action btn-eye" title="Ver Detalles">
            <x-heroicon-o-eye class="w-5 h-5" />
        </button>

        {{-- Botón Estado (Se mantiene con Livewire porque ejecuta lógica) --}}
        @if ($stLogistico === 'entregado' && $stPagoValue !== statusPedido::Pagado->value)
            <button onclick="{{ $accionBoton }}" class="btn-action {{ $claseBtn }}">
                {{ $textoBtn }}
            </button>
        @else
            <button wire:click.stop="avanzarEstado({{ $order->id }})" class="btn-action {{ $claseBtn }}">
                <span wire:loading.remove wire:target="avanzarEstado({{ $order->id }})">{{ $textoBtn }}</span>
                <x-filament::loading-indicator wire:loading wire:target="avanzarEstado({{ $order->id }})"
                    class="h-4 w-4 text-white" />
            </button>
        @endif
    </div>
</div>
