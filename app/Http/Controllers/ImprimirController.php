<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class ImprimirController extends Controller
{
    public function show($tenant, Order $order)
    {
        $order->load([
            'table',
            'details' => fn ($q) => $q->where('status', '!=', 'cancelado'),
            'details.product',
            'details.variant.values.attribute',
        ]);

        // 🔢 contar items reales
        $itemsCount = $order->details->count();

        // 📏 calcular altura dinámica
        $height = 90 + ($itemsCount * 24) + 60;

        $pdf = Pdf::loadView('pdf.ticket-cocina', [
            'order' => $order,
        ])->setPaper([0, 0, 226.77, $height], 'portrait'); // 80mm

        return $pdf->stream("comanda-{$order->code}.pdf");
    }
}
