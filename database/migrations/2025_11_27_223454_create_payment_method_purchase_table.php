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
        Schema::create('payment_method_purchase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete(); // opcional
            $table->decimal('monto', 12, 2)->nullable(); // opcional
            $table->string('referencia')->nullable(); // opcional
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete(); // opcional
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_method_purchase');
    }
};
