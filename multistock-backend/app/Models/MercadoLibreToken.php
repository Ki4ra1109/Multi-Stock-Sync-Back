<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercadoLibreToken extends Model
{
    protected $table = 'mercado_libre_tokens'; 
    protected $fillable = ['access_token', 'refresh_token', 'expires_at'];

    /**
     * Check if token is expired
     */
    public function isExpired()
    {
        return $this->expires_at < now();
    }
}
