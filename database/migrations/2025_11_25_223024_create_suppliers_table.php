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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            // DATOS GENERALES
            $table->string('name');                    // Razón social o nombre del proveedor
            $table->string('tipo_documento'); // RUC / DNI / CE
            $table->string('numero'); // Número de documento

            // CONTACTO
            $table->string('correo')->nullable();
            $table->string('telefono')->nullable();

            // UBICACIÓN
            $table->string('direccion')->nullable();
            $table->string('departamento')->nullable();
            $table->string('distrito')->nullable();
            $table->string('provincia')->nullable();

            // ESTADO
            $table->enum('status', ['activo', 'inactivo', 'archivado'])->default('activo');
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unique(['restaurant_id', 'numero']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
