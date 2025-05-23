<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CreateProductsMasiveController extends Controller
{
    public function uploadExcel(Request $request, $client_id)
    {
        // Validar que se haya subido un archivo Excel
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
        ]);

        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token no válido o expirado.'], 401);
        }

        try {
            // Leer el archivo Excel
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // La primera fila contiene los headers
            $headers = array_shift($rows);
            $headers = array_map('trim', $headers);

            // Validar que el Excel tenga las columnas necesarias
            $requiredColumns = [
                'title', 'price', 'currency_id', 'available_quantity',
                'condition', 'listing_type_id', 'category_id', 'pictures'
            ];

            $missingColumns = array_diff($requiredColumns, $headers);
            if (!empty($missingColumns)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Faltan columnas requeridas en el Excel: ' . implode(', ', $missingColumns)
                ], 422);
            }

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque empezamos desde la fila 2 (después del header)

                // Saltar filas vacías
                if (empty(array_filter($row))) {
                    continue;
                }

                // Convertir la fila en un array asociativo
                $productData = array_combine($headers, $row);

                // Procesar el producto
                $result = $this->processProduct($productData, $credentials, $rowNumber);
                $results[] = $result;

                if ($result['status'] === 'success') {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }

            return response()->json([
                'status' => 'completed',
                'message' => "Procesamiento completado. Éxitos: {$successCount}, Errores: {$errorCount}",
                'summary' => [
                    'total_processed' => count($results),
                    'successful' => $successCount,
                    'errors' => $errorCount
                ],
                'details' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error al procesar archivo Excel:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar el archivo Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processProduct($productData, $credentials, $rowNumber)
    {
        try {
            // Limpiar y preparar los datos
            $data = $this->prepareProductData($productData);

            // Validar los datos del producto
            $validation = $this->validateProductData($data);
            if ($validation['hasErrors']) {
                return [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'message' => 'Errores de validación: ' . implode(', ', $validation['errors']),
                    'data' => $data
                ];
            }

            $validated = $validation['data'];
            $hasCatalog = !empty($validated['catalog_product_id']);

            // Verificar si la categoría requiere catálogo
            $catalogRequired = false;
            if (!$hasCatalog && isset($validated['category_id'])) {
                $catalogCheck = $this->checkCatalogRequired($validated);
                $catalogRequired = $catalogCheck['required'];

                if ($catalogRequired) {
                    if (!empty($catalogCheck['catalog_product_id'])) {
                        $validated['catalog_product_id'] = $catalogCheck['catalog_product_id'];
                        $hasCatalog = true;
                    } else {
                        return [
                            'row' => $rowNumber,
                            'status' => 'error',
                            'message' => 'Esta categoría requiere publicar en el catálogo y no se encontró un catalog_product_id válido.',
                            'data' => $data
                        ];
                    }
                }
            }

            // Crear el payload para la API
            $payload = $this->buildPayload($validated, $hasCatalog, $catalogRequired);

            Log::info("Payload para producto en fila {$rowNumber}:", $payload);

            // Enviar a MercadoLibre
            $response = Http::withToken($credentials->access_token)
                ->post('https://api.mercadolibre.com/items', $payload);

            if ($response->failed()) {
                Log::error("Error al crear producto en fila {$rowNumber}:", [
                    'payload' => $payload,
                    'ml_response' => $response->json()
                ]);

                return [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'message' => 'Error al crear el producto en MercadoLibre',
                    'ml_error' => $response->json(),
                    'data' => $data
                ];
            }

            return [
                'row' => $rowNumber,
                'status' => 'success',
                'message' => 'Producto creado exitosamente',
                'ml_response' => $response->json(),
                'data' => $data
            ];

        } catch (\Exception $e) {
            Log::error("Error procesando producto en fila {$rowNumber}:", [
                'error' => $e->getMessage(),
                'data' => $productData
            ]);

            return [
                'row' => $rowNumber,
                'status' => 'error',
                'message' => 'Error interno: ' . $e->getMessage(),
                'data' => $productData
            ];
        }
    }

    private function prepareProductData($productData)
    {
        // Procesar pictures (convertir string separado por comas a array)
        if (isset($productData['pictures']) && is_string($productData['pictures'])) {
            $pictureUrls = array_filter(array_map('trim', explode(',', $productData['pictures'])));
            $productData['pictures'] = array_map(function($url) {
                return ['source' => $url];
            }, $pictureUrls);
        }

        // Procesar attributes si existe
        if (isset($productData['attributes']) && is_string($productData['attributes'])) {
            $attributes = json_decode($productData['attributes'], true);
            $productData['attributes'] = is_array($attributes) ? $attributes : null;
        }

        // Procesar sale_terms si existe
        if (isset($productData['sale_terms']) && is_string($productData['sale_terms'])) {
            $saleTerms = json_decode($productData['sale_terms'], true);
            $productData['sale_terms'] = is_array($saleTerms) ? $saleTerms : null;
        }

        // Procesar shipping
        if (isset($productData['shipping']) && is_string($productData['shipping'])) {
            $shipping = json_decode($productData['shipping'], true);
            $productData['shipping'] = is_array($shipping) ? $shipping : ['mode' => 'me2', 'free_shipping' => false];
        } elseif (!isset($productData['shipping'])) {
            $productData['shipping'] = ['mode' => 'me2', 'free_shipping' => false];
        }

        // Limpiar valores vacíos y convertir tipos
        foreach ($productData as $key => $value) {
            if (is_string($value)) {
                $productData[$key] = trim($value);
                if ($productData[$key] === '') {
                    $productData[$key] = null;
                }
            }
        }

        // Convertir tipos numéricos
        if (isset($productData['price'])) {
            $productData['price'] = (float) $productData['price'];
        }
        if (isset($productData['available_quantity'])) {
            $productData['available_quantity'] = (int) $productData['available_quantity'];
        }

        return $productData;
    }

    private function validateProductData($data)
    {
        $hasCatalog = !empty($data['catalog_product_id']);

        $rules = [
            'catalog_product_id' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'currency_id' => 'required|string',
            'available_quantity' => 'required|integer|min:1',
            'condition' => 'required|in:new,used',
            'listing_type_id' => 'required|string',
            'pictures' => 'required|array|min:1',
            'pictures.*.source' => 'required|url',
            'shipping' => 'required|array',
            'sale_terms' => 'nullable|array',
        ];

        if (!$hasCatalog) {
            $rules['title'] = 'required|string|max:60';
            $rules['category_id'] = 'required|string';
            $rules['description'] = 'nullable|string';
            $rules['attributes'] = 'nullable|array';
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return [
                'hasErrors' => true,
                'errors' => $validator->errors()->all(),
                'data' => null
            ];
        }

        return [
            'hasErrors' => false,
            'errors' => [],
            'data' => $validator->validated()
        ];
    }

    private function checkCatalogRequired($validated)
    {
        try {
            $attributeResponse = Http::get("https://api.mercadolibre.com/categories/{$validated['category_id']}/attributes");

            $catalogRequired = false;
            if ($attributeResponse->successful()) {
                foreach ($attributeResponse->json() as $attr) {
                    if (!empty($attr['tags']['catalog_required'])) {
                        $catalogRequired = true;
                        break;
                    }
                }
            }

            $catalogProductId = null;
            if ($catalogRequired) {
                $searchCatalog = Http::get("https://api.mercadolibre.com/products/search", [
                    'category' => $validated['category_id'],
                    'q' => $validated['title']
                ]);

                if ($searchCatalog->successful() && !empty($searchCatalog->json()['results'])) {
                    $catalogProductId = $searchCatalog->json()['results'][0]['id'];
                }
            }

            return [
                'required' => $catalogRequired,
                'catalog_product_id' => $catalogProductId
            ];

        } catch (\Exception $e) {
            Log::error('Error verificando catálogo requerido:', ['error' => $e->getMessage()]);
            return [
                'required' => false,
                'catalog_product_id' => null
            ];
        }
    }

    private function buildPayload($validated, $hasCatalog, $catalogRequired)
    {
        $payload = [
            'price' => $validated['price'],
            'currency_id' => $validated['currency_id'],
            'available_quantity' => $validated['available_quantity'],
            'listing_type_id' => $validated['listing_type_id'],
            'condition' => $validated['condition'],
            'pictures' => $validated['pictures'],
            'shipping' => $validated['shipping'],
        ];

        if ($hasCatalog && !empty($validated['catalog_product_id']) && $validated['catalog_product_id'] !== 'undefined') {
            $payload['catalog_product_id'] = $validated['catalog_product_id'];
            $payload['catalog_listing'] = true;
        } else {
            $payload['title'] = $validated['title'];
            $payload['category_id'] = $validated['category_id'];

            if (!empty($validated['description'])) {
                $payload['description'] = ['plain_text' => $validated['description']];
            }

            if ($catalogRequired) {
                $payload['family_name'] = $validated['title'];
            }

            if (!empty($validated['attributes'])) {
                $payload['attributes'] = $validated['attributes'];
            }
        }

        if (!empty($validated['sale_terms'])) {
            $payload['sale_terms'] = $validated['sale_terms'];
        }

        // Asegurar que category_id esté presente
        if (!empty($validated['category_id'])) {
            $payload['category_id'] = $validated['category_id'];
        }

        return $payload;
    }

    public function downloadTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Headers del Excel
            $headers = [
                'title',
                'price',
                'currency_id',
                'available_quantity',
                'condition',
                'listing_type_id',
                'category_id',
                'description',
                'pictures',
                'shipping',
                'attributes',
                'sale_terms',
                'catalog_product_id'
            ];

            // Escribir headers
            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Agregar una fila de ejemplo
            $exampleData = [
                'Producto de Ejemplo',
                '100.00',
                'ARS',
                '10',
                'new',
                'gold_special',
                'MLA1744',
                'Descripción del producto de ejemplo',
                'https://ejemplo.com/imagen1.jpg,https://ejemplo.com/imagen2.jpg',
                '{"mode":"me2","free_shipping":false}',
                '[{"id":"BRAND","value_name":"Marca Ejemplo"}]',
                '[{"id":"WARRANTY_TYPE","value_name":"Garantía del vendedor"}]',
                ''
            ];

            foreach ($exampleData as $index => $value) {
                $sheet->setCellValue(chr(65 + $index) . '2', $value);
            }

            // Auto ajustar columnas
            foreach (range('A', chr(65 + count($headers) - 1)) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

            $fileName = 'plantilla_productos_mercadolibre.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error generando plantilla Excel:', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al generar la plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
}
