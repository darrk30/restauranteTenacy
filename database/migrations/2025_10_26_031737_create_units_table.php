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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->decimal('quantity', 15, 5)->default(1);
            $table->boolean('is_base')->default(false);
            $table->foreignId('reference_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('unit_category_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // ✅ Restricción única compuesta por restaurante
            $table->unique(['restaurant_id', 'code'], 'units_restaurant_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
