<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //Obtener todos los roles
        $roles = Rol::all();

        return response()->json([
            'message'=> 'Lista de roles obtenida correctamente',
            'data' => $roles
        ], 200);
        if(is_null($roles)){
            return response()->json([
                'message' => 'No se encontraron roles',
                'data' => []
            ], 404);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //Validar los datos de entrada
        $validator  = Validator::make($request->all(), [
            'nombre' => 'Required|string|max: 50',
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

        return response()->json(['rol' => $rol, 'message' => 'Rol creado correctamente'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Rol $rol)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Rol $rol)
    {
        //
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
        if(!$rol){
            return response()->json([
                'message' => 'Rol no encontrado'
            ], 404);
        }
        $rol->delete();
        return response()->json([
            'message' => 'Rol eliminado correctamente'
        ]);
    }
}
