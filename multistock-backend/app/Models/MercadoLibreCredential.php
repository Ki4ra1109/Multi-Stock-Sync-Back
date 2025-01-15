<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercadoLibreCredential extends Model
{
    protected $fillable = ['client_id', 'client_secret', 'access_token', 'refresh_token', 'expires_at'];

    public function isTokenExpired()
    {
        return $this->expires_at < now();
    }
}
