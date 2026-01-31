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
        Schema::create('cash_register_movements', function (Blueprint $table) {
            $table->id();

            // Vinculado a la sesión actual
            $table->foreignId('session_cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            // Usuario que hizo el movimiento
            $table->foreignId('usuario_id')->constrained('users');
            // ¿Entra o sale dinero?
            $table->enum('tipo', ['ingreso', 'egreso']); 
            
            // ¿Por qué? (apertura, venta, gasto, devolucion, retiro)
            $table->string('motivo'); 

            // ¿Cuánto?
            $table->decimal('monto', 10, 2);

            // 3. DESCRIPCIÓN MANUAL
            $table->text('observacion')->nullable();

            // 4. REFERENCIA AUTOMÁTICA (Polimorfismo)
            // Esto crea dos columnas: 'referencia_type' y 'referencia_id'
            // Sirve para saber si este dinero vino de una Venta (Order) o un Gasto (Expense)
            $table->nullableMorphs('referencia');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_register_movements');
    }
};
