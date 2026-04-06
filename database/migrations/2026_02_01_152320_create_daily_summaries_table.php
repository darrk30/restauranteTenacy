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
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // 🟢 TIPO DE DOCUMENTO: Crucial para diferenciar el flujo
            // 'Summary' = Resumen Diario (Boletas) | 'Voided' = Comunicación de Baja (Facturas)
            $table->string('tipo_documento')->default('Summary');

            // Datos Generales (Lo que envías)
            $table->date('fecha_generacion'); // Fecha en la que se emitieron los comprobantes
            
            // 🟢 FECHAS ESPECÍFICAS: SUNAT y Greenter las llaman diferente según el tipo
            $table->date('fecha_resumen')->nullable();      // Usado solo para 'Summary'
            $table->date('fecha_comunicacion')->nullable(); // Usado solo para 'Voided'
            
            $table->string('correlativo'); // Ej: 001, 002
            $table->string('identificador'); // Ej: RC-20260405-001 (Resumen) o RA-20260405-001 (Baja)
            $table->json('details');
            
            // Respuesta Paso 1: Envío (Send) - SUNAT nos da un Ticket
            $table->string('ticket')->nullable();
            $table->string('hash')->nullable();
            $table->string('path_xml')->nullable();

            // Respuesta Paso 2: Consulta (Status) - Con el Ticket pedimos el CDR
            $table->string('status_sunat')->default('procesando'); // 'procesando' es ideal al crearlo
            $table->string('path_cdrZip')->nullable();
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->json('notes')->nullable();
            $table->text('error_message')->nullable();
            
            // Evitar duplicados por local (No puede haber dos RC-20260405-001 en el mismo restaurante)
            $table->unique(['restaurant_id', 'identificador'], 'unique_identificador_per_tenant');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};