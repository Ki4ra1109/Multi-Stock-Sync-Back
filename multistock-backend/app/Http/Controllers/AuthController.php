<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

    /**
     * Change user password.
     */
    public function changePassword(Request $request)
    {
        Log::info('Intento de cambio de contraseña', [
            'user_id' => Auth::id(),
            'request' => $request->all()
        ]);

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = User::find(Auth::id());
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            Log::warning('Contraseña actual incorrecta', ['user_id' => $user->id]);
            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        Log::info('Contraseña cambiada correctamente', ['user_id' => $user->id]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}