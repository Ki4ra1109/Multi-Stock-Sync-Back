<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SyncStatus;

class SyncStatusController extends Controller
{
    public function getStatus()
    {
        $sync = SyncStatus::orderBy('created_at', 'desc')->first();

        return response()->json([
            'status' => $sync ? $sync->status : 'idle',
            'processed' => $sync ? $sync->processed_products : 0,
            'total' => $sync ? $sync->total_products : 0
        ]);
    }
}

