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
        Schema::create('concepto_cajas', function (Blueprint $table) {
            $table->id();
            // VINCULACIÓN
             $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('session_cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users'); // Cajero que registra
            // Opcional: Si usas tabla de personal separada de users
            $table->foreignId('personal_id')->nullable()->constrained('users'); 

            // DATOS PRINCIPALES
            $table->enum('tipo_movimiento', ['ingreso', 'egreso']);
            
            // Subtipo para Egresos: 'compras', 'servicios', 'remuneracion', 'otros'
            // Para Ingresos puede quedar null o usar 'venta', 'ingreso_extra'
            $table->string('categoria')->nullable(); 

            $table->decimal('monto', 10, 2);
            $table->text('motivo')->nullable(); // Descripción
            $table->string('estado')->default('confirmado');

            // DATOS ESPECÍFICOS (CAMPOS DINÁMICOS)
            // Para Ingresos: "¿Quién entregó la plata?"
            // Para Egresos (No Remuneración): "¿A quién se entregó?"
            $table->string('persona_externa')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concepto_cajas');
    }
};
