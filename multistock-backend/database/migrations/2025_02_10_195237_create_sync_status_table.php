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
    Schema::create('sync_status', function (Blueprint $table) {
        $table->id();
        $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending');
        $table->timestamp('start_time')->nullable();
        $table->timestamp('end_time')->nullable();
        $table->integer('total_products')->default(0);
        $table->integer('processed_products')->default(0);
        $table->integer('estimated_duration')->nullable(); // Tiempo en segundos
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_status');
    }
};
