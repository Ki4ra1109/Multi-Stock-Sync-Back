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
        Schema::create('mercado_libre_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('access_token'); // Access Token
            $table->string('refresh_token')->nullable(); // Token to refresh the access token
            $table->timestamp('expires_at'); // Date and hour when the token expires
            $table->timestamps(); // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mercado_libre_tokens');
    }
};
