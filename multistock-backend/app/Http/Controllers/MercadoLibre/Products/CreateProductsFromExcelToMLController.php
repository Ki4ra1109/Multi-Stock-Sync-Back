<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Http;

class CreateProductsFromExcelToMLController extends Controller
{
    public function subirProductosDesdeExcel(Request $request, $clientId)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getSheetByName('Plantilla');
        if (!$sheet) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró la hoja "Plantilla".'
            ], 400);
        }

        // Leer encabezados
        $rows = [];
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $sheet->getCell($col . $row)->getValue();
                $rowData[$col] = $cellValue;
            }
            $rows[] = $rowData;
        }
        $headerRow = $rows[0] ?? [];
        $headers = [];
        foreach ($headerRow as $colLetra => $nombreCampo) {
            $nombreCampo = trim($nombreCampo);
            if (!empty($nombreCampo)) {
                $headers[$colLetra] = $nombreCampo;
            }
        }
        // Procesar filas de datos (desde la fila 2)
        $products = [];
        for ($filaIndex = 1; $filaIndex < count($rows); $filaIndex++) {
            $fila = $rows[$filaIndex];
            $title = $fila['A'] ?? null;
            if (empty($title)) continue;
            $product = [];
            foreach ($headers as $colLetra => $header) {
                $product[$header] = $fila[$colLetra] ?? null;
            }
            $products[] = $product;
        }
        if (empty($products)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron productos válidos en el Excel.'
            ], 400);
        }

        // Obtener credenciales de Mercado Libre
        $cred = MercadoLibreCredential::where('client_id', $clientId)->first();
        if (!$cred || !$cred->access_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el cliente.'
            ], 400);
        }
        $accessToken = $cred->access_token;

        $resultados = [];
        foreach ($products as $idx => $prod) {
            // Mapear campos del Excel a la estructura de ML
            $payload = [
                'title' => $prod['title'] ?? '',
                'category_id' => $prod['category_id'] ?? '',
                'price' => $prod['price'] ?? 0,
                'currency_id' => $prod['currency_id'] ?? 'CLP',
                'available_quantity' => $prod['available_quantity'] ?? 1,
                'buying_mode' => 'buy_it_now',
                'listing_type_id' => $prod['listing_type_id'] ?? 'free',
                'condition' => $prod['condition'] ?? 'new',
                'description' => [
                    'plain_text' => $prod['description'] ?? ''
                ],
                'pictures' => isset($prod['pictures']) ? [[ 'source' => $prod['pictures'] ]] : [],
                // Puedes agregar más atributos aquí si los tienes en el Excel
            ];
            // Agregar atributos personalizados si existen
            $atributos = [];
            $atributosMap = [
                'Marca' => 'BRAND',
                'Modelo' => 'MODEL',
                'Género' => 'GENDER',
                'Color' => 'COLOR',
                'Talla' => 'SIZE',
                'Temporada' => 'SEASON',
            ];
            foreach ($atributosMap as $col => $id) {
                if (!empty($prod[$col])) {
                    $atributos[] = [
                        'id' => $id,
                        'value_name' => $prod[$col]
                    ];
                }
            }
            if (!empty($atributos)) {
                $payload['attributes'] = $atributos;
            }
            // Llamar a la API de ML
            try {
                $response = Http::withToken($accessToken)
                    ->post('https://api.mercadolibre.com/items', $payload);
                if ($response->successful()) {
                    $resultados[] = [
                        'producto' => $prod['title'] ?? '',
                        'status' => 'creado',
                        'ml_id' => $response->json('id'),
                        'permalink' => $response->json('permalink')
                    ];
                } else {
                    $resultados[] = [
                        'producto' => $prod['title'] ?? '',
                        'status' => 'error',
                        'error' => $response->json()
                    ];
                }
            } catch (\Exception $e) {
                $resultados[] = [
                    'producto' => $prod['title'] ?? '',
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        return response()->json([
            'status' => 'success',
            'total' => count($products),
            'resultados' => $resultados
        ]);
    }
} 