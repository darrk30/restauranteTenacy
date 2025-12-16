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
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // estado actual
            $table->string('estado_mesa')->default('libre'); // libre, ocupada, pagando

            // cantidad de asientos fijos
            $table->integer('asientos')->default(1);

            // cuántas personas están sentadas actualmente
            $table->integer('numero_personas')->default(0);

            // cuándo empezó a estar ocupada (NULL si está libre)
           $table->time('ocupada_desde')->nullable();

            // status interno (activo/inactivo en el sistema)
            $table->boolean('status')->default(true);

            // relaciones
            $table->foreignId('floor_id')->constrained('floors')->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');

            // nombre único por restaurante
            $table->unique(['restaurant_id', 'name']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
