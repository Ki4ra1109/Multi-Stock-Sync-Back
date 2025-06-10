<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SizeGrid extends Model
{
    use HasFactory;

    protected $table = 'size_grids';
    protected $fillable = ['name', 'value_name'];

    public function sizes()
    {
        return $this->hasMany(Size::class, 'size_grid_id');
    }
}
