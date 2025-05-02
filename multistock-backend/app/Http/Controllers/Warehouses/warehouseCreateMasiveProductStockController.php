<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Knackline\ExcelTo\ExcelTo;

class warehouseCreateMasiveProductStockController{

    public function warehouseCreateMasiveProductStock(Request $request){
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
                    'id_mlc' => 'nullable|string|max:255',
                    'warehouse_id' => 'required|integer',
                    'title' => 'required|string|max:255',
                    'price' => 'required|numeric',
                    'available_quantity' => 'required|integer',
                    'condicion' => 'required|string|max:255',
                    'currency_id' => 'required|string|max:255',
                    'listing_type_id' => 'required|string|max:255',
                    'category_id' => 'nullable|string|max:255',
                    'attribute' => 'nullable|array',
                    'pictures' => 'nullable|array',
                    'sale_terms' => 'nullable|array',
                    'shipping' => 'nullable|array',
                    'description' => 'nullable|string',
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
                    'id_mlc' => $validated['id_mlc'] ?? null,
                    'warehouse_id' => $validated['warehouse_id'],
                    'title' => $validated['title'],
                    'price' => $validated['price'],
                    'condicion' => $validated['condicion'],
                    'currency_id' => $validated['currency_id'],
                    'listing_type_id' => $validated['listing_type_id'],
                    'available_quantity' => $validated['available_quantity'],
                    'category_id' => $validated['category_id'] ?? null,
                    'attribute' => isset($validated['attribute']) ? json_encode($validated['attribute']) : json_encode([]),
                    'pictures' => isset($validated['pictures']) ? json_encode($validated['pictures']) : json_encode([]),
                    'sale_terms' => isset($validated['sale_terms']) ? json_encode($validated['sale_terms']) : json_encode([]),
                    'shipping' => isset($validated['shipping']) ? json_encode($validated['shipping']) : json_encode([]),
                    'description' => $validated['description'] ?? '',
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