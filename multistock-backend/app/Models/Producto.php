<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'sku',
        'tipo',
        'marca',
        'control_stock',
        'precio',
        'permitir_venta_no_stock',
        'nombre_variante',
        'control_series',
        'permitir_venta_decimales'
    ];

    public function tipoProducto()
    {
        return $this->belongsTo(TipoProducto::class, 'tipo', 'id');
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'marca', 'id');
    }

    public function stock()
    {
        return $this->hasOne(StockProducto::class, 'sku_producto', 'sku'); // SKU foreign key
    }
}
