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
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('codigo_barras')->nullable();
            $table->string('internal_code')->nullable();
            // $table->decimal('extra_price')->nullable()->default(0);
            $table->string('status')->default('activo');
            $table->boolean('stock_inicial')->default(false);
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
