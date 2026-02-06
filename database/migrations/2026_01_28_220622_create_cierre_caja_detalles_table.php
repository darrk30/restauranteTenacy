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
        Schema::create('cierre_caja_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            // Los 3 datos claves del cuadre
            $table->decimal('monto_sistema', 10, 2)->default(0); // Lo que debería haber
            $table->decimal('monto_cajero', 10, 2)->default(0);  // Lo que contó el usuario
            $table->decimal('diferencia', 10, 2)->default(0);    // La resta
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cierre_caja_detalles');
    }
};
