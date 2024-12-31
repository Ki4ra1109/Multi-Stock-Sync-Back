<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class cliente extends Model
{
    use HasFactory;

    // New properties
    public $cliente;
    public $tipo_cliente; // empresa/persona
    public $extranjero; // si/no
    public $rut;
    public $razon_social;
    public $giro;
    public $nombres;
    public $apellidos;
    public $direccion;
    public $comuna;
    public $region;
    public $ciudad;

    public function tipoCliente()
    {
        return $this->belongsTo(TipoCliente::class, 'tipo_cliente_id', 'id');
    }

}
