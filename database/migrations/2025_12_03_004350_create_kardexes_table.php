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
        Schema::create('kardexes', function (Blueprint $table) {
            $table->id();

            // Producto / Variante
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            // Tipo de movimiento (compra, ajuste, venta, etc)
            $table->string('tipo_movimiento');
            $table->string('comprobante');
            
            // Cantidad movida
            $table->decimal('cantidad', 12, 3);

            // Costo unitario y saldo valorizado después del movimiento
            $table->decimal('costo_unitario', 10, 4)->default(0);
            $table->decimal('saldo_valorizado', 15, 4)->default(0);
            
            // Stock restante después del movimiento
            $table->decimal('stock_restante', 12, 3)->nullable();
            
            // Relación polimórfica (purchase, adjustment, sale)
            $table->nullableMorphs('modelo'); // modelo_id + modelo_type
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardexes');
    }
};
