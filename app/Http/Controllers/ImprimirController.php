<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ImprimirController extends Controller
{
    public function imprimirTicket($tenant)
    {
        // 1. Recuperamos datos
        $data = session('ticket_data');

        // 2. Limpiamos la sesión manualmente (ya que usamos put)
        // Esto asegura que si recargan, no se imprima de nuevo
        session()->forget('ticket_data');

        if (!$data) {
            // Opcional: Retornar un view amigable de error o cerrar ventana con JS
            return response('<script>window.close();</script>', 404);
        }

        $items  = $data['items'];
        $titulo = $data['titulo'];
        $meta   = $data['meta'];

        // 3. Altura Dinámica
        $height = 110 + (count($items) * 40) + 60;

        $pdf = Pdf::loadView('pdf.ticket-cocina', [
            'items'  => $items,
            'titulo' => $titulo,
            'meta'   => $meta
        ])->setPaper([0, 0, 226.77, $height], 'portrait');

        return $pdf->stream("ticket.pdf");
    }

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

    public function printTicket(Sale $sale)
    {
        // 1. Cargamos todas las relaciones necesarias de un solo golpe
        // Cargamos 'restaurant' para obtener los datos del local y el logo
        $sale->load(['details', 'user', 'restaurant']);

        // 2. Definimos el tenant desde la relación de la venta
        $tenant = $sale->restaurant;

        // 3. Validación de seguridad: Si no hay restaurante, lanzamos error 404
        if (!$tenant) {
            abort(404, 'Información del restaurante no encontrada.');
        }

        return view('pdf.ticket-venta', compact('sale', 'tenant'));
    }
}
