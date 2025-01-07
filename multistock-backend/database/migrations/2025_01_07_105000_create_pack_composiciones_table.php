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
        Schema::create('pack_composiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_pack')->constrained('pack_productos')->onDelete('cascade');
            $table->foreignId('sku_producto')->constrained('productos')->onDelete('cascade');
            $table->integer('cantidad_pack');
            $table->timestamps();
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pack_composiciones');
    }
};
