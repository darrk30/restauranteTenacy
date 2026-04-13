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
        Schema::create('credit_debit_notes', function (Blueprint $table) {
            $table->id();

            // =========================
            // 1. RELACIONES CLAVE
            // =========================
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Quién la emitió

            // 🟢 VÍNCULO AL COMPROBANTE ORIGINAL: Crucial para referenciar qué estamos anulando o modificando
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            // =========================
            // 2. DATOS DEL DOCUMENTO
            // =========================
            // '07' = Nota de Crédito | '08' = Nota de Débito
            $table->string('tipo_nota', 2);

            // FC01 (Crédito a Factura), BC01 (Crédito a Boleta), FD01 (Débito a Factura)...
            $table->string('serie', 4);
            $table->string('correlativo'); // Ej: 1, 2, 3
            $table->dateTime('fecha_emision');

            // =========================
            // 3. MOTIVOS SUNAT (Catálogos 09 y 10)
            // =========================
            // Ej: '01' (Anulación de la operación), '07' (Devolución por ítem)
            $table->string('cod_motivo', 2);
            $table->string('des_motivo');

            // =========================
            // 4. TOTALES MONETARIOS
            // =========================
            // Si anulas toda la factura, estos montos serán iguales a la factura original
            // Si es un descuento global (04), será solo el monto del descuento
            $table->decimal('op_gravada', 12, 2)->default(0);
            $table->decimal('monto_igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // =========================
            // 5. DETALLES (Opcional pero recomendado)
            // =========================
            // Guarda los ítems exactos que se están devolviendo o modificando
            $table->json('details')->nullable();

            // =========================
            // 6. COMUNICACIÓN CON SUNAT (Greenter)
            // =========================
            // Igual que en tus ventas, guarda el rastro de la factura electrónica
            $table->string('status_sunat')->default('registrado'); // 'registrado', 'aceptado', 'rechazado', 'error_api'
            $table->boolean('success')->default(false);
            $table->string('hash')->nullable();
            $table->string('path_xml')->nullable();
            $table->string('path_cdrZip')->nullable();
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->json('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->string('qr_data')->nullable(); // Para el PDF de la Nota

            // =========================
            // 7. RESTRICCIONES
            // =========================
            // Evitar que exista la misma nota de crédito 2 veces en el mismo local
            $table->unique(['restaurant_id', 'serie', 'correlativo'], 'unique_nota_per_tenant');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_debit_notes');
    }
};
