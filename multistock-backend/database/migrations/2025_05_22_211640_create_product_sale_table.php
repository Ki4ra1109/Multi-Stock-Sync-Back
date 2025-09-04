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
        if (!Schema::hasTable('product_sale')) {
            Schema::create('product_sale', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('stock_warehouses')->onDelete('cascade');
                $table->foreignId('venta_id')->constrained('sale')->onDelete('cascade');
                $table->integer('cantidad');
                $table->decimal('precio_unidad', 10, 2);
                $table->decimal('precio_total',10,2);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sale');
    }
};
