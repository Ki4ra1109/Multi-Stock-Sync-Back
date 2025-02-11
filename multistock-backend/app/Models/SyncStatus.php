<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstadoSincronizacion extends Model
{
    use HasFactory;

    // Campos que pueden ser asignados de forma masiva
    protected $fillable = [
        'estado',
        'hora_inicio',
        'hora_fin',
        'total_productos',
        'productos_procesados',
        'duracion_estimada'
    ];
}


