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
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->renameColumn('producto_id', 'product_id');
            $table->renameColumn('atributo_id', 'attribute_id');
            $table->string('value')->nullable()->change();
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('categoria_id', 'category_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('precio', 'price');
            $table->renameColumn('categoria_id', 'category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->renameColumn('product_id', 'producto_id');
            $table->renameColumn('attribute_id', 'atributo_id');
            $table->string('valor')->nullable(false)->change();
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('category_id', 'categoria_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('price', 'precio');
            $table->renameColumn('category_id', 'categoria_id');
        });
    }
};
