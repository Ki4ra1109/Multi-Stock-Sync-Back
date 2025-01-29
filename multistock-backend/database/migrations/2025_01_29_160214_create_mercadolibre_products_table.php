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
        Schema::create('mercadolibre_products', function (Blueprint $table) {
            $table->id();
            $table->string('ml_id')->unique();          // MLC1480082905
            $table->string('client_id');                // 2999003706392728
            $table->string('title', 500);               // Pantaleta Mujer Lady Genny...
            $table->decimal('price', 10, 2);            // 9690
            $table->string('currency_id', 3);           // CLP
            $table->integer('available_quantity');      // 10
            $table->integer('sold_quantity');           // 0
            $table->text('thumbnail')->nullable();      // http://http2.mlstatic.com/...
            $table->text('permalink')->nullable();      // https://articulo.mercadolibre.cl/...
            $table->string('status', 20);              // active
            $table->string('category_id', 50);         // MLC440333
            $table->timestamps();

            $table->index('client_id');
            $table->index('ml_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mercadolibre_products');
    }
};
