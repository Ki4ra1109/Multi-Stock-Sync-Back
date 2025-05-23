<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockWarehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_mlc',
        'title',
        'price',
        'condicion',
        'currency_id',
        'listing_type_id',
        'available_quantity',
        'warehouse_id',
        'category_id',
        'attribute',
        'pictures',
        'sale_terms',
        'shipping',
        'description',
    ];

    /**
     * Define a many-to-one relationship with Warehouse.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    public function productSales()
    {
        return $this->hasMany(ProductSale::class, 'product_id');
    }
    public function company()
{
    return $this->hasOneThrough(
        Company::class,
        Warehouse::class,
        'id', // Foreign key en warehouses
        'id', // Foreign key en companies
        'warehouse_id', // Local key en stock_warehouses
        'assigned_company_id' // Cambiado de 'company_id' a 'assigned_company_id'
    );
}
}
