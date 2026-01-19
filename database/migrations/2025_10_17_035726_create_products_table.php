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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');                      // Nombre del producto
            $table->string('code')->nullable();
            $table->string('slug');            // Slug para rutas
            $table->string('image_path')->nullable();            // Ruta de la imagen
            $table->string('type'); // Tipo de producto
            $table->text('description')->nullable();
            $table->foreignId('production_id')->nullable()->constrained()->onDelete('restrict'); // Área de producción
            $table->foreignId('brand_id')->nullable()->constrained()->onDelete('restrict'); // Marca
            $table->foreignId('unit_id')->nullable()->constrained()->onDelete('restrict'); // Marca
            $table->string('status')->default('activo'); // Visibilidad / estado
            $table->decimal('price')->default(0); // Precio base
            $table->boolean('cortesia')->default(false);  // Es producto de cortesía
            $table->boolean('control_stock')->default(false);  // Control de stock 
            $table->boolean('visible')->default(true);  // Visibilidad en el frontend
            $table->integer('order')->nullable();          // Orden de aparición
            $table->boolean('venta_sin_stock')->default(false);  // Permitir pedidos sin stock
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade'); // Restaurante
            $table->unique(['restaurant_id', 'code']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
