<?php

namespace App\Http\Controllers\MercadoLibre\Connections;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\MercadoLibreCredential;

class ConexionTokenController extends Controller
{
    public function index()
    {
        $conexiones = MercadoLibreCredential::all();

        $resultado = $conexiones->map(function ($conexion) {
            $tokenVigente = $this->tokenEstaVigente($conexion->expires_at);

            return [
                'id' => $conexion->id,
                'client_id' => $conexion->client_id ?? null, // Agregamos client_id
                'nickname' => $conexion->nickname,
                'email' => $conexion->email,
                'tokenVigente' => $tokenVigente,
                'requiere_refresco' => !$tokenVigente, // Más claro para el frontend
            ];
        });

        return response()->json($resultado);
    }

    private function tokenEstaVigente($tokenExpiresAt)
    {
        if (!$tokenExpiresAt) {
            return false;
        }
        return Carbon::now()->lt(Carbon::parse($tokenExpiresAt));
    }
}
