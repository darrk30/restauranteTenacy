# 🚀 Tukipu Cloud - SaaS Multitenant para Restaurantes

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Filament](https://img.shields.io/badge/Filament_PHP-v3-EBB308?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)

Tukipu es una plataforma **SaaS (Software as a Service)** de alto rendimiento para la gestión operativa de restaurantes. Utiliza una arquitectura de **Multitenancy por Subdominios**, permitiendo un aislamiento total de datos entre establecimientos.

# 📊 Módulos Estratégicos del Sistema

### 🏢 Arquitectura Multitenancy

- **Aislamiento Total:** Cada restaurante posee sus propios usuarios, productos, ventas y configuraciones de forma independiente, garantizando que la data de un comercio nunca se mezcle con otro.
- **Subdominios Dinámicos:** Implementación de acceso personalizado vía `{slug}.tukipu.cloud`, configurado dinámicamente en el `PanelProvider` de Filament.
- **Tenant Separation:** Filtrado automático de datos a nivel de base de datos a través de la columna `tenant_id` para asegurar la privacidad y seguridad entre comercios.

### 💰 Análisis de Rentabilidad (Reporte de Ganancias)

- **Cálculo de Utilidad:** Deducción automática del `costo_total` de insumos sobre el ingreso bruto de la venta para obtener la **Ganancia Neta** real.
- **Filtros de Precisión:** Motor de búsqueda avanzado por rango de fechas e intervalos de tiempo exactos, con soporte nativo para formato de 12 horas (AM/PM).
- **Indicadores Visuales:** Sistema de Badges dinámicos que resaltan márgenes de utilidad mayores al 30% en color verde para una rápida toma de decisiones.

### 📦 Gestión de Inventario (Kardex)

- **Control de Stock en Tiempo Real:** Actualización automática y precisa de existencias al completar ventas, procesar facturas de compra o realizar anulaciones de pedidos.
- **Historial de Movimientos:** Registro pormenorizado (Kardex) de entradas, salidas, mermas y transferencias para auditorías detalladas de almacén.

### 📑 Comprobantes y Facturación

- **Enums Centralizados:** Uso de clases PHP nativas (Enums) para estandarizar los tipos de documentos: **Factura, Boleta, Ticket y Nota de Venta**.
- **Validación de Correlativos:** Lógica de negocio integrada que asegura que cada establecimiento mantenga su propia numeración y series de forma única y consecutiva.

### 🍱 Gestión de Comandas y Salones

- **Monitorización de Mesas:** Interfaz gráfica interactiva para visualizar el estado de las mesas en tiempo real (libres, ocupadas, con cuenta impresa o por pagar).
- **Dashboard Estadístico:** Widgets con información crítica en tiempo real: platos más vendidos, ingresos del turno actual y monitoreo de pedidos pendientes en cocina.

---

## 🛠️ Stack Tecnológico

- **Backend:** Laravel 11 / PHP 8.2+
- **Panel Administrativo:** Filament PHP v3
- **Base de Datos:** MySQL / MariaDB
- **Frontend:** Livewire, Alpine.js & Tailwind CSS
- **Servidor:** VPS con Nginx y PHP-FPM

---

## 📋 Requisitos del Servidor (VPS)

- **PHP 8.2+** con extensiones: `bcmath`, `ctype`, `fileinfo`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`.
- **Composer 2.x**.
- **Nginx** configurado para **Wildcard Subdomains** (`*.tukipu.cloud`).

---

## ⚙️ Proceso de Despliegue (Actualización en VPS)

Para garantizar un entorno de producción estable y libre de errores de permisos, se debe seguir este flujo estrictamente:

### 1. Sincronización de Código

```bash
# Entrar al directorio y limpiar cambios locales accidentales
cd /home/tukipu/htdocs/tukipu.cloud
git checkout -- .
git pull origin master
```
