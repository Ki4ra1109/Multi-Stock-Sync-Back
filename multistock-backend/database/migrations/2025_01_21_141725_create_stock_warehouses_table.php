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
        Schema::create('stock_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('thumbnail');
            $table->string('id_mlc');
            $table->string('title');
            $table->decimal('price_clp', 8, 2);
            $table->integer('warehouse_stock');
            $table->unsignedBigInteger('warehouse_id');
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_warehouses');
    }
};
