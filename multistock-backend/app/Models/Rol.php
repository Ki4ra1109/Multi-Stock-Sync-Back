<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'nombre',
        'is_master',
    ];

    // Relación con Users
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
