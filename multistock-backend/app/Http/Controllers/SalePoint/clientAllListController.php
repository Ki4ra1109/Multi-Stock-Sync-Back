<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;


class clientAllListController
{
    public function clientAllList(){

        $clients = Client::all();
        return response()->json($clients);
    }
}