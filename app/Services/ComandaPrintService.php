<?php

namespace App\Services;

use App\Events\PrintComandaJob;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

class ComandaPrintService
{
    // =========================================================================
    // PUNTO DE ENTRADA ÚNICO
    //
    // Casos soportados:
    //   - Orden nueva       → jobId presente, solo 'nuevos' en caché
    //   - Orden actualizada → jobId presente, 'nuevos' + 'cancelados' en caché
    //   - Orden anulada     → jobId presente, solo 'cancelados' en caché
    //   - Reimpresión       → jobId null, se usa la Order completa
    //
    // Uso:
    //   app(ComandaPrintService::class)->enviarComandaDirecta($order->id, $jobId);
    //   app(ComandaPrintService::class)->enviarComandaDirecta($order->id); // reimpresión
    // =========================================================================
    public function enviarComandaDirecta(int $orderId, ?string $jobId = null): void
    {
        $tenant = Filament::getTenant();

        // Obtenemos la configuración
        $config = $tenant->cached_config ?? $tenant;
        // PASO 1 — Cargar la orden con todo lo necesario
        $order = Order::with([
            'table',
            'user',
            'details.product.production.printer',
        ])->findOrFail($orderId);

        // PASO 2 — Resolver ítems agrupados por área
        $itemsPorArea = $this->resolverItemsPorArea($order, $jobId);

        if (empty($itemsPorArea)) {
            return;
        }

        // PASO 3 — Por cada área: generar PDF y despachar evento
        foreach ($itemsPorArea as $areaData) {
            $base64 = $this->generarBase64($order, $areaData);

            $payload = [
                'tipo'         => 'comanda',                        // 👈 identifica el tipo
                'numero_orden' => $order->code,
                'mozo'         => $order->user?->name ?? 'Sistema',
                'hora'         => now()->format('H:i'),
                'mesa'         => $this->resolverMesa($order),
                'area'         => $areaData['nombre'],
                'printer_name' => $areaData['printer_name'] ?? null, // 👈 nombre impresora del área
                'pdf_base64'   => $base64,
                'api_token'    => $tenant->cached_config->api_token ?? $tenant->id,
            ];

            event(new PrintComandaJob($payload));
        }
    }

    // =========================================================================
    // PASO 2: RESOLVER ÍTEMS POR ÁREA
    // Retorna array indexado por areaId:
    // [
    //   'area_1' => [
    //      'nombre'      => 'Cocina',
    //      'nuevos'      => [['cant'=>2, 'nombre'=>'Lomo', 'nota'=>null], ...],
    //      'cancelados'  => [['cant'=>1, 'nombre'=>'Arroz', 'nota'=>null], ...],
    //      'es_parcial'  => true|false,
    //   ]
    // ]
    // =========================================================================
    private function resolverItemsPorArea(Order $order, ?string $jobId): array
    {
        if ($jobId) {
            $datosCache = Cache::get($jobId);

            // Si el caché ya expiró, fallback a la Order completa
            if (!$datosCache) {
                return $this->resolverDesdeOrder($order);
            }

            return $this->resolverDesdeCache($datosCache);
        }

        return $this->resolverDesdeOrder($order);
    }

    // ─── FUENTE A: CACHÉ ─────────────────────────────────────────────────────
    // Estructura del caché que viene de OrdenService / actualizarOrden:
    // [
    //   'nuevos'     => [['cant'=>1,'nombre'=>'X','nota'=>'','area_id'=>1,'area_nombre'=>'Cocina']],
    //   'cancelados' => [['cant'=>1,'nombre'=>'Y','nota'=>'','area_id'=>1,'area_nombre'=>'Cocina']],
    // ]
    // ─────────────────────────────────────────────────────────────────────────
    private function resolverDesdeCache(array $datosCache): array
    {
        $itemsPorArea = [];

        $nuevos     = $datosCache['nuevos']     ?? [];
        $cancelados = $datosCache['cancelados'] ?? [];

        foreach ($nuevos as $item) {
            $areaId = $item['area_id'] ?? 'general';

            $itemsPorArea[$areaId]['nombre']     = $item['area_nombre'] ?? 'GENERAL';
            $itemsPorArea[$areaId]['nuevos'][]   = [
                'cant'   => $item['cant'],
                'nombre' => $item['nombre'],
                'nota'   => $item['nota'] ?? null,
            ];
        }

        foreach ($cancelados as $item) {
            $areaId = $item['area_id'] ?? 'general';

            $itemsPorArea[$areaId]['nombre']        = $item['area_nombre'] ?? 'GENERAL';
            $itemsPorArea[$areaId]['cancelados'][]  = [
                'cant'   => $item['cant'],
                'nombre' => $item['nombre'],
                'nota'   => $item['nota'] ?? null,
            ];
        }

        // Normalizar y calcular es_parcial por área
        // es_parcial = true  → hay cancelados (cambio parcial o anulación)
        // es_parcial = false → solo nuevos (orden nueva limpia)
        foreach ($itemsPorArea as $areaId => &$areaData) {
            $areaData['nuevos']     = $areaData['nuevos']     ?? [];
            $areaData['cancelados'] = $areaData['cancelados'] ?? [];
            $areaData['es_parcial'] = !empty($areaData['cancelados']);

            // 👇 AGREGAR: buscar la impresora del área por su ID
            $areaIdNumerico = is_numeric($areaId) ? $areaId : null;
            $produccion = $areaIdNumerico
                ? \App\Models\Production::with('printer')->find($areaIdNumerico)
                : null;
            $areaData['printer_name'] = $produccion?->printer?->name ?? 'PREDETERMINADA';
        }
        unset($areaData);

        return $itemsPorArea;
    }

    // ─── FUENTE B: ORDER COMPLETA (Reimpresión manual) ───────────────────────
    // Solo detalles activos, agrupados por área de producción.
    // Es_parcial siempre false porque es una reimpresión total.
    // ─────────────────────────────────────────────────────────────────────────
    private function resolverDesdeOrder(Order $order): array
    {
        $itemsPorArea = [];

        foreach ($order->details as $detail) {
            // Ignorar detalles cancelados (compatibilidad con Enum y string)
            $statusVal = $detail->status instanceof \BackedEnum
                ? $detail->status->value
                : $detail->status;

            if ($statusVal === 'cancelado') {
                continue;
            }

            $produccion = $detail->product?->production ?? null;
            $impresora  = $produccion?->printer         ?? null;

            // Solo agrupar en área si tiene producción e impresora activas
            if ($produccion && $produccion->status && $impresora && $impresora->status) {
                $areaId     = 'area_' . $produccion->id;
                $areaNombre = $produccion->name;
            } else {
                $areaId     = 'general';
                $areaNombre = 'GENERAL';
            }

            $itemsPorArea[$areaId]['nombre']     = $areaNombre;
            $itemsPorArea[$areaId]['es_parcial'] = false;
            $itemsPorArea[$areaId]['printer_name']  = $impresora?->name ?? 'PREDETERMINADA';
            $itemsPorArea[$areaId]['cancelados'] = $itemsPorArea[$areaId]['cancelados'] ?? [];
            $itemsPorArea[$areaId]['nuevos'][]   = [
                'cant'   => $detail->cantidad,
                'nombre' => $detail->product_name,
                'nota'   => $detail->notes ?? null,
            ];
        }

        return $itemsPorArea;
    }

    // =========================================================================
    // RESOLVER MESA SEGÚN CANAL
    // La vista ticket-cocina usa $order->table->name directamente para salón,
    // pero para delivery/llevar necesitamos este helper en el payload del evento.
    // =========================================================================
    private function resolverMesa(Order $order): string
    {
        return match ($order->canal) {
            'salon'    => 'Mesa ' . ($order->table?->name ?? '?'),
            'llevar'   => 'Para Llevar - ' . ($order->nombre_cliente ?? 'Cliente'),
            'delivery' => 'Delivery - ' . ($order->nombre_cliente ?? 'Cliente')
                . ($order->nombre_delivery ? ' | Rep: ' . $order->nombre_delivery : ''),
            default    => 'Sin mesa',
        };
    }

    // =========================================================================
    // GENERAR PDF EN BASE64
    // Usa exactamente la vista pdf.ticket-cocina con sus 4 variables originales:
    //   $order             → objeto Order completo (para code, table, user)
    //   $itemsParaImprimir → ['nuevos' => [...], 'cancelados' => [...]]
    //   $esParcial         → bool (controla el título COMANDA COCINA vs COMANDA CAMBIOS)
    //   $areaNombre        → string (nombre del área en la etiqueta negra)
    // =========================================================================
    private function generarBase64(Order $order, array $areaData): string
    {
        $pdf = Pdf::loadView('pdf.ticket-cocina', [
            'order'             => $order,
            'itemsParaImprimir' => [
                'nuevos'     => $areaData['nuevos']     ?? [],
                'cancelados' => $areaData['cancelados'] ?? [],
            ],
            'esParcial'  => $areaData['es_parcial'],
            'areaNombre' => $areaData['nombre'],
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
