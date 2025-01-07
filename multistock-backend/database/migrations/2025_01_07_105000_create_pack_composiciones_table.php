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
            $table->string('sku_pack'); // Definimos como string
            $table->foreign('sku_pack')->references('sku_pack')->on('pack_productos')->onDelete('cascade');
            $table->string('sku_producto'); // También como string
            $table->foreign('sku_producto')->references('sku')->on('productos')->onDelete('cascade');
            $table->integer('cantidad_pack');
            $table->timestamps();
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
