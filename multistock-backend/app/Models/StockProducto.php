<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockProducto extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku_producto',
        'cantidad'
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'sku_producto', 'sku');
    }
}