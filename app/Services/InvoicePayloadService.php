<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Restaurant; // Asegúrate de usar tu modelo de Tenant correcto

class InvoicePayloadService
{
    /**
     * Reconstruye el payload exacto para la API de Greenter basado en una Venta existente.
     */
    public function buildFromSale(Sale $sale, Restaurant $tenant): array
    {
        $tipoDoc = $sale->tipo_comprobante === 'Factura' ? '01' : '03';
        
        $clienteTipoDoc = '1'; // DNI
        if ($sale->tipo_comprobante === 'Factura' || strlen($sale->numero_documento) === 11) {
            $clienteTipoDoc = '6'; // RUC
        } elseif (!$sale->numero_documento || $sale->numero_documento === '99999999') {
            $clienteTipoDoc = '-'; // Varios
        }

        $details = [];
        
        // Iteramos sobre los detalles de la venta YA GUARDADA en la base de datos
        foreach ($sale->details as $item) {
            $cantidad = (float) $item->cantidad;
            $precioUnitario = (float) $item->precio_unitario; // CON IGV
            
            // Calculamos bases imponibles tal como lo hace tu POS
            $subtotal = round($precioUnitario * $cantidad, 2);
            $valorTotal = round($subtotal / 1.18, 2); // SIN IGV
            $igv = round($subtotal - $valorTotal, 2);

            $details[] = [
                "tipAfeIgv" => 10, // 10 = Gravado
                "codProducto" => $item->product_id ?? 'P000',
                "unidad" => "NIU",
                "descripcion" => $item->product_name ?? 'Producto',
                "cantidad" => $cantidad,
                "mtoValorUnitario" => $cantidad > 0 ? round($valorTotal / $cantidad, 5) : 0,
                "mtoValorVenta" => $valorTotal,
                "mtoBaseIgv" => $valorTotal,
                "porcentajeIgv" => 18,
                "igv" => $igv,
                "totalImpuestos" => $igv,
                "mtoPrecioUnitario" => $precioUnitario
            ];
        }

        return [
            // Como lo estamos reenviando, forzamos que intente comunicarse con SUNAT
            "enviar_sunat"  => true, 
            "ublVersion" => "2.1",
            "tipoOperacion" => "0101",
            "tipoDoc" => $tipoDoc,
            "serie" => $sale->serie,
            "correlativo" => (string) (int) $sale->correlativo, 
            "fechaEmision" => \Carbon\Carbon::parse($sale->fecha_emision)->format('c'),
            "formaPago" => [
                "moneda" => "PEN",
                "tipo" => "Contado"
            ],
            "tipoMoneda" => "PEN",
            "company" => [
                "ruc" => $tenant->ruc ?? '20000000001',
                "razonSocial" => $tenant->name ?? $tenant->razon_social ?? 'EMPRESA DEMO',
                "nombreComercial" => $tenant->name_comercial ?? 'EMPRESA',
                "address" => [
                    "ubigeo" => $tenant->ubigeo ?? '150101',
                    "departamento" => $tenant->department ?? 'LIMA',
                    "provincia" => $tenant->province ?? 'LIMA',
                    "distrito" => $tenant->district ?? 'LIMA',
                    "urbanizacion" => $tenant->urbanizacion ?? '',
                    "direccion" => $tenant->address ?? $tenant->direccion ?? 'SIN DIRECCION',
                    "codLocal" => "0000"
                ]
            ],
            "client" => [
                "tipoDoc" => $clienteTipoDoc,
                "numDoc" => $sale->numero_documento ?? '99999999',
                "rznSocial" => $sale->nombre_cliente ?? 'CLIENTES VARIOS'
            ],
            "mtoOperGravadas" => (float) $sale->op_gravada,
            "mtoIGV" => (float) $sale->monto_igv,
            "totalImpuestos" => (float) $sale->monto_igv,
            "valorVenta" => (float) $sale->op_gravada,
            "subTotal" => (float) $sale->total,
            "mtoImpVenta" => (float) $sale->total,
            "details" => $details
        ];
    }
}