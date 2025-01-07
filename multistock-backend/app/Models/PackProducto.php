<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackProducto extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku_pack',
        'nombre',
    ];

    protected static function boot()
    {
        parent::boot();

        // Evento para generar el SKU automáticamente si no se proporciona
        static::creating(function ($packProducto) {
            if (empty($packProducto->sku_pack)) {
                $packProducto->sku_pack = self::generateSku($packProducto->nombre);
            }
        });
    }

    // Método para generar el SKU
    public static function generateSku($nombre)
    {
        $baseSku = strtoupper(substr($nombre, 0, 3)); // Primeras 3 letras del nombre
        $randomNumber = rand(1000, 9999);            // Número aleatorio de 4 dígitos
        return "{$baseSku}-{$randomNumber}";
    }

    /**
     * Relación con las composiciones del pack
     */
    public function composiciones()
    {
        return $this->hasMany(PackComposicion::class, 'sku_pack', 'sku_pack');
    }
}
