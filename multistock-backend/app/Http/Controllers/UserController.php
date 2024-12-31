<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Get all users.
     */
    public function index()
    {
        $users = User::all(); // Obtiene todos los usuarios
        return response()->json($users); // Devuelve como JSON
    }

    /**
     * Create new user.
     */
    public function store(Request $request)
    {
        // Valitade request
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'telefono' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users',
            'nombre_negocio' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Create user
        $user = User::create([
            'nombre' => $validated['nombre'],
            'apellidos' => $validated['apellidos'],
            'telefono' => $validated['telefono'],
            'email' => $validated['email'],
            'nombre_negocio' => $validated['nombre_negocio'],
            'password' => Hash::make($validated['password']), // Password hashed
        ]);

        // Response with user data
        return response()->json(['user' => $user, 'message' => 'Usuario creado correctamente'], 201);
    }
}
