<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $user = User::with('rol')->find($id);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        $userData = [
            'id' => $user->id,
            'nombre' => $user->nombre,
            'apellidos' => $user->apellidos,
            'telefono' => $user->telefono,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'role' => $user->rol->nombre,
        ];

        return response()->json([
            'user' => $userData

        ]);
    }

    /**
     * Create new user.
     */
    public function store(Request $request)
    {
        try {
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
                'role_id' => $validated['role_id'] ?? null
            ]);
            Log::info('Usuario creado', ['user_id' => $user->id]);
            // Response with user data
            return response()->json(['user' => $user, 'message' => 'Usuario creado correctamente'], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear usuario', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error interno al crear el usuario'], 500);
        }
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
        Log::info('Entrando a asignarRol', [
            'userId' => $userId,
            'request' => $request->all(),
            'auth_user' => Auth::user(),
        ]);

        if (optional(Auth::user())->id == $userId) {
            Log::warning('Intento de cambiar su propio rol', ['userId' => $userId]);
            return response()->json(['message' => 'No puedes cambiar tu propio rol.'], 403);
        }

        $request->validate([
            'role_id' => 'required|exists:rols,id',
        ]);

        $user = User::findOrFail($userId);
        $rol = \App\Models\Rol::findOrFail($request->role_id);




        $rolesPermitidosRRHH = [2, 3, 4];
        $userAuth = Auth::user();

        if (
            $userAuth->rol && $userAuth->rol->nombre === 'RRHH' &&
            !in_array($request->role_id, $rolesPermitidosRRHH)
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permisos para asignar este rol.'
            ], 403);
        }

        $user->role_id = $rol->id;
        $user->save();

        Log::info('Rol asignado correctamente', [
            'user_id' => $user->id,
            'role_id' => $rol->id,
        ]);

        return response()->json(['message' => 'Rol asignado correctamente']);
    }
}

