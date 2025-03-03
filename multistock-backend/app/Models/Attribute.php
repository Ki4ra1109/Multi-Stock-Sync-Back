<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory;

    protected $table = 'attributes';
    protected $fillable = ['nombre', 'categoria_id'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'categoria_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attributes')->withPivot('valor');
    }
}