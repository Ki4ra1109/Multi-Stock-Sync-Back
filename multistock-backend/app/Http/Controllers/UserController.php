<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Get all users.
     */
    public function usersList()
    {
        $users = User::all(); // Get users list
        return response()->json($users); // Return JSON
    }

    /**
     * Get a single user by ID.
     */
    public function show($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        
        return response()->json($user);
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
            'role_id' => 'nullable|exists:rols,id'  // lo ve el admin
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
            'role_id' => $validated['role_id'] ?? null,
        ]);

        // Response with user data
        return response()->json(['user' => $user, 'message' => 'Usuario creado correctamente'], 201);
    }

    /**
     * Update user data.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Validate request
        $validator = Validator::make($request->all(), [
            'nombre' => 'nullable|string|max:255',
            'apellidos' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|string|email|max:255|unique:users,email',
            'role_id' => 'nullable|integer|exists:rols,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'user' => $user
        ], 200);
    }

    /**
     * Delete a user.
     */
    public function delete($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $user->delete();
        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

   

    
    public function asignarRol(Request $request, $userId)
    {
        if (optional(Auth::user())->id == $userId) {
            return response()->json(['message' => 'No puedes cambiar tu propio rol.'], 403);
        }

        $request->validate([
            'role_id' => 'required|exists:rols,id',
        ]);

        $user = User::findOrFail($userId);
        $rol = \App\Models\Rol::findOrFail($request->role_id);

        $user->role_id = $rol->id;
        $user->save();

        return response()->json(['message' => 'Rol asignado correctamente']);
    }
}

