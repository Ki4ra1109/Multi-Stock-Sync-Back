<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoCliente extends Model
{
    use HasFactory;

    // New properties
    public $tipo; // empresa/persona

    public function clientes()
    
    {
        return $this->hasMany(Cliente::class, 'tipo_cliente_id', 'id');
    }

}
