<?php

namespace App\Services;

use App\Events\PrintPreCuentasJob; // 👈 Tu nuevo Job/Evento para Sockets
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;

class PrecuentaPrintService
{
    // =========================================================================
    // PUNTO DE ENTRADA ÚNICO PARA PRE-CUENTAS
    // =========================================================================
    public function enviarPrecuentaDirecta(int $orderId): void
    {
        $tenant = Filament::getTenant();
        $config = $tenant->cached_config ?? $tenant;

        // PASO 1 — Cargar la orden con las relaciones necesarias
        // (Excluimos los detalles cancelados directamente en la consulta DB)
        $order = Order::with([
            'details' => function ($query) {
                $query->where('status', '!=', \App\Enums\StatusPedido::Cancelado);
            },
            'table.floor',
            'table.floor.printer',
            'user',
            'restaurant'
        ])->findOrFail($orderId);

        // Si no hay detalles para cobrar, no imprimimos nada
        if ($order->details->isEmpty()) {
            return;
        }

        // PASO 2 — Generar el PDF en Base64
        $base64 = $this->generarBase64($order);

        // PASO 3 — Construir el Payload exacto que espera tu app.js en el Monitor
        $payload = [
            'tipo'         => 'precuenta', // Importante para que el JS sepa en qué pestaña ponerlo
            'numero_orden' => $order->code ?? $order->id,
            'mozo'         => $order->user?->name ?? 'Sistema',
            'hora'         => now()->format('H:i'),
            'mesa'         => $this->resolverMesa($order),
            'piso'         => $order->table?->floor?->name ?? 'General',
            'total'        => $order->total,
            'printer_name' => $order->table?->floor?->printer?->name ?? 'CAJA',
            'pdf_base64'   => $base64,
            'api_token'    => $config->api_token ?? $tenant->id,
        ];
        // PASO 4 — Despachar el evento por Reverb/Pusher
        event(new PrintPreCuentasJob($payload));
    }

    // =========================================================================
    // RESOLVER MESA SEGÚN CANAL
    // =========================================================================
    private function resolverMesa(Order $order): string
    {
        return match (strtolower($order->canal)) {
            'salon'    => 'Mesa ' . ($order->table?->name ?? '?'),
            'llevar'   => 'Para Llevar - ' . ($order->nombre_cliente ?? 'Cliente'),
            'delivery' => 'Delivery - ' . ($order->nombre_cliente ?? 'Cliente')
                . ($order->nombre_delivery ? ' | Rep: ' . $order->nombre_delivery : ''),
            default    => 'Sin mesa',
        };
    }

    // =========================================================================
    // GENERAR PDF EN BASE64
    // Usa tu vista pdf.precuenta-ticket
    // =========================================================================
    private function generarBase64(Order $order): string
    {
        $pdf = Pdf::loadView('pdf.precuenta-ticket', [
            'order' => $order
        ])
        ->setPaper([0, 0, 226.77, 600], 'portrait') // 80mm de ancho en puntos
        ->setOptions([
            'dpi'                  => 96,
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'monospace',
        ]);

        return base64_encode($pdf->output());
    }
}