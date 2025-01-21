<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockWarehouse extends Model
{
    use HasFactory;

    protected $fillable = ['thumbnail', 'id_mlc', 'title', 'price_clp', 'warehouse_stock' , 'warehouse_id'];

    /**
     * Define a many-to-one relationship with Warehouse.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
