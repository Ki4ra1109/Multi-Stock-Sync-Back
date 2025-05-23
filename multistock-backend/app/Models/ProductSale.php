<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSale extends Model
{
    use HasFactory;
    protected $table = 'product_sale';
    protected $fillable = [
        'venta_id',
        'product_id',
        'cantidad',
        'precio_unidad',
        'precio_total',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'venta_id');
    }

    public function stockWarehouse()
    {
        return $this->belongsTo(StockWarehouse::class, 'product_id');
    }
}
