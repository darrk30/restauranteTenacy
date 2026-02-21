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
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_type')->default('Producto');
            $table->string('product_name');
            $table->decimal('price', 10, 2);
            $table->integer('cantidad')->default(1);
            $table->decimal('subTotal', 10, 2);
            $table->string('status')->default('pendiente');
            $table->string('notes')->nullable();
            $table->boolean('cortesia')->default(false); 
            $table->timestamp('fecha_envio_cocina')->nullable();
            $table->timestamp('fecha_listo')->nullable();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_actualiza_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
