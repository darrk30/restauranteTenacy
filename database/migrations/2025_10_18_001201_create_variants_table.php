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
            $table->string('sku')->nullable();
            $table->string('internal_code')->nullable();
            $table->decimal('extra_price')->nullable()->default(0);
            $table->integer('stock_real')->nullable()->default(0);        // Stock total
            $table->integer('stock_virtual')->nullable()->default(0);        // Stock virtual
            $table->integer('stock_minimo')->nullable()->default(0); // Stock mínimo
            $table->string('status')->default('Activo');
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
