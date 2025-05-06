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
    protected $fillable = ['warehouse_id', 'client_id', 'products', 'amount_total_products', 'price_subtotal', 'price_final', 'type_emission', 'observation', 'name_companies', 'status_sale'];
}