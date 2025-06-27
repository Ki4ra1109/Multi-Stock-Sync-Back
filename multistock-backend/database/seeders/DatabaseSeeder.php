<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
       
        DB::table('rols')->updateOrInsert(
            ['id' => 7],
            [
                'nombre' => 'admin',
                'is_master' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        $adminMasterRol = \App\Models\Rol::where('nombre', 'admin')->where('is_master', true)->first();

        if ($adminMasterRol) {
            $user = \App\Models\User::find(26);
            if ($user) {
                $user->role_id = $adminMasterRol->id;
                $user->save();
            }
        }
    }
}
