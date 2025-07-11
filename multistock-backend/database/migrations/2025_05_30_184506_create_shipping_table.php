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
        if (!Schema::hasTable('shipping')) {
            Schema::create('shipping', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('apellido');
                $table->string('rut');
                $table->string('direccion');
                $table->string('telefono');
                $table->string('email');
                $table->unsignedInteger('venta_id');
                $table->string('ciudad');
                $table->unsignedBigInteger('warehouse_id');
                $table->unsignedBigInteger('client_id');
                $table->timestamps();

            // Clave foránea a sale (venta)
            $table->foreign('venta_id')
                ->references('id')->on('sale')
                ->onUpdate('restrict')
                ->onDelete('restrict');

            // Clave foránea a warehouses
            $table->foreign('warehouse_id')
                ->references('id')->on('warehouses')
                ->onUpdate('restrict')
                ->onDelete('restrict');

        // Clave foránea a clientes
        $table->foreign('client_id')
            ->references('id')->on('clientes')
            ->onUpdate('restrict')
            ->onDelete('restrict');
        });
    }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping');
    }
};
