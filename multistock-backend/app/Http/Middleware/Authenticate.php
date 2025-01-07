<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Manejar solicitudes no autenticadas.
     */
    protected function unauthenticated($request, array $guards)
    {
        // Retorna un mensaje JSON para solicitudes no autenticadas
        abort(response()->json(['message' => 'No autenticado'], 401));
    }

    /**
     * Este método no será necesario en una API pura, pero Laravel lo requiere.
     */
    protected function redirectTo($request)
    {
        // Este método no será utilizado, ya que es una API pura
        return null;
    }
}
