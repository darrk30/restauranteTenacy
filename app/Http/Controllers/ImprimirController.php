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
        // Cargamos relaciones profundas para determinar el área en tiempo real
        $order->load(['table', 'user', 'details.product.production.printer']);

        $jobId = $request->get('jobId');
        // Recibimos el área por URL (query param). Por defecto 'general'
        $areaSolicitada = $request->get('areaId', 'general'); 

        $datosParciales = $jobId ? Cache::get($jobId) : null;
        $itemsParaImprimir = ['nuevos' => [], 'cancelados' => []];
        $esParcial = false;
        $nombreAreaTitulo = 'GENERAL';

        if ($datosParciales) {
            // === MODO PARCIAL (CACHE) ===
            $esParcial = true;
            
            // Recorremos 'nuevos' y 'cancelados' buscando coincidencias con el área
            foreach (['nuevos', 'cancelados'] as $tipo) {
                if (isset($datosParciales[$tipo])) {
                    foreach ($datosParciales[$tipo] as $item) {
                        // El area_id ya viene guardado en el Cache gracias a tu cambio anterior en OrdenMesa.php
                        $areaItem = $item['area_id'] ?? 'general';
                        
                        if ((string)$areaItem === (string)$areaSolicitada) {
                            $itemsParaImprimir[$tipo][] = $item;
                            // Capturamos el nombre real para el título del PDF
                            $nombreAreaTitulo = $item['area_nombre'] ?? 'GENERAL';
                        }
                    }
                }
            }

        } else {
            // === MODO TOTAL (BASE DE DATOS) ===
            // Aquí recalculamos las áreas porque la BD no guarda el "histórico" de a dónde se fue
            foreach ($order->details as $det) {
                $prod = $det->product->production ?? null;
                $printer = $prod?->printer ?? null;

                // Lógica de determinación de área
                if ($prod && $prod->status && $printer && $printer->status) {
                    $idArea = $prod->id;
                    $nombreArea = $prod->name;
                } else {
                    $idArea = 'general';
                    $nombreArea = 'GENERAL';
                }

                // Si coincide con el área solicitada en la URL, lo agregamos
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

        // Calculamos altura dinámica basada en los items filtrados
        $totalLineas = count($itemsParaImprimir['nuevos']) + count($itemsParaImprimir['cancelados']);
        
        // Si no hay líneas para esta área, podrías retornar un PDF vacío o un mensaje, 
        // pero el modal ya filtra las pestañas, así que siempre debería haber algo.
        
        $height = 140 + ($totalLineas * 50) + 60;
        $customPaper = array(0, 0, 226.77, $height);

        // Pasamos 'areaNombre' a la vista para que salga en el título del ticket (ej: "COCINA")
        return Pdf::loadView('pdf.ticket-cocina', [
                'order' => $order,
                'itemsParaImprimir' => $itemsParaImprimir,
                'esParcial' => $esParcial,
                'areaNombre' => $nombreAreaTitulo 
            ])
            ->setPaper($customPaper, 'portrait')
            ->stream('comanda-' . $order->code . '-' . $areaSolicitada . '.pdf');
    }
}
