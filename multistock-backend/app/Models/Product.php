<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncStatus extends Model
{
    use HasFactory;

    protected $table = 'sync_status';
    protected $fillable = ['status', 'start_time', 'end_time', 'total_products', 'processed_products'];
}