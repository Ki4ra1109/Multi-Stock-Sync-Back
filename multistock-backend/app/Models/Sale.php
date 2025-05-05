<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Sale extends Model
{
    use HasFactory;

    // Nombre de la tabla (opcional si es 'ventas', porque Eloquent lo asume)
    protected $table = 'sale';

    // Campos que se pueden asignar en masa
    protected $fillable = ['warehouse_id', 'cliente_id', 'products', 'amount', 'price_subtotal', 'price_total', 'type_emission', 'observation', 'name_companies'];
}