<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        $user = $request->user();
        $userRoleName = $user && $user->rol ? $user->rol->nombre : null;

        if (!$user || !in_array($userRoleName, $roles)) {
            Log::warning('Intento de acceso no autorizado', [
                'user_id' => $user ? $user->id : null,
                'user_rol' => $userRoleName,
                'required_roles' => $roles,
                'route' => $request->path(),
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'No autorizado Rol'], 403);
        }
        return $next($request);
    }
}
