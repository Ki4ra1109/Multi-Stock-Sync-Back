<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrandsSeeder extends Seeder
{
    /**
     * Run the database seeds.
    */

    public function run(): void
    {
        DB::table('brands')->insert([
            [
             'name' => 'Sin Marca',
             'image' => 'https://picsum.photos/400',
             'created_at' => now(),
             'updated_at' => now()
            ]
        ]);
    }
}
