<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    protected $table = 'sizes';
    protected $fillable = ['name', 'size_grid_id', 'value'];

    public function sizeGrid()
    {
        return $this->belongsTo(SizeGrid::class, 'size_grid_id');
    }
}
