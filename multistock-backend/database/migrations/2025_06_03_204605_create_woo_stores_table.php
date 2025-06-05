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
        Schema::create('woo_stores', function (Blueprint $table) {
            $table->id(); // ID autoincremental
            $table->string('name'); // Nombre amigable de la tienda (ej: "Tienda Principal")
            $table->string('store_url'); // URL base del sitio WordPress con WooCommerce (ej: https://ejemplo.com)
            $table->text('consumer_key'); // Clave pública API REST WooCommerce (encriptada)
            $table->text('consumer_secret'); // Clave secreta API REST WooCommerce (encriptada)
            $table->boolean('active')->default(true); // Control de activación de la conexión
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woo_stores');
    }
};
