<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class SizeGridRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'size_grid_id',
        'row_index',
        'attributes',
        'meli_row_id'
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    /**
     * Relación con la guía de tallas
     */
    public function sizeGrid()
    {
        return $this->belongsTo(SizeGrid::class);
    }

    /**
     * Obtener un atributo específico de la fila
     */
    public function getAttribute($attributeId)
    {
        $attributes = $this->attributes;

        foreach ($attributes as $attribute) {
            if ($attribute['id'] === $attributeId) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Obtener el valor de un atributo específico
     */
    public function getAttributeValue($attributeId)
    {
        $attribute = $this->getAttribute($attributeId);

        if ($attribute && isset($attribute['values'][0]['name'])) {
            return $attribute['values'][0]['name'];
        }

        return null;
    }
}
