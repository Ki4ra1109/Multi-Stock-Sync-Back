<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    // Allow mass assignment for these fields
    protected $fillable = ['name', 'location', 'assigned_company_id'];

    // Set default values for attributes
    protected $attributes = [
        'location' => 'no especificado',
    ];

    /**
     * Define a many-to-one relationship with Company.
     */
    
    public function company()
    {
        return $this->belongsTo(Company::class, 'assigned_company_id');
    }

    /**
     * Define a one-to-many relationship with StockWarehouse.
     */

    public function stockWarehouses()
    {
        return $this->hasMany(StockWarehouse::class, 'warehouse_id');
    }

}
