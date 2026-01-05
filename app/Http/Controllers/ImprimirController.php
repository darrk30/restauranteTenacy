<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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
        $order->load(['table', 'user']); // Ya no cargamos details obligatoriamente

        // 1. Verificar si hay un trabajo parcial en Cache
        $jobId = $request->get('jobId');
        $datosParciales = $jobId ? Cache::get($jobId) : null;

        $itemsParaImprimir = [];
        $esParcial = false;

        if ($datosParciales) {
            // MODO PARCIAL (Solo cambios)
            $esParcial = true;
            // Pasamos los datos crudos a la vista
            $itemsParaImprimir = $datosParciales;

            // Calculo de altura aproximada
            $totalLineas = count($datosParciales['nuevos']) + count($datosParciales['cancelados']);
        } else {
            // MODO TOTAL (Fallback o primera orden)
            $order->load('details');
            $itemsParaImprimir = ['nuevos' => [], 'cancelados' => []];

            // Convertimos los detalles normales al formato de impresión
            foreach ($order->details as $det) {
                $itemsParaImprimir['nuevos'][] = [
                    'cant' => $det->cantidad,
                    'nombre' => $det->product_name,
                    'nota' => $det->notes
                ];
            }
            $totalLineas = $order->details->count();
        }

        // Calculamos altura dinámica
        $height = 140 + ($totalLineas * 50) + 60;
        $customPaper = array(0, 0, 226.77, $height);

        return Pdf::loadView('pdf.ticket-cocina', compact('order', 'itemsParaImprimir', 'esParcial'))
            ->setPaper($customPaper, 'portrait')
            ->stream('comanda-' . $order->code . '.pdf');
    }
}
