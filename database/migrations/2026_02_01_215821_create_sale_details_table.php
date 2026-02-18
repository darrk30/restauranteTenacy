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
        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name')->index();
            $table->integer('cantidad');

            // PRECIOS Y VALORES UNITARIOS (Alta Precisión: 6 decimales)
            $table->decimal('precio_unitario', 16, 6); // Con IGV
            $table->decimal('valor_unitario', 16, 6);  // Sin IGV (Base Imponible)
            $table->decimal('costo_unitario', 16, 6)->default(0); // Costo promedio ponderado

            // TOTALES DE LÍNEA (Dinero real: 2 decimales)
            $table->decimal('subtotal', 12, 2);    // Cantidad * Precio Unitario (Total con IGV)
            $table->decimal('valor_total', 12, 2); // Base Imponible de la línea (Sin IGV)
            $table->decimal('costo_total', 12, 2); // Inversión real en la línea

            $table->timestamps();
            $table->index(['sale_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_details');
    }
};
