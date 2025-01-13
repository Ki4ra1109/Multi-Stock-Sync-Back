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
        Schema::create('mercado_libre_credentials', function (Blueprint $table) {
            $table->id(); 
            $table->string('client_id'); // MercadoLibre Client ID
            $table->string('client_secret'); // MercadoLibre Secret Key
            $table->timestamps(); // Created_at and Updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mercado_libre_credentials');
    }
};
