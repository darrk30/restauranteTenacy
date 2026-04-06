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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_comercial')->nullable();
            $table->string('ruc')->maxLength(11);
            $table->string('address');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('department')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('ubigeo')->nullable();
            $table->string('status')->default('activo');
            $table->string('carta_activa_cliente')->default('activo');
            $table->string('carta_activa_admin')->default('activo');
            $table->string('logo')->nullable();
            $table->string('slug')->nullable();
            $table->string('api_token')->nullable();
            $table->boolean('production')->default(false);
            $table->string('sol_user')->nullable();
            $table->string('sol_pass')->nullable();
            $table->string('cert_path')->nullable();
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->string('cod_local')->default('0000');
            $table->string('country_code')->default('PE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
