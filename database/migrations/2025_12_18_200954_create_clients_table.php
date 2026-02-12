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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_document_id')->constrained('type_documents');
            $table->string('numero', 20);
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('Activo');
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->unique(['restaurant_id', 'type_document_id', 'numero'], 'unique_client_in_restaurant');
            $table->index(['restaurant_id', 'numero']);
            $table->index(['restaurant_id', 'nombres']);
            $table->index(['restaurant_id', 'razon_social']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
