<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            
            $roles = Rol::all();

            return response()->json([
                'message'=> 'Lista de roles obtenida correctamente',
                'data' => $roles
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener la lista de roles', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error al obtener la lista de roles'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //Validar los datos de entrada
        $validator  = Validator::make($request->all(), [
            'nombre' => 'required|string|max:50',
        ],[
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
        ]);

        if($validator->fails()){
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        //Crear un nuevo rol
        $rol = Rol::create([
            'nombre' => $validated['nombre'],
        ]);

        Log::info('Rol creado', ['rol_id' => $rol->id, 'nombre' => $rol->nombre]);

        return response()->json(['rol' => $rol, 'message' => 'Rol creado correctamente'], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Rol $rol)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //Buscar el rol por ID
        $rol = Rol::find($id);
        if (!$rol) {
            Log::warning('Intento de eliminar rol inexistente', ['rol_id' => $id]);
            return response()->json([
                'message' => 'Rol no encontrado'
            ], 404);
        }

        
        if ($rol->nombre === 'admin' && $rol->is_master) {
            $user = auth()->guard()->user();
            if (!$user || !$user->rol || !$user->rol->is_master) {
                return response()->json([
                    'message' => 'No se puede eliminar el rol admin master'
                ], 403);
            }
        }

        $rol->delete();

        Log::info('Rol eliminado', ['rol_id' => $rol->id, 'nombre' => $rol->nombre]);

        return response()->json([
            'message' => 'Rol eliminado correctamente'
        ]);
    }

    public function delete($id)
    {
        $user = User::with('rol')->find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

       
        if ($user->rol && $user->rol->nombre === 'admin' && $user->rol->is_master) {
            return response()->json([
                'message' => 'No se puede eliminar el usuario admin master.'
            ], 403);
        }

        $authUser = request()->user();
        if (
            $user->rol && $user->rol->nombre === 'admin' && !$user->rol->is_master &&
            (!$authUser->rol || !$authUser->rol->is_master)
        ) {
            return response()->json([
                'message' => 'Solo el admin master puede eliminar a otros admins.'
            ], 403);
        }

        $user->delete();
        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }
}
