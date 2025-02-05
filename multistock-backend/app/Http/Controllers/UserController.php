<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get all users.
     */
    public function index()
    {
        $users = User::all(); // Get users list
        return response()->json($users); // Return JSON
    }

    /**
     * Create new user.
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'telefono' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string|min:6',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
            'email' => 'El campo :attribute debe ser una dirección de correo válida.',
            'unique' => 'El campo :attribute ya ha sido registrado.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'confirmed' => 'La confirmación de :attribute no coincide.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        // Create user
        $user = User::create([
            'nombre' => $validated['nombre'],
            'apellidos' => $validated['apellidos'],
            'telefono' => $validated['telefono'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']), // Password hashed
        ]);

        // Response with user data
        return response()->json(['user' => $user, 'message' => 'Usuario creado correctamente'], 201);
    }
}
