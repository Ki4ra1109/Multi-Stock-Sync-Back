<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientesController extends Controller
{
    // List all clients
    public function index()
    {
        $clientes = Cliente::with('tipoCliente')->get(); // Includes the relationship with TipoCliente
        return response()->json([
            'data' => $clientes
        ], 200);
    }

    // Create client
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'tipo_cliente_id' => 'required|exists:tipo_clientes,id',
            'extranjero' => 'required|boolean',
            'rut' => 'required_if:extranjero,false|max:12',
            'razon_social' => 'nullable|string|max:255',
            'giro' => 'nullable|string|max:255',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
            'exists' => 'El campo :attribute seleccionado no es válido.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $cliente = Cliente::create($validated);

        return response()->json([
            'message' => 'Cliente creado con éxito',
            'data' => $cliente
        ], 201);
    }

    // Show client by ID
    public function show($id)
    {
        $cliente = Cliente::with('tipoCliente')->findOrFail($id); // Includes the relationship
        return response()->json([
            'data' => $cliente
        ], 200);
    }

    // Update client
    public function update(Request $request, $id)
    {
        $cliente = Cliente::findOrFail($id);

        // Validate request
        $validator = Validator::make($request->all(), [
            'tipo_cliente_id' => 'exists:tipo_clientes,id',
            'extranjero' => 'boolean',
            'rut' => 'nullable|max:12',
            'razon_social' => 'nullable|string|max:255',
            'giro' => 'nullable|string|max:255',
            'nombres' => 'string|max:255',
            'apellidos' => 'string|max:255',
            'direccion' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
            'exists' => 'El campo :attribute seleccionado no es válido.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $cliente->update($validated);

        return response()->json([
            'message' => 'Cliente actualizado con éxito',
            'data' => $cliente
        ], 200);
    }

    // Delete client
    public function destroy($id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->delete();

        return response()->json([
            'message' => 'Cliente eliminado con éxito'
        ], 204);
    }
}
