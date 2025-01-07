<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    /**
     * Mass assignable fields.
     */
    protected $fillable = [
        'tipo_cliente_id', // Relationship with TipoCliente (empresa/persona)
        'extranjero',      // Boolean
        'rut',
        'razon_social',
        'giro',
        'nombres',
        'apellidos',
        'direccion',
        'comuna',
        'region',
        'ciudad',
    ];

    /**
     * Relationship with the TipoCliente table.
     */
    public function tipoCliente()
    {
        return $this->belongsTo(TipoCliente::class, 'tipo_cliente_id', 'id');
    }
}
