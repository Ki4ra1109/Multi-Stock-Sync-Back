<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run()
    {
        Product::create([
            'nombre' => 'Producto de prueba',
            'precio' => 1000,
            'stock' => 10
        ]);
    }
}

