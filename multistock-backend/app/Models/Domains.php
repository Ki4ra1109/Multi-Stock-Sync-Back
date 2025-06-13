<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domains extends Model
{

    use HasFactory;

    protected $fillable = [
        'domain_id',
        'domain_name',
    ];
}
