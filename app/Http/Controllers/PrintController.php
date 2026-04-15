<?php

namespace App\Http\Controllers;

use App\Models\CreditDebitNote;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PrintController extends Controller
{
    public function printNota(CreditDebitNote $nota)
    {
        // 1. Cargamos las relaciones para no tener errores en la vista
        $nota->load(['restaurant', 'sale', 'user']);
        
        $tenant = $nota->restaurant;

        // 2. Cargamos la vista Blade y le pasamos los datos
        $pdf = Pdf::loadView('pdf.nota_ticket', [
            'nota'   => $nota,
            'sale'   => $nota->sale, // La factura/boleta original
            'tenant' => $tenant,
        ]);

        // 3. Ajustamos el papel a formato Ticketera (80mm)
        // 80mm = ~226.77 puntos. El largo lo ponemos dinámico (ej. 800)
        $pdf->setPaper([0, 0, 226.77, 800], 'portrait');

        $correlativoPadded = str_pad($nota->correlativo, 8, '0', STR_PAD_LEFT);
        $nombreArchivo = "{$tenant->ruc}-{$nota->tipo_nota}-{$nota->serie}-{$correlativoPadded}.pdf";

        return $pdf->stream($nombreArchivo);
    }
}