<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class createNewClientController
{
    public function createNewClient(Request $request)
    {
        try {
            // Obtener el ID del tipo de cliente desde el request
            $tipoClienteId = (int) $request->input('tipo_cliente_id');

            if ($tipoClienteId === 1) {

                // Validar los datos de entrada
                $validatedData = $request->validate([
                    'extranjero' => 'required|boolean',
                    'rut' => 'required|string|max:255',
                    'razon_social' => 'required|string|max:255',
                    'giro' => 'required|string|max:255',
                    'nombres' => 'required|string|max:255',
                    'apellidos' => 'required|string|max:255',
                    'direccion' => 'required|string|max:255',
                    'comuna' => 'required|string|max:255',
                    'region' => 'required|string|max:255',
                    'ciudad' => 'required|string|max:255',
                    'tipo_cliente_id' => 'required|integer|exists:tipo_clientes,id',
                ]);

                // Crear un nuevo cliente
                $client = Client::create($validatedData);
            }
            else if ($tipoClienteId === 2) {
                // Validar los datos de entrada
                $validatedData = $request->validate([
                    'extranjero' => 'required|boolean',
                    'rut' => 'required|string|max:255',
                    'nombres' => 'required|string|max:255',
                    'apellidos' => 'required|string|max:255',
                    'direccion' => 'required|string|max:255',
                    'comuna' => 'required|string|max:255',
                    'region' => 'required|string|max:255',
                    'ciudad' => 'required|string|max:255',
                    'tipo_cliente_id' => 'required|integer|exists:tipo_clientes,id',
                ]);

                // Crear un nuevo cliente
                $client = Client::create($validatedData);
            } else {
                return response()->json(['message' => 'Tipo de cliente no válido.'], 422);
            }

            return response()->json(['message' => 'Cliente creado con éxito.', 'data' => $client], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el cliente.', 'error' => $e->getMessage()], 500);
        }
    }
}