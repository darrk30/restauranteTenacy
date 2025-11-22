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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2)->nullable();
            $table->string('slug')->unique();
            $table->string('image_path')->nullable(); 
            $table->foreignId('production_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->boolean('visible')->default(true);
            $table->text('description')->nullable();
             $table->string('status')->default('Activo'); // Visibilidad / estado
            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_end')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
