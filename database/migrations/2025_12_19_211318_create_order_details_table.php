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
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price', 10, 2);
            $table->integer('cantidad')->default(1);
            $table->string('status')->default('pendiente');
            $table->string('notes')->nullable();
            $table->boolean('cortesia')->default(false); 
            $table->timestamp('fecha_envio_cocina')->nullable();
            $table->timestamp('fecha_listo')->nullable();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
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
