<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipping extends Model
{
    use HasFactory;

    protected $table='direccion';
    protected $fillable=['nombre','apellido','rut','direccion', 'telefono','email', 'venta_id','ciudad'];

    public function client()
    {
        return $this->belongsTo(Sale::class, 'venta_id');
    }
}
