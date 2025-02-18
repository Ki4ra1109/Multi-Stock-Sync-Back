<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $table = 'product_attributes';
    protected $fillable = ['producto_id', 'atributo_id', 'valor'];

    public function products()
    {
        return $this->belongsTo(Products::class);
    }

    public function attributes()
    {
        return $this->belongsTo(Attributes::class);
    }
}
