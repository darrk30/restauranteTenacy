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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            // RELACIONES
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            // DOCUMENTO
            $table->string('tipo_documento')->nullable(); // factura/boleta/etc
            $table->string('serie')->nullable();
            $table->string('numero')->nullable();

            // FECHAS
            $table->date('fecha_compra')->nullable();  // fecha de emisión

            // MONEDA Y TIPO DE CAMBIO
            $table->string('moneda')->default('PEN');

            // IMPORTES
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('costo_envio', 12, 2)->default(0);
            $table->decimal('saldo', 12, 2)->default(0);

            // ESTADO
            $table->string('estado_despacho'); //'recibido', 'por recibir', 'anulado'
            $table->string('estado_pago'); //['pagado', 'por pagar']
            $table->string('estado_comprobante')->default('aceptado'); //['aceptado', 'anulado']

            // OTROS
            $table->text('observaciones')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
