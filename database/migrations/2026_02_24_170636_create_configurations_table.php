<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();

            // Relación con el restaurante
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            // ==========================================
            // 1. CONFIGURACIONES DE IMPRESIÓN
            // ==========================================
            $table->boolean('impresion_directa_precuenta')->default(false)
                ->comment('Impresión directa de pre-cuenta sin cuadro de diálogo');

            $table->boolean('impresion_directa_comprobante')->default(false)
                ->comment('Impresión directa de comprobante (Boleta/Factura)');

            $table->boolean('impresion_directa_comanda')->default(true)
                ->comment('Impresión automática de comanda a las tiqueteras de cocina/bar');

            $table->boolean('mostrar_pantalla_cocina')->default(false)
                ->comment('Mostrar pedidos en pantalla digital de cocina (KDS) en lugar de imprimir');

            $table->boolean('mostrar_modal_impresion_comanda')->default(false);
            $table->boolean('mostrar_modal_impresion_precuenta')->default(false);
            $table->boolean('mostrar_modal_impresion_comprobante')->default(false);

            // ==========================================
            // 2. CONFIGURACIONES DE LA CARTA DIGITAL / WEB
            // ==========================================
            $table->boolean('guardar_pedidos_web')->default(true)
                ->comment('True: Guarda en BD el pedido. False: Solo envía mensaje a WhatsApp');

            $table->boolean('habilitar_delivery_web')->default(true)
                ->comment('Habilitar opción de Delivery en la carta digital');

            $table->boolean('habilitar_recojo_web')->default(true)
                ->comment('Habilitar opción de Recojo en Tienda en la carta digital');

            // ==========================================
            // 3. FACTURACIÓN E IMPUESTOS
            // ==========================================
            $table->boolean('precios_incluyen_impuesto')->default(true)
                ->comment('¿Los precios registrados en el menú ya incluyen IGV/IVA?');

            $table->boolean('envio_boletas')->default(false)
                ->comment('Envio de boletas automatico');

            $table->boolean('envio_facturas')->default(false)
                ->comment('Envio de facturas automatico');

            $table->decimal('porcentaje_impuesto', 5, 2)->default(18.00)
                ->comment('Porcentaje de impuesto (Ej: 18 para IGV en Perú)');

            $table->string('api_token')->nullable();
            $table->string('api_url')->nullable();
            $table->boolean('production')->default(false);
            $table->string('sol_user')->nullable();
            $table->string('sol_pass')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }
};
