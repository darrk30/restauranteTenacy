<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsRestSeeder extends Seeder
{
    public function run()
    {
        // 1. Limpiar caché de permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Definir Estructura Granular Modular
        $estructura = [

            // ==========================================
            // MÓDULO: DASHBOARD Y PUNTO DE VENTA
            // ==========================================
            'escritorio' => [
                'label' => 'Escritorio (Dashboard)',
                'permissions' => [
                    'ver_dashboard_rest' => 'Ver dashboard',
                    'ver_dashboard_ventas_rest' => 'Ver métricas de ventas y saldos',
                    'ver_dashboard_metodos_pago_rest' => 'Ver métricas por método de pago',
                    'ver_dashboard_ganancias_rest' => 'Ver ganancias y márgenes',
                    'ver_dashboard_ingresos_egresos_rest' => 'Ver ingresos y egresos',
                    'ver_dashboard_anulaciones_rest' => 'Ver pedidos anulados',
                    'ver_dashboard_qr_rest' => 'Ver código QR del menú',
                    'activar_menu_publico_rest' => 'Activar/Desactivar Menú Digital',
                    'ver_dashboard_tendencias_rest' => 'Ver gráficos de tendencias',
                ]
            ],
            'punto_venta' => [
                'label' => 'Punto de Venta (Caja)',
                'permissions' => [
                    'ver_punto_venta_rest' => 'Acceso al Punto de Venta',
                    'ver_salon_rest' => 'Ver salón y mesas',
                    'ver_llevar_rest' => 'Ver sección para Llevar',
                    'ver_delivery_rest' => 'Ver sección delivery',
                    'crear_orden_llevar_rest' => 'Crear orden para llevar',
                    'crear_orden_delivery_rest' => 'Crear orden para delivery',
                    'ordenar_pedido_rest' => 'Ordenar/Actualizar pedido',
                    'anular_pedido_rest' => 'Anular Pedido',
                    'cobrar_pedido_rest' => 'Compleatr pago de pedido',
                    'asignar_repartidor_rest' => 'Asignar repartidor en Delivery',
                    'ver_deliverys_usuario_rest' => 'Ver ordenes de delivery por usuario',
                ]
            ],

            // ==========================================
            // MÓDULO: CAJA Y FINANZAS
            // ==========================================
            'caja_apertura' => [
                'label' => 'Apertura y Cierre de Caja',
                'permissions' => [
                    'listar_apertura_cierre_rest' => 'Ver listado de aperturas y cierres',
                    'aperturar_caja_rest' => 'Aperturar caja',
                    'cerrar_caja_rest' => 'Cerrar caja',
                ]
            ],
            'caja_ingresos_egresos' => [
                'label' => 'Ingresos y Egresos',
                'permissions' => [
                    'listar_ingresos_egresos_rest' => 'Ver ingresos y egresos',
                    'registrar_ingreso_rest' => 'Registrar nuevo ingreso',
                    'registrar_egreso_rest' => 'Registrar nuevo egreso',
                    'anular_ingreso_egreso_rest' => 'Anular ingreso o egreso',
                ]
            ],
            'caja_historial_ventas' => [
                'label' => 'Historial de Ventas',
                'permissions' => [
                    'listar_historial_ventas_rest' => 'Ver historial de ventas',
                    'ver_detalle_venta_rest' => 'Ver detalles de venta',
                    'reimprimir_ticket_rest' => 'Reimprimir ticket',
                    'anular_venta_rest' => 'Anular venta',
                ]
            ],

            // ==========================================
            // MÓDULO: CLIENTES Y PROVEEDORES
            // ==========================================
            'clientes' => [
                'label' => 'Gestión de Clientes',
                'permissions' => [
                    'listar_clientes_rest' => 'Listar clientes',
                    'crear_cliente_rest' => 'Crear cliente',
                    'editar_cliente_rest' => 'Editar cliente',
                    'ver_facturas_cliente_rest' => 'Ver facturas de cliente',
                    'importar_clientes_rest' => 'Importar clientes',
                    'exportar_clientes_rest' => 'Exportar clientes',
                ]
            ],
            'proveedores' => [
                'label' => 'Gestión de Proveedores',
                'permissions' => [
                    'listar_proveedores_rest' => 'Listar proveedores',
                    'crear_proveedor_rest' => 'Crear proveedor',
                    'editar_proveedor_rest' => 'Editar proveedor',
                    'anular_proveedor_rest' => 'Anular proveedor',
                    'importar_proveedores_rest' => 'Importar proveedores',
                    'exportar_proveedores_rest' => 'Exportar proveedores',
                ]
            ],
            'compras' => [
                'label' => 'Gestión de Compras',
                'permissions' => [
                    'listar_compras_rest' => 'Listar compras',
                    'crear_compra_rest' => 'Registrar compra',
                    'editar_compra_rest' => 'Editar compra',
                    'anular_compra_rest' => 'Anular compra',
                ]
            ],

            // ==========================================
            // MÓDULO: INVENTARIO DESGLOSADO
            // ==========================================
            'inventario_productos' => [
                'label' => 'Gestión de Productos',
                'permissions' => [
                    'listar_productos_rest' => 'Listar productos',
                    'crear_producto_rest' => 'Crear producto',
                    'editar_producto_rest' => 'Editar producto',
                    'importar_productos_rest' => 'Importar productos',
                    'exportar_productos_rest' => 'Exportar productos',
                    'actualizar_precios_productos_rest' => 'Actualizar precios masivamente',
                    'ver_variantes_producto_rest' => 'Ver variantes',
                    'editar_variante_producto_rest' => 'Editar variante',
                ]
            ],
            'inventario_categorias' => [
                'label' => 'Gestión de Categorías',
                'permissions' => [
                    'listar_categorias_rest' => 'Listar categorías',
                    'crear_categoria_rest' => 'Crear categoría',
                    'editar_categoria_rest' => 'Editar categoría',
                    'eliminar_categoria_rest' => 'Eliminar categoría',
                ]
            ],
            'inventario_marcas' => [
                'label' => 'Gestión de Marcas',
                'permissions' => [
                    'listar_marcas_rest' => 'Listar marcas',
                    'crear_marca_rest' => 'Crear marca',
                    'editar_marca_rest' => 'Editar marca',
                    'eliminar_marca_rest' => 'Eliminar marca',
                ]
            ],
            'inventario_promociones' => [
                'label' => 'Gestión de Promociones',
                'permissions' => [
                    'listar_promociones_rest' => 'Listar promociones',
                    'crear_promocion_rest' => 'Crear promoción',
                    'editar_promocion_rest' => 'Editar promoción',
                ]
            ],
            'inventario_stock' => [
                'label' => 'Control de Existencias',
                'permissions' => [
                    'listar_existencias_rest' => 'Ver existencias',
                    'descargar_pdf_existencias_rest' => 'Descargar PDF de existencias',
                    'listar_kardex_rest' => 'Ver Kardex',
                    'descargar_pdf_kardex_rest' => 'Descargar PDF de Kardex',
                ]
            ],
            'inventario_ajustes' => [
                'label' => 'Ajustes de Stock',
                'permissions' => [
                    'listar_ajustes_stock_rest' => 'Listar ajustes de stock',
                    'crear_ajuste_stock_rest' => 'Crear ajuste de stock',
                    'editar_ajuste_stock_rest' => 'Editar ajuste de stock',
                    'anular_ajuste_stock_rest' => 'Anular ajuste de stock',
                ]
            ],

            // ==========================================
            // MÓDULO: REPORTES
            // ==========================================
            'reportes' => [
                'label' => 'Reportes',
                'permissions' => [
                    'ver_reporte_ventas_rest' => 'Reporte de ventas',
                    'ver_reporte_ganancias_rest' => 'Reporte de ganancias',
                    'ver_reporte_cajas_rest' => 'Reporte de cajas',
                    'ver_reporte_ingresos_egresos_rest' => 'Reporte de ingresos/egresos',
                    'ver_reporte_ranking_productos_rest' => 'Ranking de productos',
                    'ver_reporte_anulaciones_rest' => 'Reporte de anulaciones',
                    'ver_reporte_ventas_mozo_rest' => 'Ventas por mozo',
                ]
            ],

            // ==========================================
            // MÓDULO: CONFIGURACIÓN DESGLOSADA
            // ==========================================
            'config_pisos_mesas' => [
                'label' => 'Gestión de Pisos y Mesas',
                'permissions' => [
                    'listar_pisos_rest' => 'Listar pisos',
                    'crear_piso_rest' => 'Crear piso',
                    'editar_piso_rest' => 'Editar piso',
                    'eliminar_piso_rest' => 'Eliminar piso',
                    'asignar_mesa_rest' => 'Crear/Asignar mesa',
                    'ver_qr_mesa_rest' => 'Ver QR de mesa',
                    'editar_mesa_rest' => 'Editar mesa',
                    'eliminar_mesa_rest' => 'Eliminar mesa',
                ]
            ],
            'config_cajas_reg' => [
                'label' => 'Gestión de Cajas Registradoras',
                'permissions' => [
                    'listar_cajas_registradoras_rest' => 'Listar cajas registradoras',
                    'crear_caja_registradora_rest' => 'Crear caja',
                    'editar_caja_registradora_rest' => 'Editar caja',
                    'asignar_usuario_caja_rest' => 'Vincular usuario a caja',
                    // 'editar_usuario_caja_rest' => 'Editar asignación de usuario',
                    'desvincular_usuario_caja_rest' => 'Desvincular usuario de caja',
                ]
            ],
            'config_metodos_pago' => [
                'label' => 'Gestión de Métodos de Pago',
                'permissions' => [
                    'listar_metodos_pago_rest' => 'Listar métodos de pago',
                    'crear_metodo_pago_rest' => 'Crear método de pago',
                    'editar_metodo_pago_rest' => 'Editar método de pago',
                    'eliminar_metodo_pago_rest' => 'Eliminar método de pago',
                ]
            ],
            'config_areas_produccion' => [
                'label' => 'Gestión de Áreas de Producción',
                'permissions' => [
                    'listar_areas_produccion_rest' => 'Listar áreas de producción',
                    'crear_area_produccion_rest' => 'Crear área de producción',
                    'editar_area_produccion_rest' => 'Editar área de producción',
                    'eliminar_area_produccion_rest' => 'Eliminar área de producción',
                ]
            ],
            'config_impresoras' => [
                'label' => 'Gestión de Impresoras',
                'permissions' => [
                    'listar_impresoras_rest' => 'Listar impresoras',
                    'crear_impresora_rest' => 'Crear impresora',
                    'editar_impresora_rest' => 'Editar impresora',
                    'eliminar_impresora_rest' => 'Eliminar impresora',
                ]
            ],
            'config_series_documentos' => [
                'label' => 'Gestión de Series de Comprobantes',
                'permissions' => [
                    'listar_series_documentos_rest' => 'Listar series de documentos',
                    'crear_serie_documento_rest' => 'Crear serie',
                    'editar_serie_documento_rest' => 'Editar serie',
                    'eliminar_serie_documento_rest' => 'Eliminar serie',
                ]
            ],
            'config_unidades_medida' => [
                'label' => 'Gestión de Unidades de Medida',
                'permissions' => [
                    'listar_categoria_unidades_rest' => 'Listar Categoria unidades de medida',
                    'crear_categoria_unidad_rest' => 'Crear Categoria unidad de medida',
                    'editar_unidad_categoria_rest' => 'Editar Categoria de unidad de medida',
                    'eliminar_eliminar_unidad_rest' => 'Eliminar Categoria de unidad de medida',
                    'listar_unidades_medida_rest' => 'Listar unidades de medida',
                    'crear_unidad_medida_rest' => 'Crear unidad de medida',
                    'editar_unidad_medida_rest' => 'Editar unidad de medida',
                    'eliminar_unidad_medida_rest' => 'Eliminar unidad de medida',
                ]
            ],
            'config_banners' => [
                'label' => 'Gestión de Banners',
                'permissions' => [
                    'listar_banners_rest' => 'Listar banners',
                    'crear_banner_rest' => 'Crear banner',
                    'editar_banner_rest' => 'Editar banner',
                    'eliminar_banner_rest' => 'Eliminar banner',
                ]
            ],
            'config_restaurante' => [
                'label' => 'Ajustes del Restaurante',
                'permissions' => [
                    'editar_mi_restaurante_rest' => 'Editar datos y logo del local',
                    'guardar_configuracion_impresion_cocina_rest' => 'Configurar impresión y KDS',
                    'guardar_configuracion_carta_web_rest' => 'Configurar Carta Web',
                    'guardar_configuracion_facturacion_rest' => 'Configurar facturación electrónica',
                ]
            ],
            'config_usuarios' => [
                'label' => 'Gestión de Empleados',
                'permissions' => [
                    'listar_usuarios_rest' => 'Listar usuarios',
                    'crear_usuario_rest' => 'Crear usuario',
                    'editar_usuario_rest' => 'Editar usuario',
                    'eliminar_usuario_rest' => 'Eliminar usuario',
                ]
            ],

            'comprobantes_electronicos' => [
                'label' => 'Facturación: Comprobantes',
                'permissions' => [
                    'ver_comprobantes_rest' => 'Ver listado de facturas y boletas',
                    'generar_comunicacion_baja_rest' => 'Anular comprobantes',
                    'descargar_comprobantes_xml_cdr_pdf_rest' => 'Descargar archivos XML, CDR y PDF',
                    'enviar_comprobante_sunat_rest' => 'Enviar comprobante a sunat',
                    'emitir_nota_rest' => 'Generar Nota',
                ]
            ],

            'notas_credito_debito' => [
                'label' => 'Facturación: Notas de Crédito/Débito',
                'permissions' => [
                    'ver_notas_creditos_debitos_rest' => 'Ver listado de notas de crédito y débito',
                    'descargar_notas_xml_cdr_pdf_rest' => 'Descargar archivos XML, CDR y PDF',
                ]
            ],

            'resumenes_diarios' => [
                'label' => 'Facturación: Resúmenes Diarios',
                'permissions' => [
                    'ver_resumenes_diarios_rest' => 'Ver listado de resúmenes diarios',
                    'generar_resumen_diario_rest' => 'Generar y enviar Resumen Diario',
                    'consultar_tiket_resumen_diario_rest' => 'Consultar Ticket de resumen diario',
                    'descargar_resumenes_xml_cdr_rest' => 'Descargar archivos XML, CDR',
                ]
            ],
            // MODULO DE ROLES Y PERMISOS
            'roles_permisos' => [
                'label' => 'Gestión de Roles',
                'permissions' => [
                    'listar_roles_rest' => 'Listar roles',
                    'crear_rol_rest' => 'Crear Rol',
                    'editar_rol_rest' => 'Editar Rol',
                    'anular_rol_rest' => 'Anular Rol',
                    'ver_rol_rest' => 'Ver Rol',
                ]
            ],


        ];

        // 3. Insertar a la base de datos
        foreach ($estructura as $keyModule => $data) {
            $moduleLabel = $data['label'];
            foreach ($data['permissions'] as $permissionName => $permissionDesc) {
                Permission::updateOrCreate(
                    ['name' => $permissionName],
                    [
                        'description'  => $permissionDesc,
                        'module'       => $keyModule,
                        'module_label' => $moduleLabel,
                        'scope'        => 'restaurant',
                        'guard_name'   => 'web'
                    ]
                );
            }
        }
    }
}
