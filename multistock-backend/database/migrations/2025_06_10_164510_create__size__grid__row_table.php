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
        Schema::create('size_grid_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_grid_id')->constrained()->onDelete('cascade');
            $table->integer('row_index'); // Índice de la fila
            $table->json('attributes'); // Atributos de la fila en formato JSON
            $table->string('meli_row_id')->nullable(); // ID de la fila en MercadoLibre
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('_size__grid__row');
    }
};
