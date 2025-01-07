<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipo_clientes', function (Blueprint $table) {
            $table->id(); // 'id' field with type bigint unsigned
            $table->string('tipo'); // empresa/persona
            $table->timestamps();
        });
        
        DB::table('tipo_clientes')->insert([
            ['tipo' => 'Empresa', 'created_at' => now(), 'updated_at' => now()],
            ['tipo' => 'Persona', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_clientes');
    }
};
