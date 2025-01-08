<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarcasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('marcas')->insert([
            [
             'nombre' => 'Sin Marca',
             'imagen' => '',
             'created_at' => now(),
             'updated_at' => now()
            ]
        ]);
    }
}
