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
        Schema::create('sale', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clientes')->onDelete('cascade');
            $table->integer('amount_total_products');
            $table->decimal('price_subtotal', 10, 2);
            $table->decimal('price_final', 10, 2);
            $table->string('type_emission')->nullable();
            $table->text('observation')->nullable();
            $table->string('name_companies')->nullable();
            $table->string('status_sale')->default('Pendiente');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
};
