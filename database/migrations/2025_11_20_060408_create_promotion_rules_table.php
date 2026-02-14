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
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type');       // horario, fecha, cupon, limite, condicion, etc.
            $table->string('key');        // start_time, weekday, code, max_uses, etc.
            $table->string('operator')->nullable(); // =, >, >=, in, between, etc.
            $table->json('value')->nullable();    // string, number, json, etc.
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            // $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_rules');
    }
};
