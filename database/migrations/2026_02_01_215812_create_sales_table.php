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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            // Relaciones principales
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            //Cliente
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre_cliente')->nullable();

            //Mozo
            $table->foreignId('user_id')->constrained(); // Quién realizó la venta
            $table->foreignId('user_actualiza_id')->nullable()->constrained('users')->nullOnDelete();

            //Delivery
            $table->foreignId('delivery_id')->nullable()->constrained('users'); // Delivery
            $table->string('nombre_delivery')->nullable();

            $table->string('tipo_documento')->nullable();
            $table->string('numero_documento')->nullable();

            // Datos del Comprobante
            $table->string('tipo_comprobante'); // Boleta, Factura, Nota de Venta
            $table->string('serie');
            $table->string('correlativo');

            // Totales
            $table->decimal('monto_descuento', 12, 2)->default(0.00);
            $table->decimal('op_gravada', 12, 2);    // Base imponible
            $table->decimal('monto_igv', 12, 2);     // 18%
            $table->decimal('total', 12, 2);
            $table->string('cantida_items')->nullable();
            $table->decimal('costo_total', 12, 2);

            // Estado
            $table->string('status')->default('conpletado');
            $table->text('notas')->nullable();
            $table->string('canal')->nullable();
            $table->dateTime('fecha_emision')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
