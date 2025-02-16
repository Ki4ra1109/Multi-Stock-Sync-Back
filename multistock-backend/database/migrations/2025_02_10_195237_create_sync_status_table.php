<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('estado_sincronizacion', function (Blueprint $table) {
            $table->id();
            $table->enum('estado', ['pendiente', 'en_progreso', 'completado', 'fallido'])->default('pendiente');
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fin')->nullable();
            $table->integer('total_productos')->default(0);
            $table->integer('productos_procesados')->default(0);
            $table->integer('duracion_estimada')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('estado_sincronizacion');
    }
};
