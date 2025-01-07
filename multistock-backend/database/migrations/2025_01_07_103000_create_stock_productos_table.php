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
        Schema::create('stock_productos', function (Blueprint $table) {
            $table->id();
            $table->string('sku_producto'); // Cambiado a string
            $table->foreign('sku_producto')->references('sku')->on('productos')->onDelete('cascade');
            $table->integer('cantidad');
            $table->timestamps();
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_productos');
    }
};
