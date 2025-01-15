<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    // Allow mass assignment for these fields
    protected $fillable = ['name'];

    /**
     * Define a one-to-many relationship with Warehouse.
     */
    public function warehouses()
    {
        return $this->hasMany(Warehouse::class, 'assigned_company_id');
    }
}