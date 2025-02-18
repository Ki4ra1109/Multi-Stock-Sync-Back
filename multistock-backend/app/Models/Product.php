<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = ['nombre', 'precio', 'stock', 'categoria_id'];

    public function categories()
    {
        return $this->belongsTo(Categories::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attributes::class, 'product_attributes')->withPivot('valor');
    }
}
