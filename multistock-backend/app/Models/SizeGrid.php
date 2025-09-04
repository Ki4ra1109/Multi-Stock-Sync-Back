<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SizeGrid extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'domain_id',
        'site_id',
        'measure_type',
        'gender',
        'main_attribute',
        'meli_chart_id',
        'client_id'
    ];

    protected $casts = [
        'gender' => 'array',
        'main_attribute' => 'array',
    ];

    /**
     * Relación con las filas de tallas
     */
    public function sizes()
    {
        return $this->hasMany(SizeGridRow::class);
    }

    /**
     * Relación con las filas ordenadas por índice
     */
    public function sizeRows()
    {
        return $this->hasMany(SizeGridRow::class)->orderBy('row_index');
    }
}
