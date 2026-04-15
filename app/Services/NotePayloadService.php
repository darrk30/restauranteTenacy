<?php

namespace App\Services;

use App\Models\CreditDebitNote;
use App\Models\Restaurant;
use Carbon\Carbon;

class NotePayloadService
{
    /**
     * Construye el payload para enviar Notas de Crédito y Débito a Greenter.
     */
    public function buildFromNote(CreditDebitNote $note, Restaurant $tenant): array
    {
        // 1. OBTIENE EL COMPROBANTE ORIGINAL AL QUE AFECTA LA NOTA
        $sale = $note->sale;

        $tipDocAfectado = $sale->tipo_comprobante === 'Factura' ? '01' : '03';
        $numDocAfectado = "{$sale->serie}-{$sale->correlativo}";

        // 2. CONFIGURACIÓN DEL CLIENTE
        $clienteTipoDoc = '1'; // DNI por defecto
        if ($sale->tipo_comprobante === 'Factura' || strlen($sale->numero_documento) === 11) {
            $clienteTipoDoc = '6'; // RUC
        } elseif (empty($sale->numero_documento) || $sale->numero_documento === '99999999') {
            $clienteTipoDoc = '-'; // Varios
        }

        // 3. VALORES DINÁMICOS DEL IGV (Usando tu helper)
        $porcentajeIgv = get_tax_percentage($tenant->id);
        $divisor = get_tax_divisor($tenant->id);

        $details = [];
        $totalOpGravada = 0;
        $totalIgv = 0;

        // 4. PROCESAR DETALLES
        // Recorremos el JSON guardado en $note->details
        foreach ($note->details as $item) {
            // Aseguramos que el item sea tratado como un array en PHP
            $item = (array) $item;

            $cantidad = (float) ($item['cantidad'] ?? 1);
            $precioUnitario = (float) ($item['precio_unitario'] ?? $item['precio'] ?? 0); // Precio con IGV

            // CÁLCULOS POR LÍNEA EXACTOS
            $subtotalLlamado = round($precioUnitario * $cantidad, 2);
            $valorVentaLinea = round($subtotalLlamado / $divisor, 2); // Base imponible
            $igvLinea = round($subtotalLlamado - $valorVentaLinea, 2);

            $totalOpGravada += $valorVentaLinea;
            $totalIgv += $igvLinea;

            $details[] = [
                "tipAfeIgv"         => 10, // 10 = Gravado
                "codProducto"       => $item['product_id'] ?? $item['codProducto'] ?? 'P000',
                "unidad"            => "NIU",
                "descripcion"       => $item['product_name'] ?? $item['descripcion'] ?? 'Producto',
                "cantidad"          => $cantidad,
                "mtoValorUnitario"  => $cantidad > 0 ? round($valorVentaLinea / $cantidad, 5) : 0,
                "mtoValorVenta"     => $valorVentaLinea,
                "mtoBaseIgv"        => $valorVentaLinea,
                "porcentajeIgv"     => $porcentajeIgv,
                "igv"               => $igvLinea,
                "totalImpuestos"    => $igvLinea,
                "mtoPrecioUnitario" => $precioUnitario
            ];
        }

        // Recálculo maestro para SUNAT
        $totalComprobante = round($totalOpGravada + $totalIgv, 2);

        // 5. EXTRACCIÓN DEL MOTIVO
        // Como implementamos Enums, validamos si viene como objeto Enum o como String
        $codMotivo = $note->cod_motivo instanceof \App\Enums\MotivoNotaCredito 
            ? $note->cod_motivo->value 
            : $note->cod_motivo;

        // 6. RETORNO DEL PAYLOAD
        return [
            "ublVersion"     => "2.1",
            "tipoDoc"        => $note->tipo_nota, // Ej: '07' (Crédito) u '08' (Débito)
            "serie"          => $note->serie, // Ej: FC01 o BC01
            "correlativo"    => (string) (int) $note->correlativo,
            "fechaEmision"   => Carbon::parse($note->fecha_emision)->format('c'),
            "tipDocAfectado" => $tipDocAfectado,
            "numDocAfectado" => $numDocAfectado,
            "codMotivo"      => $codMotivo,
            "desMotivo"      => $note->des_motivo,
            "formaPago"      => [
                "moneda" => "PEN",
                "tipo"   => "Contado"
            ],
            "tipoMoneda"     => "PEN",
            "company"        => [
                "ruc"             => $tenant->ruc,
                "razonSocial"     => $tenant->name,
                "nombreComercial" => $tenant->name_comercial ?? $tenant->name,
                "email" => $tenant->email ?? '',
                "telefono" => $tenant->phone ?? '',
                "address"         => [
                    "ubigeo"       => $tenant->ubigeo ?? '',
                    "departamento" => $tenant->department ?? '',
                    "provincia"    => $tenant->province ?? '',
                    "distrito"     => $tenant->district ?? '',
                    "urbanizacion" => $tenant->urbanizacion ?? '',
                    "direccion"    => $tenant->address ?? '',
                    "codLocal"     => "0000"
                ]
            ],
            "client"         => [
                "tipoDoc"   => $clienteTipoDoc,
                "numDoc"    => $sale->numero_documento ?? '99999999',
                "razonSocial" => $sale->nombre_cliente ?? 'CLIENTES VARIOS',
                "email" => $sale->client->email ?? '',
                "telefono" => $sale->client->telefono ?? '',
                "address" => [  
                    "ubigeo" => "",
                    "departamento" => "",
                    "provincia" => "",
                    "distrito" => "",
                    "direccion" => $sale->client->direccion ?? ''
                ]
            ],
            "mtoOperGravadas" => (float) round($totalOpGravada, 2),
            "mtoIGV"          => (float) round($totalIgv, 2),
            "totalImpuestos"  => (float) round($totalIgv, 2),
            "valorVenta"      => (float) round($totalOpGravada, 2),
            "subTotal"        => (float) $totalComprobante,
            "mtoImpVenta"     => (float) $totalComprobante,
            
            "details"         => $details
        ];
    }
}