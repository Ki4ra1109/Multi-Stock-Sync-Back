<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercadoLibreCredential extends Model

{
    protected $fillable = ['client_id', 'client_secret'];
}
