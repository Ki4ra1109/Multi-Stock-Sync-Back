<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoProductosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tipo_productos')->insert([
            [
                'producto' => 'No especificado',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

