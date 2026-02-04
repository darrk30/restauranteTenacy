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
        Schema::create('type_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description', 255);
            $table->string('maximo_carateres')->nullable();
            $table->boolean('status');
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->unique(['restaurant_id', 'code'], 'code_unique_restaurant');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_documents');
    }
};
