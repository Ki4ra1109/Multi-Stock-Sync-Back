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
            $table->string('ml_id', 255)->index();
            $table->string('client_id', 255)->index();
            $table->string('title', 500);
            $table->integer('price');
            $table->string('currency_id', 3);
            $table->integer('available_quantity');
            $table->integer('sold_quantity');
            $table->text('thumbnail')->nullable();
            $table->text('permalink')->nullable();
            $table->string('status', 20);
            $table->string('category_id', 50);
            $table->timestamps();
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
