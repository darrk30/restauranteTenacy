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
        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->string('type_documento'); 
            $table->string('serie'); 
            $table->integer('current_number')->default(0); 
            $table->boolean('is_active')->default(true);
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->unique(['serie', 'restaurant_id', 'type_documento'], 'unique_serie_res_type');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_series');
    }
};
