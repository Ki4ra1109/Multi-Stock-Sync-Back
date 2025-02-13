<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncStatus extends Model
{
    use HasFactory;

    protected $table = 'estado_sincronizacion'; // Table name

    protected $fillable = [
        'estado',
        'inicio',
        'fin',
        'total_productos',
        'productos_procesados',
        'duracion_estimada',
    ];
}