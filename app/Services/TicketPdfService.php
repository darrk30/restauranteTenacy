<?php

namespace App\Services;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TicketPdfService
{
    public function generateAndSave(Sale $sale, $tenant)
    {
        if (!in_array($sale->tipo_comprobante, ['Boleta', 'Factura'])) return false;

        try {
            $slug  = $tenant->slug ?? 'default';
            $fecha = Carbon::parse($sale->fecha_emision)->format('Y-m-d');
            $folder = "tenants/{$slug}/comprobantes/pdf/{$fecha}";
            $nombreBase = "{$sale->serie}-{$sale->correlativo}";
            $pathPdf = "{$folder}/{$nombreBase}.pdf";

            // 🚀 PASAMOS 'isPdf' => true
            $pdf = Pdf::loadView('pdf.desgarcar-ticket-pdf', [
                'sale' => $sale,
                'tenant' => $tenant,
                'isPdf' => true 
            ])->setPaper([0, 0, 226, 800], 'portrait'); 

            Storage::disk('public')->put($pathPdf, $pdf->output());
            $sale->update(['path_pdf' => $pathPdf]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Error PDF: " . $e->getMessage());
            return false;
        }
    }
}