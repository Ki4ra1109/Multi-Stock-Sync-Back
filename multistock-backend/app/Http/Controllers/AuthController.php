<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * User login and token generation.
     */
    public function login(Request $request)
    {
        // Validación con mensajes personalizados
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser una dirección de correo válida.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser una cadena de texto.',
        ]);

        // Check if email or password is empty
        $validated = $request->all();

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