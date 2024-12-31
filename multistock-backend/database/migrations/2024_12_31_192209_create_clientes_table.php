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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('cliente');
            $table->foreignId('tipo_cliente_id') // Foreign bigint key
                  ->constrained('tipo_clientes') // Relates to the 'tipo_clientes' table
                  ->onDelete('cascade'); // Deletes clients if the tipo_cliente is deleted
            $table->boolean('extranjero')->default(false);
            $table->string('rut')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('giro')->nullable();
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();
            $table->string('direccion')->nullable();
            $table->string('comuna')->nullable();
            $table->string('region')->nullable();
            $table->string('ciudad')->nullable();
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
