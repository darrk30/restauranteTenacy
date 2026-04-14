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

        $clienteTipoDoc = '1';
        if ($sale->tipo_comprobante === 'Factura' || strlen($sale->numero_documento) === 11) {
            $clienteTipoDoc = '6';
        } elseif (!$sale->numero_documento || $sale->numero_documento === '99999999') {
            $clienteTipoDoc = '-';
        }

        $porcentajeIgv = get_tax_percentage($tenant->id);
        $divisor       = get_tax_divisor($tenant->id);
        $config        = $tenant->cached_config;

        $enviarSunat = $sale->tipo_comprobante === 'Factura'
            ? (bool) ($config->envio_facturas ?? true)
            : (bool) ($config->envio_boletas ?? true);

        $details  = [];

        foreach ($sale->details as $item) {
            $cantidad       = (float) $item->cantidad;
            $precioUnitario = (float) $item->precio_unitario;

            $codigoInterno = 'P000';
            if ($item->variant_id && $item->variant) {
                $codigoInterno = $item->variant->internal_code;
            } elseif ($item->promotion_id) {
                $codigoInterno = $item->promotion->code ?? 'PROMO';
            }

            $subtotal   = round($precioUnitario * $cantidad, 2);
            $valorVenta = round($subtotal / $divisor, 2);
            $igvLinea   = round($subtotal - $valorVenta, 2);

            $details[] = [
                'tipAfeIgv'          => 10,
                'codProducto'        => $codigoInterno,
                'unidad'             => 'NIU',
                'descripcion'        => $item->product_name ?? 'Producto',
                'cantidad'           => $cantidad,
                'mtoValorUnitario'   => $cantidad > 0 ? round($valorVenta / $cantidad, 5) : 0,
                'mtoValorVenta'      => $valorVenta,
                'mtoBaseIgv'         => $valorVenta,
                'porcentajeIgv'      => $porcentajeIgv,
                'igv'                => $igvLinea,
                'totalImpuestos'     => $igvLinea,
                'mtoPrecioUnitario'  => $precioUnitario,
            ];
        }

        // ✅ DESCUENTO GLOBAL (si existe en la venta)
        $descuentos     = [];
        $montoDescuento = (float) ($sale->monto_descuento ?? 0);

        if ($montoDescuento > 0) {
            $descuentos[] = [
                'codTipo' => '02',  // Afecta base imponible - Catálogo 53
                'monto'   => $montoDescuento, // Con IGV incluido, el trait lo convierte
                'conIGV'  => true,
            ];
        }

        $payload = [
            'enviar_sunat'  => $enviarSunat,
            'ublVersion'    => '2.1',
            'tipoOperacion' => '0101',
            'tipoDoc'       => $tipoDoc,
            'serie'         => $sale->serie,
            'correlativo'   => (string) (int) $sale->correlativo,
            'fechaEmision'  => \Carbon\Carbon::parse($sale->fecha_emision)->format('c'),
            'formaPago'     => ['moneda' => 'PEN', 'tipo' => 'Contado'],
            'tipoMoneda'    => 'PEN',
            'company'       => [
                'ruc'             => $tenant->ruc,
                'razonSocial'     => $tenant->name,
                'nombreComercial' => $tenant->name_comercial ?? $tenant->name,
                'email'           => $tenant->email ?? '',
                'telefono'        => $tenant->phone ?? '',
                'address'         => [
                    'ubigeo'       => $tenant->ubigeo ?? '',
                    'departamento' => $tenant->department ?? '',
                    'provincia'    => $tenant->province ?? '',
                    'distrito'     => $tenant->district ?? '',
                    'direccion'    => $tenant->address ?? '',
                    'codLocal'     => $tenant->cod_local ?? '0000',
                ],
            ],
            'client'        => [
                'tipoDoc'    => $clienteTipoDoc,
                'numDoc'     => $sale->numero_documento ?? '99999999',
                'razonSocial' => $sale->nombre_cliente ?? 'CLIENTES VARIOS',
                'email'      => $sale->client->email ?? '',
                'telefono'   => $sale->client->telefono ?? '',
                'address'    => [
                    'ubigeo'       => '',
                    'departamento' => '',
                    'provincia'    => '',
                    'distrito'     => '',
                    'direccion'    => $sale->client->direccion ?? '',
                ],
            ],
            'details' => $details,
        ];

        // Solo agrega el nodo si hay descuento, el trait lo procesa en setTotales()
        if (!empty($descuentos)) {
            $payload['descuentos'] = $descuentos;
        }

        return $payload;
    }
}
