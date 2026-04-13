<?php

namespace Database\Seeders;

use App\Enums\DocumentSeriesType;
use App\Models\Client;
use App\Models\DocumentSerie;
use App\Models\PaymentMethod;
use App\Models\Production;
use App\Models\Restaurant;
use App\Models\TypeDocument;
use App\Models\CashRegister; // IMPORTANTE: Agregar el modelo
use App\Models\Configuration;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConfiguracionInicial extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
    }

    public function runForRestaurant(Restaurant $restaurant): void
    {
        $adminRestaurante = Role::firstOrCreate([
            'name' => 'Administrador',
            'guard_name' => 'web',
            'restaurant_id' => $restaurant->id // 🟢 Ahora el rol le pertenece a este restaurante
        ]);
        $adminRestaurante->syncPermissions(Permission::where('scope', 'restaurant')->get());

        // 1.2 CAJERO
        $cajero = Role::firstOrCreate([
            'name' => 'Cajero',
            'guard_name' => 'web',
            'restaurant_id' => $restaurant->id
        ]);
        $cajero->syncPermissions([
            'ver_dashboard_ventas_rest',
            'ver_dashboard_metodos_pago_rest',
            'ver_punto_venta_rest',
            'ver_salon_rest',
            'ver_llevar_rest',
            'ver_delivery_rest',
            'crear_orden_llevar_rest',
            'crear_orden_delivery_rest',
            'ordenar_pedido_rest',
            'cobrar_pedido_rest',
            'asignar_repartidor_rest',
            'listar_apertura_cierre_rest',
            'aperturar_caja_rest',
            'cerrar_caja_rest',
            'listar_ingresos_egresos_rest',
            'registrar_ingreso_rest',
            'registrar_egreso_rest',
            'listar_historial_ventas_rest',
            'ver_detalle_venta_rest',
            'reimprimir_ticket_rest',
            'listar_clientes_rest',
            'crear_cliente_rest',
            'editar_cliente_rest',
            'ver_facturas_cliente_rest',
        ]);

        // 1.3 MOZO
        $mozo = Role::firstOrCreate([
            'name' => 'Mozo',
            'guard_name' => 'web',
            'restaurant_id' => $restaurant->id
        ]);
        $mozo->syncPermissions([
            'ver_punto_venta_rest',
            'ver_salon_rest',
            'ordenar_pedido_rest',
            'listar_clientes_rest',
            'crear_cliente_rest'
        ]);

        // 1.4 DELIVERY
        $delivery = Role::firstOrCreate([
            'name' => 'Delivery',
            'guard_name' => 'web',
            'restaurant_id' => $restaurant->id
        ]);
        $delivery->syncPermissions([
            'ver_punto_venta_rest',
            'ver_delivery_rest'
        ]);

        // 1.5 ALMACENERO
        $almacenero = Role::firstOrCreate([
            'name' => 'Almacenero',
            'guard_name' => 'web',
            'restaurant_id' => $restaurant->id
        ]);
        $almacenero->syncPermissions([
            'listar_productos_rest',
            'crear_producto_rest',
            'editar_producto_rest',
            'ver_variantes_producto_rest',
            'listar_categorias_rest',
            'listar_marcas_rest',
            'listar_existencias_rest',
            'listar_kardex_rest',
            'listar_ajustes_stock_rest',
            'crear_ajuste_stock_rest',
            'listar_compras_rest',
            'crear_compra_rest',
            'editar_compra_rest',
            'listar_proveedores_rest',
            'crear_proveedor_rest',
            'editar_proveedor_rest'
        ]);

        // 1. Buscar el ID del tipo de documento DNI por su código
        $dniType = TypeDocument::where('code', 'DNI')->first();

        // 2. Crear Cliente "Clientes Varios" por defecto
        if ($dniType) {
            Client::firstOrCreate([
                'restaurant_id'    => $restaurant->id,
                'numero'           => '99999999',
            ], [
                'nombres'          => 'CLIENTES',
                'apellidos'        => 'VARIOS',
                'type_document_id' => $dniType->id,
                'status'           => 'Activo',
            ]);
        }

        // 3. MÉTODOS DE PAGO POR DEFECTO CON IMÁGENES
        $metodosPago = [
            [
                'name' => 'Efectivo',
                'payment_condition' => 'Contado',
                'requiere_referencia' => false,
                'image' => 'metodos_pago/efectivo.png',
            ],
            [
                'name' => 'Yape',
                'payment_condition' => 'Contado',
                'requiere_referencia' => true,
                'image' => 'metodos_pago/yape.png',
            ],
            [
                'name' => 'Tarjeta',
                'payment_condition' => 'Contado',
                'requiere_referencia' => true,
                'image' => 'metodos_pago/tarjeta.png',
            ],
        ];

        foreach ($metodosPago as $metodo) {
            PaymentMethod::firstOrCreate([
                'restaurant_id' => $restaurant->id,
                'name'          => $metodo['name'],
            ], [
                'payment_condition'   => $metodo['payment_condition'],
                'requiere_referencia' => $metodo['requiere_referencia'],
                'image_path'          => $metodo['image'],
                'status'              => true,
            ]);
        }

        // 4. PUNTOS DE PRODUCCIÓN (Cocina y Almacén)
        $puntosProduccion = ['Cocina', 'Almacén'];

        foreach ($puntosProduccion as $punto) {
            Production::firstOrCreate([
                'name'          => $punto,
                'restaurant_id' => $restaurant->id,
            ], [
                'status'        => true,
                'printer_id'    => null,
            ]);
        }

        // 5. CAJA PRINCIPAL (Nueva sección agregada)
        CashRegister::firstOrCreate([
            'restaurant_id' => $restaurant->id,
            'code'          => 'CAJA-01', // Código único para la caja
        ], [
            'name'          => 'Caja Principal',
            'status'        => true,
        ]);

        // 6. SERIES DE DOCUMENTOS
        $seriesIniciales = [
            [
                'type_documento' => DocumentSeriesType::FACTURA,
                'serie' => 'F001',
            ],
            [
                'type_documento' => DocumentSeriesType::BOLETA,
                'serie' => 'B001',
            ],
            [
                'type_documento' => DocumentSeriesType::NOTA_VENTA,
                'serie' => 'NV01',
            ],
            [
                'type_documento' => DocumentSeriesType::NOTA_CREDITO,
                'serie' => 'FC01',
            ],
            [
                'type_documento' => DocumentSeriesType::NOTA_CREDITO,
                'serie' => 'BC01',
            ],
            [
                'type_documento' => DocumentSeriesType::NOTA_DEBITO,
                'serie' => 'FD01',
            ],
            [
                'type_documento' => DocumentSeriesType::NOTA_DEBITO,
                'serie' => 'BD01',
            ],
        ];

        foreach ($seriesIniciales as $data) {
            DocumentSerie::firstOrCreate([
                'restaurant_id' => $restaurant->id,
                'serie' => $data['serie'],
            ], [
                'type_documento' => $data['type_documento'],
                'current_number' => 0,
                'is_active' => true,
            ]);
        }

        Configuration::firstOrCreate(
            ['restaurant_id' => $restaurant->id],
            [
                // Impresión Directa
                'impresion_directa_precuenta' => false,
                'impresion_directa_comprobante' => false,
                'impresion_directa_comanda' => false, // Por defecto imprimen comanda

                // Modal
                'mostrar_modal_impresion_comanda' => true,
                'mostrar_modal_impresion_precuenta' => true,
                'mostrar_modal_impresion_comprobante' => true,

                // KDS
                'mostrar_pantalla_cocina' => false,

                // Web
                'guardar_pedidos_web' => true,
                'habilitar_delivery_web' => true,
                'habilitar_recojo_web' => true,

                // Facturación
                'precios_incluyen_impuesto' => true,
                'porcentaje_impuesto' => 18.00,
            ]
        );
    }
}
