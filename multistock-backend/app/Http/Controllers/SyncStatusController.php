<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SyncStatus;
use App\Jobs\SyncProductsJob;

class SyncStatusController extends Controller
{
    public function iniciarSincronizacion(Request $request)
    {
        $productos = $request->input('productos', []);

        if (SyncStatus::where('estado', 'en_progreso')->exists()) {
            return response()->json(['mensaje' => 'Ya hay una sincronización en progreso'], 409);
        }

        
        $sincronizacion = SyncStatus::create([
            'estado' => 'en_progreso',
            'inicio' => now(),
            'total_productos' => count($productos),
            'productos_procesados' => 0,
        ]);

        dispatch(new SyncProductsJob($productos));

        return response()->json(['mensaje' => 'Sincronización iniciada', 'id' => $sincronizacion->id]);
    }

    public function estadoSincronizacion()
    {
        $sincronizacion = SyncStatus::latest()->first();

        if (!$sincronizacion) {
            return response()->json(['mensaje' => 'No hay ninguna sincronización en progreso'], 404);
        }

        return response()->json([
            'estado' => $sincronizacion->estado,
            'inicio' => $sincronizacion->inicio,
            'fin' => $sincronizacion->fin,
            'total_productos' => $sincronizacion->total_productos,
            'productos_procesados' => $sincronizacion->productos_procesados,
        ]);
    }
}


