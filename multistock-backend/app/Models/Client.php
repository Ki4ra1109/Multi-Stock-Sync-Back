<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    // Nombre de la tabla (opcional si es 'clientes', porque Eloquent lo asume)
    protected $table = 'clientes';

    // Campos que se pueden asignar en masa
    protected $fillable = ['extranjero', 'rut', 'razon_social', 'giro', 'nombres', 'apellidos', 'direccion', 'comuna', 'region', 'ciudad','tipo_cliente_id',];

}
