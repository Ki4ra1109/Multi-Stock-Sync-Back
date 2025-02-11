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
        Schema::create('estado_sincronizacion', function (Blueprint $table) {
            $table->id();
            $table->enum('estado', ['pendiente', 'en_progreso', 'completado', 'fallido'])->default('pendiente');
            $table->timestamp('hora_inicio')->nullable();
            $table->timestamp('hora_fin')->nullable();
            $table->integer('total_productos')->default(0);
            $table->integer('productos_procesados')->default(0);
            $table->integer('duracion_estimada')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_status');
    }
};
