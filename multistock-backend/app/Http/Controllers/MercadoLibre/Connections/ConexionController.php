<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conexion;
use Carbon\Carbon;

class ConexionController extends Controller
{
    public function index()
    {
        $conexiones = Conexion::all();

        $resultado = $conexiones->map(function ($conexion) {
            $tokenVigente = $this->tokenEstaVigente($conexion->token_expires_at);

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
