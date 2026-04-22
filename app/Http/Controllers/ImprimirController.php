<?php

namespace App\Http\Controllers;

use App\Events\PrintJob;
use App\Models\Order;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ImprimirController extends Controller
{
    // public function imprimirTicket($tenant)
    // {
    //     // 1. Recuperamos datos
    //     $data = session('ticket_data');

    //     // 2. Limpiamos la sesión manualmente (ya que usamos put)
    //     // Esto asegura que si recargan, no se imprima de nuevo
    //     session()->forget('ticket_data');

    //     if (!$data) {
    //         // Opcional: Retornar un view amigable de error o cerrar ventana con JS
    //         return response('<script>window.close();</script>', 404);
    //     }

    //     $items  = $data['items'];
    //     $titulo = $data['titulo'];
    //     $meta   = $data['meta'];

    //     // 3. Altura Dinámica
    //     $height = 110 + (count($items) * 40) + 60;

    //     $pdf = Pdf::loadView('pdf.ticket-cocina', [
    //         'items'  => $items,
    //         'titulo' => $titulo,
    //         'meta'   => $meta
    //     ])->setPaper([0, 0, 226.77, $height], 'portrait');

    //     return $pdf->stream("ticket.pdf");
    // }

    public function imprimirComanda(Order $order, Request $request)
    {
        // 1. Carga de relaciones (Exactamente igual a tu lógica actual)
        $order->load(['table', 'user', 'details.product.production.printer']);

        $jobId = $request->get('jobId');
        $areaSolicitada = $request->get('areaId', 'general');

        $datosParciales = $jobId ? Cache::get($jobId) : null;
        $itemsParaImprimir = ['nuevos' => [], 'cancelados' => []];
        $esParcial = false;
        $nombreAreaTitulo = 'GENERAL';

        if ($datosParciales) {
            $esParcial = true;
            foreach (['nuevos', 'cancelados'] as $tipo) {
                if (isset($datosParciales[$tipo])) {
                    foreach ($datosParciales[$tipo] as $item) {
                        $areaItem = $item['area_id'] ?? 'general';
                        if ((string)$areaItem === (string)$areaSolicitada) {
                            $itemsParaImprimir[$tipo][] = $item;
                            $nombreAreaTitulo = $item['area_nombre'] ?? 'GENERAL';
                        }
                    }
                }
            }
        } else {
            foreach ($order->details as $det) {
                $prod = $det->product->production ?? null;
                $printer = $prod?->printer ?? null;

                if ($prod && $prod->status && $printer && $printer->status) {
                    $idArea = $prod->id;
                    $nombreArea = $prod->name;
                } else {
                    $idArea = 'general';
                    $nombreArea = 'GENERAL';
                }

                if ((string)$idArea === (string)$areaSolicitada) {
                    $itemsParaImprimir['nuevos'][] = [
                        'cant'   => $det->cantidad,
                        'nombre' => $det->product_name,
                        'nota'   => $det->notes
                    ];
                    $nombreAreaTitulo = $nombreArea;
                }
            }
        }

        // === EL CAMBIO CLAVE ESTÁ AQUÍ ===
        // En lugar de Pdf::loadView(...)->stream(), retornamos una vista Blade común.
        return view('pdf.ticket-cocina', [
            'order' => $order,
            'itemsParaImprimir' => $itemsParaImprimir,
            'esParcial' => $esParcial,
            'areaNombre' => $nombreAreaTitulo
        ]);
    }

    // public function printTicket(Sale $sale)
    // {
    //     // 1. Cargamos todas las relaciones necesarias
    //     $sale->load(['details', 'user', 'restaurant']);
    //     $tenant = $sale->restaurant;

    //     if (!$tenant) {
    //         abort(404, 'Información del restaurante no encontrada.');
    //     }

    //     // 2. Mostramos la pantalla normal para que el cajero pueda imprimirlo
    //     return view('pdf.ticket-venta', compact('sale', 'tenant'));
    // }

    public function printTicket(Sale $sale)
    {
        // 1. Cargamos lo básico
        $sale->load(['details', 'user', 'restaurant']);
        $tenant = $sale->restaurant;
        $config = $tenant->cached_config;

        $esDirecta = $config->impresion_directa_comprobante ?? false;
        $mostrarModal = $config->mostrar_modal_impresion_comprobante ?? false;

        // --- ACCIÓN 1: Si es directa, buscamos la caja y enviamos a Reverb ---
        if ($esDirecta) {

            // Buscamos la sesión de caja abierta según tu lógica
            // Usamos el user_id de la venta para saber en qué caja se procesó
            $sesionCaja = \App\Models\SessionCashRegister::where('restaurant_id', $tenant->id)
                ->where('user_id', $sale->user_id)
                ->where('status', 'open')
                ->with('cashRegister.printer') // Cargamos la impresora de la caja
                ->first();

            // Extraemos el nombre de la impresora (o usamos una por defecto)
            $nombreImpresora = $sesionCaja?->cashRegister?->printer?->name ?? 'Impresora_Predeterminada';

            // Generamos el PDF en memoria
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ticket-venta', [
                'sale' => $sale,
                'tenant' => $tenant
            ])->setPaper([0, 0, 226.77, 800], 'portrait');

            // Despachamos a Electron usando el método interno
            $this->dispatchToElectron([
                'base64'        => base64_encode($pdf->output()),
                'api_token'     => $config->api_token,
                'restaurant_id' => $tenant->id,
                'nro_orden'     => $sale->serie . '-' . $sale->correlativo,
                'mozo'          => $sale->user->name ?? 'Cajero',
                'total'         => $sale->total,
                'printer_name'  => $nombreImpresora // Enviamos el nombre de la impresora de la caja
            ]);
        }

        // --- ACCIÓN 2: Respuesta para el Iframe ---
        if (!$mostrarModal && $esDirecta) {
            return response('<p style="font-family:sans-serif; text-align:center; color:#10b981; font-size:12px; margin-top:50px;">✔ Enviado a tiquetera física</p>');
        }

        return view('pdf.ticket-venta', compact('sale', 'tenant'));
    }

    /**
     * Método interno: Despachador de Eventos
     * Se puede usar internamente por otros métodos del controlador
     */
    protected function dispatchToElectron(array $data)
    {
        // Preparamos el payload final
        $payload = [
            'base64'        => $data['base64'],
            'api_token'     => $data['api_token'],
            'restaurant_id' => $data['restaurant_id'],
            'nro_orden'     => $data['nro_orden'],
            'mozo'          => $data['mozo'],
            'total'         => $data['total'] ?? '0.00',
            'printer_name'  => $data['printer_name'] ?? 'Default',
            'fecha'         => now()->format('d/m/Y H:i:s'),
        ];

        // Disparamos el evento de Laravel Reverb
        event(new PrintJob($payload));

        return true;
    }

    /**
     * Opcional: Si aún necesitas una RUTA API para enviar datos externos,
     * este método recibe el Request y usa el despachador interno.
     */
    public function apiSendToElectron(Request $request)
    {
        $request->validate([
            'base64'        => 'required|string',
            'api_token'     => 'required|string',
            'restaurant_id' => 'required',
            'nro_orden'     => 'required',
            'mozo'          => 'required',
        ]);

        $this->dispatchToElectron($request->all());

        return response()->json(['success' => true]);
    }
}
