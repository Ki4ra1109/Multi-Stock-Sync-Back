<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackProducto extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku_pack',
        'nombre'
    ];

    public function composiciones()
    {
        return $this->hasMany(PackComposicion::class, 'sku_pack', 'id');
    }
}
