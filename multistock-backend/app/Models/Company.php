<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    // Nombre de la tabla (opcional si es 'companies', porque Eloquent lo asume)
    protected $table = 'companies';

    // Campos que se pueden asignar en masa
    protected $fillable = ['name'];

    /**
     * Relación uno a muchos con Warehouse.
     * Una empresa puede tener muchas bodegas.
     */
    public function warehouses()
    {
        return $this->hasMany(Warehouse::class, 'assigned_company_id');
    }
}
