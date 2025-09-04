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
        Schema::create('size_grids', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain_id'); // SNEAKERS, PANTS, etc.
            $table->string('site_id'); // MLA, MLB, MLC, etc.
            $table->enum('measure_type', ['BODY_MEASURE', 'CLOTHING_MEASURE'])->default('BODY_MEASURE');
            $table->json('gender'); // Información del género
            $table->json('main_attribute'); // Atributo principal
            $table->string('meli_chart_id')->nullable(); // ID de la guía en MercadoLibre
            $table->string('client_id')->nullable(); // Para asociar con el usuario
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('_size__grid_');
    }
};
