<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $table = 'product_attributes';
    protected $fillable = ['producto_id', 'atributo_id', 'valor'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'atributo_id');
    }
}
