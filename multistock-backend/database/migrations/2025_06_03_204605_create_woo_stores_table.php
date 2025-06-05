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
            $table->id();
            $table->string('name'); // Nombre identificador de la tienda 
            $table->string('store_url'); // URL de la tienda WooCommerce 
            $table->string('consumer_key'); // Clave pública para conexión
            $table->string('consumer_secret'); // Clave privada para conexión
            $table->boolean('active')->default(true); // Para activar/desactivar tiendas si es necesario
            $table->timestamps();
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
