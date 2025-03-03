<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = ['nombre', 'precio', 'stock', 'categoria_id'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'categoria_id'); // Nombre correcto del modelo
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes', 'producto_id', 'atributo_id')->withPivot('valor');
    }
}
