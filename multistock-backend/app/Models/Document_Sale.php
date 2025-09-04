<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document_Sale extends Model
{
    use HasFactory;

    // Nombre de la tabla (opcional si es 'documentos_venta', porque Eloquent lo asume)
    protected $table = 'document_sale';

    // Campos que se pueden asignar en masa
    protected $fillable = ['id_folio', 'documento'];
}