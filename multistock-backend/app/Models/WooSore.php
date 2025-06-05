<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class WooStore extends Model
{
    protected $fillable = [
        'name',
        'store_url',
        'consumer_key',
        'consumer_secret',
        'active',
    ];

    
    public function setConsumerKeyAttribute($value)
    {
        $this->attributes['consumer_key'] = Crypt::encryptString($value);
    }

    public function setConsumerSecretAttribute($value)
    {
        $this->attributes['consumer_secret'] = Crypt::encryptString($value);
    }

    
    public function getConsumerKeyAttribute($value)
    {
        return Crypt::decryptString($value);
    }

    public function getConsumerSecretAttribute($value)
    {
        return Crypt::decryptString($value);
    }
}
