<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('pack_composiciones', function (Blueprint $table) {
            $table->id();
            $table->string('sku_pack'); // SKU como string
            $table->string('sku_producto'); // SKU como string
            $table->integer('cantidad_pack');
            $table->timestamps();

            // Definir claves foráneas basadas en columnas string
            $table->foreign('sku_pack')->references('sku_pack')->on('pack_productos')->onDelete('cascade');
            $table->foreign('sku_producto')->references('sku')->on('productos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pack_composiciones');
    }
};
