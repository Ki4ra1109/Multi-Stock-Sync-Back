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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Cambio de 'nombre' a 'name'
            $table->decimal('price', 10, 0); // Cambio 'precio' a 'price'
            $table->integer('stock')->default(0);
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade'); // Cambio 'categoria_id' a 'category_id'
            $table->timestamps();
        });
    }    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
