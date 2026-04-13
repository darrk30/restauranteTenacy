<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Restaurant;

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

        // 1. OBTENER VALORES DINÁMICOS DEL HELPER
        $porcentajeIgv = get_tax_percentage($tenant->id);
        $divisor = get_tax_divisor($tenant->id);

        // 🟢 Obtenemos la configuración cacheada del tenant
        $config = $tenant->cached_config;

        // 🟢 Evaluamos si se debe enviar a SUNAT según el tipo de documento
        $enviarSunat = $sale->tipo_comprobante === 'Factura'
            ? (bool) ($config->envio_facturas ?? true)
            : (bool) ($config->envio_boletas ?? true);

        $details = [];
        $totalOpGravada = 0;
        $totalIgv = 0;

        foreach ($sale->details as $item) {
            $cantidad = (float) $item->cantidad;
            $precioUnitario = (float) $item->precio_unitario;
            $codigoInterno = 'P000';
            if ($item->variant_id && $item->variant) {
                $codigoInterno = $item->variant->internal_code;
            } elseif ($item->promotion_id) {
                $codigoInterno = $item->promotion->code ?? 'PROMO';
            }

            $subtotalLlamado = round($precioUnitario * $cantidad, 2);
            $valorVentaLínea = round($subtotalLlamado / $divisor, 2);
            $igvLínea = round($subtotalLlamado - $valorVentaLínea, 2);

            $totalOpGravada += $valorVentaLínea;
            $totalIgv += $igvLínea;

            $details[] = [
                "tipAfeIgv"      => 10,
                "codProducto"    => $codigoInterno, // 🟢 Ahora usa el código interno de la variante
                "unidad"         => "NIU",
                "descripcion"    => $item->product_name ?? 'Producto',
                "cantidad"       => $cantidad,
                "mtoValorUnitario" => $cantidad > 0 ? round($valorVentaLínea / $cantidad, 5) : 0,
                "mtoValorVenta"    => $valorVentaLínea,
                "mtoBaseIgv"       => $valorVentaLínea,
                "porcentajeIgv"    => $porcentajeIgv,
                "igv"              => $igvLínea,
                "totalImpuestos"   => $igvLínea,
                "mtoPrecioUnitario" => $precioUnitario
            ];
        }

        // 3. RETORNO DEL PAYLOAD
        return [
            "enviar_sunat"  => $enviarSunat,
            "ublVersion"   => "2.1",
            "tipoOperacion" => "0101",
            "tipoDoc"      => $tipoDoc,
            "serie"        => $sale->serie,
            "correlativo"  => (string) (int) $sale->correlativo,
            "fechaEmision" => \Carbon\Carbon::parse($sale->fecha_emision)->format('c'),
            "formaPago"    => [
                "moneda" => "PEN",
                "tipo"   => "Contado"
            ],
            "tipoMoneda"   => "PEN",
            "company"      => [
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
                    "direccion"    => $tenant->address ?? '',
                    "codLocal"     => $tenant->cod_local ?? '0000'
                ]
            ],
            "client"       => [
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
            "details"         => $details
        ];
    }
}
