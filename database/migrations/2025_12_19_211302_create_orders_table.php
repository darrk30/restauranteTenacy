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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('canal')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre_cliente')->nullable();
            $table->foreignId('delivery_id')->nullable()->constrained('users'); // Delivery
            $table->string('nombre_delivery')->nullable();
            $table->string('status')->default('pendiente');
            $table->string('status_llevar_delivery')->default('preparando'); //LLEVAR y DELIVERY> PREPARANDO, LLEVAR> ENTREGADO, DELIVERY> ENVIADO, ENTREGADO
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('igv', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamp('fecha_pedido')->useCurrent();
            $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unique(['restaurant_id', 'code']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
