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
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $table->double('stock_real')->default(0);
            $table->double('stock_reserva')->default(0);
            $table->double('min_stock')->default(0);
            $table->decimal('costo_promedio', 10, 4)->default(0);
            $table->decimal('valor_inventario', 10, 4)->default(0);
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->unique(['variant_id', 'restaurant_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
