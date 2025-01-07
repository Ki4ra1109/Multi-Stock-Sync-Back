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
        Schema::create('tipo_productos', function (Blueprint $table) {
            $table->id();
            $table->string('producto');
            $table->timestamps();
        });

        DB::table('tipo_productos')->insert([
            ['producto' => 'No especificado', 'created_at' => now(), 'updated_at' => now()] 
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_productos');
    }
};
