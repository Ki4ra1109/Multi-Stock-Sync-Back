<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoProducto extends Model
{
    use HasFactory;

    protected $fillable = [
        'producto'
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class, 'tipo', 'id');
    }
}