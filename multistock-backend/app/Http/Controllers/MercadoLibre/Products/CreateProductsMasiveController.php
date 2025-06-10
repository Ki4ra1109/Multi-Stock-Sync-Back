<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\MercadoLibre\Products\getAtributosCategoriaController;
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
                'title',
                'price',
                'currency_id',
                'available_quantity',
                'condition',
                'listing_type_id',
                'category_id',
                'pictures'
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
            $productData['pictures'] = array_map(function ($url) {
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
    private function getAtributos($clientId, $categoryId)
    {
        $cred = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$cred) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

        if ($cred->isTokenExpired()) {
            $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $cred->client_id,
                'client_secret' => $cred->client_secret,
                'refresh_token' => $cred->refresh_token,
            ]);

            if ($refreshResponse->failed()) {
                return response()->json(['error' => 'No se pudo refrescar el token'], 401);
            }

            $data = $refreshResponse->json();
            $cred->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);
        }

        $response = Http::withToken($cred->access_token)
            ->get("https://api.mercadolibre.com/categories/{$categoryId}/attributes");

        $responseData = $response->json();

        // Inyectar valores manuales en SIZE_GRID_ID si vienen vacíos
        foreach ($responseData as &$attr) {
            if ($attr['id'] === 'SIZE_GRID_ID' && empty($attr['values'])) {
                $attr['values'] = [
                    ['id' => '336013', 'name' => 'Ropa interior - CHILE (numérica)'],
                    ['id' => '336014', 'name' => 'Ropa superior - CHILE (letras)'],
                    ['id' => '336015', 'name' => 'Calzas - CHILE (numérica)'],
                ];
                $attr['tags'] = ['required' => true];
            }
        }

        return $responseData;
    }

    public function downloadTemplate($clientId, $categoryId)
    {
        try {
            $attributes = $this->getAtributos($clientId, $categoryId);
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            // === PRIMERA PESTAÑA: PLANTILLA ===
            $this->createInstructionsSheet($spreadsheet, $categoryId);

            // === SEGUNDA PESTAÑA: VALORES PERMITIDOS ===
            $this->createValuesSheet($spreadsheet, $attributes);

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Plantilla');

            // Headers básicos
            $headers = [
                'title',
                'price',
                'currency_id',
                'available_quantity',
                'condition',
                'listing_type_id',
                'description',
                'pictures',
                'local_pick_up',
                'free_shipping',
                'catalog_product_id',
                'category_id',
                'warranty_type',
                'warranty_days'

            ];

            // Agregar headers para atributos requeridos
            foreach ($attributes as $attribute) {
                if (isset($attribute['tags']['required']) && $attribute['tags']['required']) {
                    $headers[] = $attribute['name'] ?? $attribute['id'];
                }
            }

            // Escribir headers
            foreach ($headers as $index => $header) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($column . '1', $header);
                $sheet->getStyle($column . '1')->getFont()->setBold(true);

                // Prellenar category_id en la primera fila de datos con el valor del parámetro
                if ($header === 'category_id') {
                    $sheet->setCellValue($column . '2', $categoryId);
                    // Aplicar el valor a más filas para facilitar el uso
                    for ($row = 2; $row <= 1001; $row++) {
                        $sheet->setCellValue($column . $row, $categoryId);
                    }
                }
            }

            // Auto ajustar columnas
            for ($i = 0; $i < count($headers); $i++) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            // En la función downloadTemplate(), después de crear la hoja 'Plantilla':

            // Configurar dimensiones y estilos
            $sheet = $spreadsheet->getSheetByName('Plantilla');

            // 1. Ajustar anchos de columna específicos
            $sheet->getColumnDimension('A')->setWidth(40);  // title (columna A)
            $sheet->getColumnDimension('H')->setWidth(60);  // description (columna H)
            $sheet->getColumnDimension('I')->setWidth(50);  // pictures (columna I)

            // 2. Configurar wrap text y altura para descripción
            $descriptionCol = array_search('description', $headers) + 1;
            $descriptionLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($descriptionCol);

            // Aplicar estilo wrap text a toda la columna descripción
            $sheet->getStyle($descriptionLetter . '2:' . $descriptionLetter . '1000')
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

            // Establecer altura de fila para descripción (aproximadamente 4 líneas)
            for ($row = 2; $row <= 1000; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(20);
            }

            // 3. Añadir comentario flotante para pictures (URLs)
            $picturesCol = array_search('pictures', $headers) + 1;
            $picturesLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($picturesCol);

            $comment = $sheet->getComment($picturesLetter . '1');
            $comment->getText()->createTextRun("Ingrese URLs de imágenes separadas por comas\nEjemplo: url1.jpg,url2.jpg");
            $comment->setWidth('300pt');
            $comment->setHeight('50pt');

            // 4. Configurar autoajuste para las demás columnas
            foreach ($headers as $index => $header) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                if (!in_array($header, ['title', 'description', 'pictures'])) {
                    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                }
            }

            // 5. Ajustar zoom para mejor visualización
            $sheet->getSheetView()->setZoomScale(90);

            // === CUARTA PESTAÑA: HOJA OCULTA PARA VALORES ===
            $hiddenSheet = $spreadsheet->createSheet();
            $hiddenSheet->setTitle('_Valores');
            $this->createHiddenValueLists($hiddenSheet, $attributes);
            $hiddenSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

            // === AGREGAR VALIDACIONES DE DATOS ===
            $this->addDataValidations($sheet, $headers, $attributes);

            $spreadsheet->setActiveSheetIndex(0);

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $fileName = "plantilla_productos_{$categoryId}_" . date('Ymd_His') . ".xlsx";
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

    /**
     * Crear listas de valores en hoja oculta - VERSIÓN SIMPLIFICADA (SOLO NAMES)
     */
    private function createHiddenValueLists($hiddenSheet, $attributes)
    {
        $currentColumn = 1;

        // === CURRENCY_ID ===
        $hiddenSheet->setCellValue('A1', 'currency_id');
        $hiddenSheet->setCellValue('A2', 'USD');
        $hiddenSheet->setCellValue('A3', 'CLP');

        // === CONDITION ===
        $hiddenSheet->setCellValue('B1', 'condition');
        $hiddenSheet->setCellValue('B2', 'new');
        $hiddenSheet->setCellValue('B3', 'used');

        // === LISTING_TYPE_ID ===
        $hiddenSheet->setCellValue('C1', 'listing_type_id');
        $hiddenSheet->setCellValue('C2', 'gold_special');
        $hiddenSheet->setCellValue('C3', 'gold_pro');
        $hiddenSheet->setCellValue('C4', 'gold');
        $hiddenSheet->setCellValue('C5', 'silver');
        $hiddenSheet->setCellValue('C6', 'bronze');
        $hiddenSheet->setCellValue('C7', 'free');

        // === WARRANTY_TYPE ===
        $hiddenSheet->setCellValue('D1', 'warranty_type');
        $hiddenSheet->setCellValue('D2', 'Garantía de fábrica');
        $hiddenSheet->setCellValue('D3', 'Garantía del vendedor');

        // === WARRANTY_DAYS ===
        $hiddenSheet->setCellValue('E1', 'warranty_days');
        $hiddenSheet->setCellValue('E2', '30');
        $hiddenSheet->setCellValue('E3', '60');
        $hiddenSheet->setCellValue('E4', '90');
        $hiddenSheet->setCellValue('E5', '180');
        $hiddenSheet->setCellValue('E6', '365');

        // === LOCAL_PICK_UP ===
        $hiddenSheet->setCellValue('F1', 'local_pick_up');
        $hiddenSheet->setCellValue('F2', 'true');
        $hiddenSheet->setCellValue('F3', 'false');

        // === FREE_SHIPPING ===
        $hiddenSheet->setCellValue('G1', 'free_shipping');
        $hiddenSheet->setCellValue('G2', 'true');
        $hiddenSheet->setCellValue('G3', 'false');

        $currentColumn = 8;

        // === ATRIBUTOS DE MERCADOLIBRE (SOLO NAMES) ===
        foreach ($attributes as $attribute) {
            if (isset($attribute['tags']['required']) && $attribute['tags']['required'] && !empty($attribute['values'])) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentColumn);
                $attributeName = $attribute['name'] ?? $attribute['id'];

                // Header
                $hiddenSheet->setCellValue($columnLetter . '1', $attribute['id']);

                // Solo guardar los names (ya no necesitamos IDs)
                $row = 2;
                foreach ($attribute['values'] as $value) {
                    $hiddenSheet->setCellValue($columnLetter . $row, $value['name'] ?? $value['id']);
                    $row++;
                }

                $currentColumn++;
            }
        }
    }

    /**
     * Agregar validaciones de datos - VERSIÓN SIMPLIFICADA (SOLO NAMES)
     */
    private function addDataValidations($sheet, $headers, $attributes)
    {
        $maxRows = 1000;

        $attributeMap = [];
        foreach ($attributes as $attribute) {
            if (isset($attribute['tags']['required']) && $attribute['tags']['required'] && !empty($attribute['values'])) {
                $attributeName = $attribute['name'] ?? $attribute['id'];
                $attributeMap[$attributeName] = $attribute;
            }
        }

        foreach ($headers as $index => $header) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $range = $columnLetter . '2:' . $columnLetter . $maxRows;

            Log::info("Procesando validación para header: {$header}");

            switch ($header) {
                case 'currency_id':
                    $this->addListValidation($sheet, $range, ['USD', 'CLP'], 'Seleccione una moneda válida');
                    break;

                case 'condition':
                    $this->addListValidation($sheet, $range, ['new', 'used'], 'Seleccione: new o used');
                    break;

                case 'listing_type_id':
                    $listingTypes = ['gold_special', 'gold_pro', 'gold', 'silver', 'bronze', 'free'];
                    $this->addListValidation($sheet, $range, $listingTypes, 'Seleccione un tipo de publicación');
                    break;
                case 'local_pick_up':
                    $this->addListValidation($sheet, $range, ['true', 'false'], 'Seleccione true o false');
                    break;

                case 'free_shipping':
                    $this->addListValidation($sheet, $range, ['true', 'false'], 'Seleccione true o false');
                    break;

                case 'warranty_type':
                    $warrantyTypes = ['Garantía de fábrica', 'Garantía del vendedor'];
                    $this->addListValidation($sheet, $range, $warrantyTypes, 'Seleccione un tipo de garantía');
                    break;

                case 'warranty_days':
                    // Validación numérica para días de garantía
                    $validation = $sheet->getCell($columnLetter . '2')->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_WHOLE);
                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(true);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle('Valor inválido');
                    $validation->setError('Debe ser un número entero entre 1 y 365');
                    $validation->setPromptTitle('Días de garantía');
                    $validation->setPrompt('Ingrese el número de días de garantía (1-365)');
                    $validation->setFormula1(1);
                    $validation->setFormula2(365);
                    $sheet->setDataValidation($range, $validation);
                    break;

                case 'category_id':
                    break;

                default:
                    if (isset($attributeMap[$header])) {
                        $attribute = $attributeMap[$header];
                        $names = array_column($attribute['values'], 'name');
                        $this->addListValidation($sheet, $range, $names, "Seleccione un valor válido para {$header}");
                    }
                    break;
            }
        }
    }
    private function addListValidation($sheet, $range, $values, $promptMessage = 'Seleccione un valor de la lista')
    {
        try {
            // Crear la lista de valores como string separado por comas
            $valuesList = '"' . implode(',', $values) . '"';

            // Obtener la primera celda del rango para aplicar la validación
            $firstCell = explode(':', $range)[0];
            $validation = $sheet->getCell($firstCell)->getDataValidation();

            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $validation->setAllowBlank(false);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Valor inválido');
            $validation->setError('Debe seleccionar un valor de la lista desplegable.');
            $validation->setPromptTitle('Valores permitidos');
            $validation->setPrompt($promptMessage);
            $validation->setFormula1($valuesList);

            // Aplicar la validación a todo el rango
            $sheet->setDataValidation($range, $validation);

            Log::info("Validación aplicada exitosamente", [
                'range' => $range,
                'values_count' => count($values),
                'formula' => $valuesList
            ]);
        } catch (\Exception $e) {
            Log::error('Error aplicando validación de lista', [
                'range' => $range,
                'values_count' => count($values),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function createValuesSheet($spreadsheet, $attributes)
    {
        $valuesSheet = $spreadsheet->createSheet();
        $valuesSheet->setTitle('Valores Permitidos');

        $valuesSheet->setCellValue('A1', 'GUÍA DE VALORES PERMITIDOS');
        $valuesSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $valuesSheet->mergeCells('A1:E1');
        $valuesSheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $currentRow = 3;

        // Nota sobre valores personalizados
        $valuesSheet->setCellValue('A' . $currentRow, '⚡ IMPORTANTE: Para atributos marcados con (*), puede usar valores personalizados además de los listados');
        $valuesSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->getColor()->setRGB('CC6600');
        $valuesSheet->getStyle('A' . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $valuesSheet->getStyle('A' . $currentRow)->getFill()->getStartColor()->setRGB('FFF2CC');
        $valuesSheet->mergeCells('A' . $currentRow . ':E' . $currentRow);
        $currentRow += 2;

        // Headers para la tabla
        $valuesSheet->setCellValue('A' . $currentRow, 'CAMPO');
        $valuesSheet->setCellValue('B' . $currentRow, 'NOMBRE MOSTRADO');
        $valuesSheet->setCellValue('C' . $currentRow, 'TIPO');
        $valuesSheet->setCellValue('D' . $currentRow, 'NOTAS');

        $headerStyle = $valuesSheet->getStyle('A' . $currentRow . ':D' . $currentRow);
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setRGB('E0E0E0');

        $currentRow++;

        // CAMPOS FIJOS (sin valores personalizados)
        $fixedFields = [
            'currency_id' => [
                ['name' => 'USD', 'desc' => 'Dólar estadounidense'],
                ['name' => 'CLP', 'desc' => 'Peso chileno']
            ],
            'condition' => [
                ['name' => 'new', 'desc' => 'Producto nuevo'],
                ['name' => 'used', 'desc' => 'Producto usado']
            ],
            'listing_type_id' => [
                ['name' => 'gold_special', 'desc' => 'Publicación destacada premium'],
                ['name' => 'gold_pro', 'desc' => 'Publicación destacada pro'],
                ['name' => 'gold', 'desc' => 'Publicación destacada'],
                ['name' => 'silver', 'desc' => 'Publicación plata'],
                ['name' => 'bronze', 'desc' => 'Publicación bronce'],
                ['name' => 'free', 'desc' => 'Publicación gratuita']
            ],
            'local_pick_up' => [
                ['name' => 'true', 'desc' => 'El producto puede ser recogido en local'],
                ['name' => 'false', 'desc' => 'El producto no puede ser recogido en local']
            ],
            'free_shipping' => [
                ['name' => 'true', 'desc' => 'Envío gratuito para este producto'],
                ['name' => 'false', 'desc' => 'Envío con costo para este producto']
            ],
            'warranty_type' => [
                ['name' => 'Garantía de fábrica', 'desc' => 'Garantía ofrecida por el fabricante'],
                ['name' => 'Garantía del vendedor', 'desc' => 'Garantía ofrecida por el vendedor']
            ],
            'warranty_days' => [
                ['name' => '30', 'desc' => '1 mes de garantía'],
                ['name' => '60', 'desc' => '2 meses de garantía'],
                ['name' => '90', 'desc' => '3 meses de garantía'],
                ['name' => '180', 'desc' => '6 meses de garantía'],
                ['name' => '365', 'desc' => '1 año de garantía']
            ]

        ];


        foreach ($fixedFields as $fieldName => $values) {
            foreach ($values as $value) {
                $valuesSheet->setCellValue('A' . $currentRow, $fieldName);
                $valuesSheet->setCellValue('B' . $currentRow, $value['name']);
                $valuesSheet->setCellValue('C' . $currentRow, 'FIJO');
                $valuesSheet->setCellValue('D' . $currentRow, $value['desc']);
                $currentRow++;
            }
        }

        // ATRIBUTOS DE MERCADOLIBRE (con valores personalizados permitidos)
        foreach ($attributes as $attribute) {
            if (isset($attribute['tags']['required']) && $attribute['tags']['required'] && !empty($attribute['values'])) {
                $attributeName = $attribute['name'] ?? $attribute['id'];
                $isFirstRow = true;

                foreach ($attribute['values'] as $value) {
                    $fieldDisplayName = $isFirstRow ? $attributeName . ' (*)' : '';
                    $typeText = $isFirstRow ? 'FLEXIBLE' : '';
                    $notesText = $isFirstRow ? 'Puede usar valores personalizados' : '';

                    $valuesSheet->setCellValue('A' . $currentRow, $fieldDisplayName);
                    $valuesSheet->setCellValue('B' . $currentRow, $value['name'] ?? $value['id']);
                    $valuesSheet->setCellValue('C' . $currentRow, $typeText);
                    $valuesSheet->setCellValue('D' . $currentRow, $notesText);

                    if ($isFirstRow) {
                        $valuesSheet->getStyle('A' . $currentRow . ':D' . $currentRow)->getFont()->setBold(true);
                        $valuesSheet->getStyle('C' . $currentRow)->getFont()->getColor()->setRGB('0066CC');
                    }

                    $currentRow++;
                    $isFirstRow = false;
                }
            }
        }

        // Auto-ajustar columnas
        foreach (['A', 'B', 'C', 'D'] as $column) {
            $valuesSheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function createInstructionsSheet($spreadsheet, $categoryId)
    {
        $instructionsSheet = $spreadsheet->getActiveSheet();
        $instructionsSheet->setTitle('Instrucciones');

        // Título principal
        $instructionsSheet->setCellValue('A1', 'INSTRUCCIONES DE USO - PLANTILLA DE PRODUCTOS');
        $instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $instructionsSheet->mergeCells('A1:F1');
        $instructionsSheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // AGREGAR CATEGORY_ID DE FORMA PROCESABLE
        $instructionsSheet->setCellValue('A2', "Categoría ID: {$categoryId}");
        $instructionsSheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $instructionsSheet->mergeCells('A2:F2');

        // Agregar una celda especial con solo el ID para procesamiento posterior
        $instructionsSheet->setCellValue('H1', 'CATEGORY_DATA');
        $instructionsSheet->setCellValue('H2', $categoryId);
        $instructionsSheet->getStyle('H1:H2')->getFont()->getColor()->setRGB('FFFFFF'); // Texto blanco (invisible)
        $instructionsSheet->getColumnDimension('H')->setWidth(0.1); // Columna muy pequeña

        $currentRow = 4;

        // Sección 1: Información general
        $instructionsSheet->setCellValue('A' . $currentRow, '1. INFORMACIÓN GENERAL');
        $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
        $currentRow += 2;

        $instructions = [
            '• Use la pestaña "Plantilla" para ingresar sus productos',
            '• Consulte la pestaña "Valores Permitidos" para ver todos los valores válidos',
            '• Los campos marcados como requeridos son obligatorios',
            '• Puede agregar hasta 1000 productos en una sola plantilla',
            '• Las columnas con listas desplegables tienen valores predefinidos',
            '• El campo category_id está prellenado automáticamente'
        ];

        foreach ($instructions as $instruction) {
            $instructionsSheet->setCellValue('A' . $currentRow, $instruction);
            $currentRow++;
        }

        $currentRow += 2;

        // Sección 2: Campos obligatorios
        $instructionsSheet->setCellValue('A' . $currentRow, '2. CAMPOS OBLIGATORIOS');
        $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
        $currentRow += 2;

        $requiredFields = [
            'title' => 'Título del producto (máximo 60 caracteres)',
            'price' => 'Precio del producto (solo números)',
            'currency_id' => 'Moneda: USD o CLP',
            'available_quantity' => 'Cantidad disponible (número entero)',
            'condition' => 'Condición: new (nuevo) o used (usado)',
            'listing_type_id' => 'Tipo de publicación (ver valores permitidos)',
            'category_id' => 'ID de categoría (prellenado automáticamente - NO MODIFICAR)',
            'warranty_type' => 'Tipo de garantía: Garantía de fábrica o Garantía del vendedor',
            'warranty_days' => 'Días de garantía (número entre 1 y 365)',
            'local_pick_up' => 'Indica si el producto puede ser recogido en local (true/false)',
            'free_shipping' => 'Indica si el producto tiene envío gratuito (true/false)'
        ];

        foreach ($requiredFields as $field => $description) {
            $instructionsSheet->setCellValue('A' . $currentRow, "• {$field}:");
            $instructionsSheet->setCellValue('B' . $currentRow, $description);
            $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true);

            // Resaltar category_id
            if ($field === 'category_id') {
                $instructionsSheet->getStyle('A' . $currentRow . ':B' . $currentRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $instructionsSheet->getStyle('A' . $currentRow . ':B' . $currentRow)->getFill()
                    ->getStartColor()->setRGB('FFE6E6');
            }

            $currentRow++;
        }

        $currentRow += 2;

        // Sección 3: Valores personalizados
        $instructionsSheet->setCellValue('A' . $currentRow, '3. VALORES PERSONALIZADOS EN ATRIBUTOS');
        $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
        $instructionsSheet->getStyle('A' . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $instructionsSheet->getStyle('A' . $currentRow)->getFill()->getStartColor()->setRGB('FFE6CC');
        $currentRow += 2;

        $customValueInstructions = [
            '• Para atributos como MARCA, COLOR, MATERIAL, etc., puede:',
            '  - Seleccionar un valor de la lista desplegable (recomendado)',
            '  - O escribir un valor personalizado directamente',
            '',
            '• IMPORTANTE: Los valores personalizados deben cumplir estas reglas:',
            '  - Solo texto alfanumérico y espacios',
            '  - Máximo 255 caracteres',
            '  - Sin caracteres especiales como @, #, $, %, etc.',
            '',
            '• Ejemplos de valores personalizados válidos:',
            '  - Marca nueva: "Mi Marca Nueva"',
            '  - Color personalizado: "Azul Marino Metalizado"',
            '  - Material específico: "Algodón Orgánico Certificado"',
            '',
            '• NOTA IMPORTANTE: Ahora solo se guardan los nombres de los valores',
            '• El sistema procesará automáticamente la conversión interna',
            '• Se recomienda usar valores de la lista cuando sea posible'
        ];

        foreach ($customValueInstructions as $instruction) {
            if (empty($instruction)) {
                $currentRow++;
                continue;
            }

            $instructionsSheet->setCellValue('A' . $currentRow, $instruction);
            if (strpos($instruction, 'IMPORTANTE:') !== false) {
                $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->getColor()->setRGB('CC0000');
            } elseif (strpos($instruction, 'Ejemplos') !== false) {
                $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->getColor()->setRGB('0066CC');
            } elseif (strpos($instruction, 'NOTA IMPORTANTE:') !== false) {
                $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->getColor()->setRGB('0066CC');
                $instructionsSheet->getStyle('A' . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $instructionsSheet->getStyle('A' . $currentRow)->getFill()->getStartColor()->setRGB('E6F3FF');
            }
            $instructionsSheet->mergeCells('A' . $currentRow . ':F' . $currentRow);
            $currentRow++;
        }

        $currentRow += 2;

        // Sección 4: Campos opcionales
        $instructionsSheet->setCellValue('A' . $currentRow, '4. CAMPOS OPCIONALES');
        $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
        $currentRow += 2;

        $opcionalFields = [
            'description' => 'Descripción del producto',
            'pictures' => 'URLs de imágenes separadas por comas',
            'shipping' => 'Configuración de envío',
            'sale_terms' => 'Términos de venta',
            'catalog_product_id' => 'ID de producto en catálogo'
        ];

        foreach ($opcionalFields as $field => $description) {
            $instructionsSheet->setCellValue('A' . $currentRow, "• {$field}:");
            $instructionsSheet->setCellValue('B' . $currentRow, $description);
            $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true);
            $currentRow++;
        }

        $currentRow += 2;

        // Sección 5: Consejos importantes
        $instructionsSheet->setCellValue('A' . $currentRow, '5. CONSEJOS IMPORTANTES');
        $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
        $instructionsSheet->getStyle('A' . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $instructionsSheet->getStyle('A' . $currentRow)->getFill()->getStartColor()->setRGB('E6F3FF');
        $currentRow += 2;

        $tips = [
            '• Guarde el archivo frecuentemente mientras trabaja',
            '• No modifique los nombres de las columnas (headers)',
            '• NO modifique los valores de category_id (están prellenados)',
            '• No elimine las pestañas del archivo',
            '• Valide sus datos antes de procesar el archivo',
            '• Para dudas, consulte la documentación de MercadoLibre'
        ];

        foreach ($tips as $tip) {
            $instructionsSheet->setCellValue('A' . $currentRow, $tip);
            $instructionsSheet->mergeCells('A' . $currentRow . ':F' . $currentRow);

            // Resaltar el tip sobre category_id
            if (strpos($tip, 'category_id') !== false) {
                $instructionsSheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->getColor()->setRGB('CC0000');
            }

            $currentRow++;
        }

        // Auto-ajustar columnas
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
            $instructionsSheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
    public function ListCategory($clientId)
    {
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        try {
            if ($credentials->isTokenExpired()) {
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);

                if ($refreshResponse->failed()) {
                    return response()->json(['error' => 'No se pudo refrescar el token'], 401);
                }

                $data = $refreshResponse->json();
                $credentials->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'],
                    'expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al refrescar token: ' . $e->getMessage(),
            ], 500);
        }

        try {
            $baseUrl = 'https://api.mercadolibre.com/sites/MLC/categories/all';
            $response = Http::timeout(30)->withToken($credentials->access_token)->get($baseUrl);

            // Filtrar la respuesta para obtener solo id, name y children_categories
            $filteredCategories = collect($response->json())
                //->take(5000) // Limita a 5000 categorías principales
                ->map(function ($category) {
                    return [
                        'id' => $category['id'],
                        'name' => $category['name'],
                        'children_categories' => $category['children_categories']
                    ];
                })
                ->values()
                ->all();

            return response()->json($filteredCategories, $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en peticion a ML: ' . $e->getMessage(),
            ], 500);
        }
    }
}
