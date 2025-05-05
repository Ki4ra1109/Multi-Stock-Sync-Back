<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Knackline\ExcelTo\ExcelTo;

class warehouseCreateMasiveProductStockController{

    public function warehouseCreateMasiveProductStock(Request $request, $warehouseId){
    try {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        if ($request->hasFile('excel_file')) {
            $excelFile = $request->file('excel_file');
            $path = $excelFile->getRealPath();
            $jsonData = ExcelTo::json($path);
        
            // Validar que sea un array de arrays
            if (!is_array($jsonData) || !is_array($jsonData[0])) {
                return response()->json([
                    'message' => 'El formato del archivo no es válido.',
                ], 400);
            }

            // Guardar cada fila como un nuevo producto
            foreach($jsonData as $json){
                $validator = Validator::make($row, [
                    'title' => 'required|string|max:255',
                    'precio' => 'required|numeric',
                    'cantidad' => 'required|integer',
                    'condicion' => 'required|string|max:255',
                    'tipo_moneda' => 'required|string|max:255',
                    'tipo_publicidad' => 'required|string|max:255',
                    'categoria_id' => 'nullable|string|max:255',
                    'atributos' => 'nullable|array',
                    'imagenes' => 'nullable|array',
                    'sale_terms' => 'nullable|array',
                    'envio' => 'nullable|array',
                    'descripcion' => 'nullable|string',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $index + 1,
                        'errors' => $validator->errors(),
                    ];
                    continue;
                }

                $validated = $validator->validated();

                StockWarehouse::create([
                    'warehouse_id' => $warehouseId, // pedir el campo cuando se envie
                    'title' => $validated['title'],
                    'price' => $validated['precio'],
                    'condicion' => $validated['condicion'],
                    'currency_id' => $validated['tipo_moneda'],
                    'listing_type_id' => $validated['tipo_publicidad'],
                    'available_quantity' => $validated['cantidad'],
                    'category_id' => $validated['categoria_id'] ?? null,
                    'attribute' => isset($validated['atributos']) ? json_encode($validated['atributos']) : json_encode([]),
                    'pictures' => isset($validated['imagenes']) ? json_encode($validated['imagenes']) : json_encode([]),
                    'sale_terms' => isset($validated['sale_terms']) ? json_encode($validated['sale_terms']) : json_encode([]),
                    'shipping' => isset($validated['envio']) ? json_encode($validated['envio']) : json_encode([]),
                    'description' => $validated['descripcion'] ?? '',
                ]);
            }

            if (!empty($errors)) {
                return response()->json([
                    'message' => 'Algunas filas no se pudieron importar.',
                    'errors' => $errors,
                ], 207);
            }
        
            return response()->json([
                'message' => 'Productos creados con éxito en la base de datos.',
            ], 201);
        }else {
            return response()->json([
                'message' => 'No se ha subido ningún archivo.',
            ], 400);
        }
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Datos inválidos.',
            'errors' => $e->errors(),
        ], 422);
    }
    catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error al procesar el archivo.',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}