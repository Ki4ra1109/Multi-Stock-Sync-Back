<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Sale extends Model
{
    use HasFactory;

    // Nombre de la tabla (opcional si es 'ventas', porque Eloquent lo asume)
    protected $table = 'sale';

    // Campos que se pueden asignar en masa
    protected $fillable = ['warehouse_id', 'client_id', 'amount_total_products', 'price_subtotal', 'price_final', 'type_emission', 'observation', 'name_companies', 'status_sale'];
    public function productSales()
    {
        return $this->hasMany(ProductSale::class, 'venta_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function stockWarehouses()
    {
        return $this->hasManyThrough(
            StockWarehouse::class,
            ProductSale::class,
            'venta_id', // Foreign key en product_sales
            'id', // Foreign key en stock_warehouses
            'id', // Local key en sales
            'product_id' // Local key en product_sales (apunta a stock_warehouses.id)
        );
    }

}
