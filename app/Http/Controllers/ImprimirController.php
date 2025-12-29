<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

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
}