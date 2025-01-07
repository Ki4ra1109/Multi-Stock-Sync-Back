<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'tipo_cliente_id' => 'required|exists:tipo_clientes,id', // Validate relationship with tipo_clientes
            'extranjero' => 'required|boolean',
            'rut' => 'required_if:extranjero,false|max:12', // Required if not foreign
            'razon_social' => 'nullable|string|max:255', // Optional for individuals
            'giro' => 'nullable|string|max:255',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
        ]);

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

        $validated = $request->validate([
            'tipo_cliente_id' => 'exists:tipo_clientes,id', // Validate relationship
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
        ]);

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
