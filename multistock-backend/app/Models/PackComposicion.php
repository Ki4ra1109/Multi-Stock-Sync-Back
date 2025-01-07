<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackComposicion extends Model
{
    use HasFactory;

    // Explicit table name
    protected $table = 'pack_composiciones';

    protected $fillable = [
        'sku_pack',
        'sku_producto',
        'cantidad_pack',
    ];

    /**
     * Relación con PackProducto
     */
    public function pack()
    {
        return $this->belongsTo(PackProducto::class, 'sku_pack', 'sku_pack');
    }

    /**
     * Relación con Producto
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'sku_producto', 'sku');
    }
}
