<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\AuthController;

class AuthController extends Controller
{
    /**
     * User login and token generation.
     */
    public function login(Request $request)
    {
        // Check if the request is empty or has invalid JSON
        if (!$request->isJson() || empty($request->all())) {
            return response()->json(['message' => 'Solicitud inválida'], 400);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Check if email or password is empty
        if (empty($validated['email']) || empty($validated['password'])) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // Find user by email
        $user = User::where('email', $validated['email'])->first();

        // Check if user exists
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Check credentials
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout and token revocation.
     */
    public function logout(Request $request)
    {
        // Revoke token
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
